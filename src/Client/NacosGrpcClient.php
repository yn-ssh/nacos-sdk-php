<?php

namespace Nacos\Client;

use Nacos\Exception\NacosException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NacosGrpcClient
{
    /**
     * @var string
     */
    private $serverUrl;

    /**
     * @var int
     */
    private $grpcPort;

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
     * @var NacosClient|null 引用HTTP客户端，用于共享accessToken
     */
    private $httpClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool|null Cached availability check result
     */
    private $availabilityCache;

    /**
     * NacosGrpcClient constructor.
     * @param string $serverUrl
     * @param int $grpcPort
     * @param string $namespaceId
     * @param string $accessKey
     * @param string $secretKey
     * @param LoggerInterface|null $logger
     * @param NacosClient|null $httpClient 共享的HTTP客户端，用于获取accessToken
     */
    public function __construct(
        string $serverUrl,
        int $grpcPort = 9848,
        string $namespaceId = 'public',
        string $accessKey = '',
        string $secretKey = '',
        ?LoggerInterface $logger = null,
        ?NacosClient $httpClient = null
    ) {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->grpcPort = $grpcPort;
        $this->namespaceId = $namespaceId;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->logger = $logger ?? new NullLogger();
        $this->httpClient = $httpClient;
        $this->availabilityCache = null;
    }

    /**
     * 获取gRPC服务器地址
     * @return string
     */
    public function getGrpcServerAddress(): string
    {
        $host = parse_url($this->serverUrl, PHP_URL_HOST) ?: 'localhost';
        return $host . ':' . $this->grpcPort;
    }

    /**
     * 检查gRPC服务是否可用
     * @return bool
     */
    public function isGrpcAvailable(): bool
    {
        // Use cached result if available
        if ($this->availabilityCache !== null) {
            return $this->availabilityCache;
        }

        // 检查 gRPC 扩展是否安装
        if (!extension_loaded('grpc')) {
            $this->logger->info('gRPC extension is not installed');
            $this->availabilityCache = false;
            return false;
        }

        // 检查 protobuf 扩展是否安装
        if (!extension_loaded('protobuf')) {
            $this->logger->info('Protobuf extension is not installed');
            $this->availabilityCache = false;
            return false;
        }

        try {
            $address = $this->getGrpcServerAddress();
            $host = parse_url($address, PHP_URL_HOST) ?: 'localhost';
            $port = parse_url($address, PHP_URL_PORT) ?: 9848;

            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 2, 'usec' => 0]);
            $result = socket_connect($socket, $host, $port);
            socket_close($socket);

            if (!$result) {
                $this->logger->info('gRPC port is not reachable', ['address' => $address]);
                $this->availabilityCache = false;
                return false;
            }

            $this->logger->info('gRPC service is available', ['address' => $address]);
            $this->availabilityCache = true;
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('gRPC service is not available', ['exception' => $e->getMessage()]);
            $this->availabilityCache = false;
            return false;
        }
    }

    /**
     * 重置可用性缓存，强制下次重新检测
     * @return void
     */
    public function resetAvailabilityCache(): void
    {
        $this->availabilityCache = null;
    }

    /**
     * 确保gRPC可用，不可用时抛出异常
     * @throws NacosException
     */
    private function ensureAvailable(): void
    {
        if (!extension_loaded('grpc')) {
            throw new NacosException('gRPC extension is not installed. Please install the gRPC PHP extension to use gRPC features.');
        }

        if (!extension_loaded('protobuf')) {
            throw new NacosException('Protobuf extension is not installed. Please install the Protobuf PHP extension to use gRPC features.');
        }

        if (!$this->isGrpcAvailable()) {
            throw new NacosException('gRPC service is not available at ' . $this->getGrpcServerAddress() . '. The SDK will fall back to HTTP.');
        }
    }

    /**
     * 发送gRPC请求
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws NacosException
     */
    public function request(string $method, array $params = [])
    {
        $this->ensureAvailable();

        // 确保accessToken有效（如果有httpClient共享的话）
        if ($this->httpClient !== null) {
            $this->httpClient->ensureTokenValid();
        }

        // gRPC request implementation using the grpc extension
        // This connects to the Nacos gRPC server and sends protocol buffer messages
        try {
            $this->logger->info('gRPC request', ['method' => $method, 'params' => $params]);

            $address = $this->getGrpcServerAddress();

            // Create gRPC channel
            $channel = new \Grpc\Channel($address, [
                'credentials' => \Grpc\ChannelCredentials::createInsecure(),
            ]);

            // Build the request metadata
            $metadata = $this->buildRequestMetadata();

            // Nacos gRPC uses a specific request format with Payload
            // The actual implementation requires generated PHP classes from the proto definition
            // Since the proto generated classes are not available, we throw a clear exception
            $channel->close();

            throw new NacosException(
                'gRPC protocol implementation requires generated PHP classes from the Nacos proto definition. ' .
                'Please run proto code generation first, or use the HTTP API as fallback.'
            );
        } catch (NacosException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('gRPC request failed', ['exception' => $e->getMessage()]);
            throw new NacosException('gRPC request failed: ' . $e->getMessage());
        }
    }

    /**
     * 构建gRPC请求元数据
     * Nacos gRPC 鉴权支持两种方式：
     * 1. 用户名密码认证：通过HTTP登录获取accessToken，在gRPC metadata中传递
     * 2. AK/SK认证：直接在metadata中传递accessKey和secretKey
     * @return array
     */
    private function buildRequestMetadata(): array
    {
        $metadata = [
            'namespaceId' => $this->namespaceId,
        ];

        // 优先使用用户名密码认证（通过共享的 NacosClient 获取 accessToken）
        if ($this->httpClient !== null) {
            $accessToken = $this->httpClient->getAccessToken();
            if (!empty($accessToken)) {
                $metadata['accessToken'] = $accessToken;
                $this->logger->debug('gRPC using accessToken from HTTP client', [
                    'token_prefix' => substr($accessToken, 0, 8) . '...',
                ]);
            }
        }

        // AK/SK 认证方式
        if (!empty($this->accessKey)) {
            $metadata['accessKey'] = $this->accessKey;
        }

        if (!empty($this->secretKey)) {
            $metadata['secretKey'] = $this->secretKey;
        }

        return $metadata;
    }

    /**
     * 获取所有服务实例（gRPC版本）
     * @param string $serviceName
     * @param string $group
     * @param bool $healthyOnly
     * @return array
     * @throws NacosException
     */
    public function getAllInstances(string $serviceName, string $group = 'DEFAULT_GROUP', bool $healthyOnly = true)
    {
        $this->ensureAvailable();

        return $this->request('getAllInstances', [
            'serviceName' => $serviceName,
            'group' => $group,
            'healthyOnly' => $healthyOnly,
            'namespaceId' => $this->namespaceId
        ]);
    }

    /**
     * 选择一个健康实例（gRPC版本）
     * @param string $serviceName
     * @param string $group
     * @return array|null
     * @throws NacosException
     */
    public function selectOneHealthyInstance(string $serviceName, string $group = 'DEFAULT_GROUP')
    {
        $this->ensureAvailable();

        return $this->request('selectOneHealthyInstance', [
            'serviceName' => $serviceName,
            'group' => $group,
            'namespaceId' => $this->namespaceId
        ]);
    }

    /**
     * 发送心跳（gRPC版本）
     * @param string $serviceName
     * @param string $ip
     * @param int $port
     * @param string $group
     * @param bool $ephemeral
     * @return bool
     * @throws NacosException
     */
    public function sendHeartbeat(string $serviceName, string $ip, int $port, string $group = 'DEFAULT_GROUP', bool $ephemeral = true)
    {
        $this->ensureAvailable();

        $result = $this->request('sendHeartbeat', [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'group' => $group,
            'ephemeral' => $ephemeral,
            'namespaceId' => $this->namespaceId
        ]);
        return isset($result['code']) && $result['code'] === 0;
    }

    /**
     * 删除配置（gRPC版本）
     * @param string $dataId
     * @param string $group
     * @return bool
     * @throws NacosException
     */
    public function deleteConfig(string $dataId, string $group = 'DEFAULT_GROUP')
    {
        $this->ensureAvailable();

        $result = $this->request('deleteConfig', [
            'dataId' => $dataId,
            'group' => $group,
            'namespaceId' => $this->namespaceId
        ]);
        return isset($result['code']) && $result['code'] === 0;
    }

    /**
     * 监听配置变更（gRPC版本）
     * @param array $listeners
     * @param callable $callback
     * @return void
     * @throws NacosException
     */
    public function listenConfig(array $listeners, callable $callback)
    {
        $this->ensureAvailable();

        $this->request('listenConfig', [
            'listeners' => $listeners,
            'namespaceId' => $this->namespaceId
        ]);
    }

    /**
     * 获取配置（gRPC版本）
     * @param string $dataId
     * @param string $group
     * @return string
     * @throws NacosException
     */
    public function getConfig(string $dataId, string $group = 'DEFAULT_GROUP')
    {
        $this->ensureAvailable();

        return $this->request('getConfig', [
            'dataId' => $dataId,
            'group' => $group,
            'namespaceId' => $this->namespaceId
        ]);
    }

    /**
     * 发布配置（gRPC版本）
     * @param string $dataId
     * @param string $group
     * @param string $content
     * @param string $type
     * @return bool
     * @throws NacosException
     */
    public function publishConfig(string $dataId, string $group, string $content, string $type = 'text')
    {
        $this->ensureAvailable();

        $result = $this->request('publishConfig', [
            'dataId' => $dataId,
            'group' => $group,
            'content' => $content,
            'type' => $type,
            'namespaceId' => $this->namespaceId
        ]);
        return isset($result['code']) && $result['code'] === 0;
    }

    /**
     * 注册服务实例（gRPC版本）
     * @param string $serviceName
     * @param string $ip
     * @param int $port
     * @param string $group
     * @param array $metadata
     * @param int $weight
     * @param bool $ephemeral
     * @return bool
     * @throws NacosException
     */
    public function registerInstance(string $serviceName, string $ip, int $port, string $group = 'DEFAULT_GROUP', array $metadata = [], int $weight = 1, bool $ephemeral = true)
    {
        $this->ensureAvailable();

        $result = $this->request('registerInstance', [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'group' => $group,
            'metadata' => $metadata,
            'weight' => $weight,
            'ephemeral' => $ephemeral,
            'namespaceId' => $this->namespaceId
        ]);
        return isset($result['code']) && $result['code'] === 0;
    }

    /**
     * 注销服务实例（gRPC版本）
     * @param string $serviceName
     * @param string $ip
     * @param int $port
     * @param string $group
     * @param bool $ephemeral
     * @return bool
     * @throws NacosException
     */
    public function deregisterInstance(string $serviceName, string $ip, int $port, string $group = 'DEFAULT_GROUP', bool $ephemeral = true)
    {
        $this->ensureAvailable();

        $result = $this->request('deregisterInstance', [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'group' => $group,
            'ephemeral' => $ephemeral,
            'namespaceId' => $this->namespaceId
        ]);
        return isset($result['code']) && $result['code'] === 0;
    }

    /**
     * @return string
     */
    public function getServerUrl(): string
    {
        return $this->serverUrl;
    }

    /**
     * @return int
     */
    public function getGrpcPort(): int
    {
        return $this->grpcPort;
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

    /**
     * @return NacosClient|null
     */
    public function getHttpClient(): ?NacosClient
    {
        return $this->httpClient;
    }
}
