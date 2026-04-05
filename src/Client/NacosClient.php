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
     */
    public function __construct(
        string $serverUrl,
        string $namespaceId = 'public',
        string $accessKey = '',
        string $secretKey = '',
        ?LoggerInterface $logger = null
    ) {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->namespaceId = $namespaceId;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->logger = $logger ?? new NullLogger();

        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->serverUrl,
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        // 检测Nacos服务器版本
        $this->detectServerVersion();
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
        
        // 构建签名字符串
        $signatureString = strtoupper($method) . '\n'
            . $nonce . '\n'
            . $timestamp . '\n'
            . $path . '\n';
        
        // 生成签名
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
            $options = [];
            
            // 添加鉴权头
            $authHeaders = $this->generateAuthHeaders($method, $path, $params);
            if (!empty($authHeaders)) {
                $options['headers'] = $authHeaders;
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
                if (($result['code'] !== 200 && $result['code'] !== 0) && $result['code'] !== null) {
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
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}