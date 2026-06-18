<?php

namespace Nacos\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Nacos\Exception\NacosException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NacosClient
{
    /**
     * @var GuzzleClient
     */
    private $httpClient;

    /**
     * @var string
     */
    private $serverUrl;

    /**
     * @var string
     */
    private $namespaceId;

    /**
     * @var string
     */
    private $accessKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var int
     */
    private $tokenExpireTime;

    /**
     * @var string
     */
    private $serverVersion;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * NacosClient constructor.
     * @param string $serverUrl
     * @param string $namespaceId
     * @param string $accessKey
     * @param string $secretKey
     * @param LoggerInterface|null $logger
     * @param string $username
     * @param string $password
     */
    public function __construct(
        string $serverUrl,
        string $namespaceId = 'public',
        string $accessKey = '',
        string $secretKey = '',
        ?LoggerInterface $logger = null,
        string $username = '',
        string $password = ''
    ) {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->namespaceId = $namespaceId;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->username = $username;
        $this->password = $password;
        $this->logger = $logger ?? new NullLogger();
        $this->accessToken = '';
        $this->tokenExpireTime = 0;

        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->serverUrl,
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        // 检测Nacos服务器版本
        $this->detectServerVersion();
        
        // 如果提供了用户名密码，先登录获取 token
        if (!empty($this->username) && !empty($this->password)) {
            $this->login();
        }
    }

    /**
     * 检测Nacos服务器版本
     */
    private function detectServerVersion()
    {
        try {
            $response = $this->httpClient->get('/nacos/v1/console/server/info');
            $body = $response->getBody()->getContents();
            $result = json_decode($body, true);
            
            if (isset($result['version'])) {
                $this->serverVersion = $result['version'];
                $this->logger->info('Detected Nacos server version: ' . $this->serverVersion);
            } else {
                $this->serverVersion = '2.0';
                $this->logger->info('Assuming Nacos server version: 2.0');
            }
        } catch (\Exception $e) {
            $this->serverVersion = '2.0';
            $this->logger->warning('Failed to detect Nacos server version, assuming 2.0', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * 登录获取 access token
     * @return bool
     */
    private function login(): bool
    {
        if (empty($this->username) || empty($this->password)) {
            return false;
        }

        try {
            $response = $this->httpClient->post('/nacos/v1/auth/login', [
                'form_params' => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
            ]);
            
            $body = $response->getBody()->getContents();
            $result = json_decode($body, true);
            
            if (isset($result['accessToken']) && !empty($result['accessToken'])) {
                $this->accessToken = $result['accessToken'];
                $tokenTtl = $result['tokenTtl'] ?? 18000;
                $this->tokenExpireTime = time() + $tokenTtl - 300;
                $this->logger->info('Login successful, token obtained');
                return true;
            }
            
            $this->logger->warning('Login failed: no accessToken in response');
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Login failed', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 检查 token 是否需要刷新
     * @return void
     */
    private function refreshTokenIfNeeded(): void
    {
        if (empty($this->username) || empty($this->password)) {
            return;
        }

        if (empty($this->accessToken) || time() >= $this->tokenExpireTime) {
            $this->login();
        }
    }

    /**
     * 确保accessToken有效（供gRPC客户端调用）
     * 如果token已过期或即将过期，自动刷新
     * @return void
     */
    public function ensureTokenValid(): void
    {
        $this->refreshTokenIfNeeded();
    }

    /**
     * 生成鉴权签名
     * @param string $method
     * @param string $path
     * @param array $params
     * @return array
     */
    private function generateAuthHeaders(string $method, string $path, array $params): array
    {
        if (empty($this->accessKey) || empty($this->secretKey)) {
            return [];
        }

        $timestamp = time() * 1000;
        $nonce = uniqid();
        
        $signatureString = strtoupper($method) . '\n'
            . $nonce . '\n'
            . $timestamp . '\n'
            . $path . '\n';
        
        $signature = hash_hmac('sha1', $signatureString, $this->secretKey, true);
        $signature = base64_encode($signature);
        
        return [
            'AccessKey' => $this->accessKey,
            'Timestamp' => (string)$timestamp,
            'Nonce' => $nonce,
            'Signature' => $signature,
        ];
    }

    /**
     * @param string $path
     * @param array $params
     * @return array|string
     * @throws NacosException
     */
    public function get(string $path, array $params = [])
    {
        return $this->request('GET', $path, $params);
    }

    /**
     * 获取原始响应体（不进行JSON解析）
     * 用于getConfig等返回纯文本/JSON配置内容的场景
     * @param string $path
     * @param array $params
     * @return string
     * @throws NacosException
     */
    public function getRaw(string $path, array $params = []): string
    {
        return $this->requestRaw('GET', $path, $params);
    }

    /**
     * @param string $path
     * @param array $params
     * @return array|string
     * @throws NacosException
     */
    public function post(string $path, array $params = [])
    {
        return $this->request('POST', $path, $params);
    }

    /**
     * @param string $path
     * @param array $params
     * @return array|string
     * @throws NacosException
     */
    public function delete(string $path, array $params = [])
    {
        return $this->request('DELETE', $path, $params);
    }

    /**
     * @param string $path
     * @param array $params
     * @return array|string
     * @throws NacosException
     */
    public function put(string $path, array $params = [])
    {
        return $this->request('PUT', $path, $params);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array $params
     * @return array|string
     * @throws NacosException
     */
    private function request(string $method, string $path, array $params = [])
    {
        try {
            $this->refreshTokenIfNeeded();
            
            $options = [];
            
            // 添加鉴权头（AK/SK 方式）
            $authHeaders = $this->generateAuthHeaders($method, $path, $params);
            if (!empty($authHeaders)) {
                $options['headers'] = $authHeaders;
            }
            
            // 如果有 accessToken，通过 Header 传递（Nacos 不从 form_params 中读 token）
            if (!empty($this->accessToken)) {
                if (!isset($options['headers'])) {
                    $options['headers'] = [];
                }
                $options['headers']['accessToken'] = $this->accessToken;
            }
            
            if ($method === 'GET') {
                $options['query'] = $params;
            } else {
                $options['form_params'] = $params;
            }

            $response = $this->httpClient->request($method, $path, $options);
            $body = $response->getBody()->getContents();
            
            // Try to decode as JSON
            $result = json_decode($body, true);
            
            // If it's not JSON, return the raw string
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $body;
            }

            if (isset($result['code'])) {
                // Nacos v1 API: code !== 200 表示错误
                // Nacos v2 API: code !== 0 表示错误
                // 心跳接口返回 code=10200 表示成功
                $isSuccess = $result['code'] === 200 || $result['code'] === 0 || $result['code'] === 10200 || $result['code'] === null;
                if (!$isSuccess) {
                    throw new NacosException($result['message'] ?? 'Request failed', $result['code'] ?? 500);
                }
            }

            return $result;
        } catch (GuzzleException $e) {
            $this->logger->error('Nacos request failed', ['exception' => $e->getMessage()]);
            throw new NacosException('HTTP request failed: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            $this->logger->error('Nacos request failed', ['exception' => $e->getMessage()]);
            throw new NacosException($e->getMessage(), 500);
        }
    }

    /**
     * 发送请求并返回原始响应体（不进行JSON解析）
     * 用于getConfig等场景，配置内容本身可能是JSON字符串，不应被解析为数组
     * @param string $method
     * @param string $path
     * @param array $params
     * @return string
     * @throws NacosException
     */
    private function requestRaw(string $method, string $path, array $params = []): string
    {
        try {
            $this->refreshTokenIfNeeded();
            
            $options = [];
            
            // 添加鉴权头（AK/SK 方式）
            $authHeaders = $this->generateAuthHeaders($method, $path, $params);
            if (!empty($authHeaders)) {
                $options['headers'] = $authHeaders;
            }
            
            // 如果有 accessToken，通过 Header 传递
            if (!empty($this->accessToken)) {
                if (!isset($options['headers'])) {
                    $options['headers'] = [];
                }
                $options['headers']['accessToken'] = $this->accessToken;
            }
            
            if ($method === 'GET') {
                $options['query'] = $params;
            } else {
                $options['form_params'] = $params;
            }

            $response = $this->httpClient->request($method, $path, $options);
            $body = $response->getBody()->getContents();
            
            // 检查是否是Nacos v2 API的JSON错误响应
            $result = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($result['code'])) {
                $isSuccess = $result['code'] === 200 || $result['code'] === 0 || $result['code'] === 10200 || $result['code'] === null;
                if (!$isSuccess) {
                    throw new NacosException($result['message'] ?? 'Request failed', $result['code'] ?? 500);
                }
                // Nacos v2 API成功响应，返回data字段内容
                if (isset($result['data'])) {
                    return is_string($result['data']) ? $result['data'] : json_encode($result['data']);
                }
            }
            
            return $body;
        } catch (GuzzleException $e) {
            $this->logger->error('Nacos request failed', ['exception' => $e->getMessage()]);
            throw new NacosException('HTTP request failed: ' . $e->getMessage(), 500);
        } catch (NacosException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Nacos request failed', ['exception' => $e->getMessage()]);
            throw new NacosException($e->getMessage(), 500);
        }
    }

    /**
     * @return string
     */
    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    /**
     * @return string
     */
    public function getServerUrl(): string
    {
        return $this->serverUrl;
    }

    /**
     * @return string
     */
    public function getNamespaceId(): string
    {
        return $this->namespaceId;
    }

    /**
     * 获取用于API请求的namespaceId
     * Nacos中public命名空间的实际ID为空字符串
     * @return string
     */
    public function getNamespaceIdForApi(): string
    {
        return $this->namespaceId === 'public' ? '' : $this->namespaceId;
    }

    /**
     * @return string
     */
    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    /**
     * @return string
     */
    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
