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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * NacosGrpcClient constructor.
     * @param string $serverUrl
     * @param int $grpcPort
     * @param string $namespaceId
     * @param string $accessKey
     * @param string $secretKey
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        string $serverUrl,
        int $grpcPort = 9848,
        string $namespaceId = 'public',
        string $accessKey = '',
        string $secretKey = '',
        ?LoggerInterface $logger = null
    ) {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->grpcPort = $grpcPort;
        $this->namespaceId = $namespaceId;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->logger = $logger ?? new NullLogger();
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
        try {
            $address = $this->getGrpcServerAddress();
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 2, 'usec' => 0]);
            $result = socket_connect($socket, parse_url($address, PHP_URL_HOST), parse_url($address, PHP_URL_PORT));
            socket_close($socket);
            return $result;
        } catch (\Exception $e) {
            $this->logger->warning('gRPC service is not available', ['exception' => $e->getMessage()]);
            return false;
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
        try {
            // 检查gRPC扩展是否安装
            if (!extension_loaded('grpc')) {
                throw new NacosException('gRPC extension is not installed. Please install the gRPC PHP extension.');
            }
            
            // 检查protobuf扩展是否安装
            if (!extension_loaded('protobuf')) {
                throw new NacosException('Protobuf extension is not installed. Please install the Protobuf PHP extension.');
            }
            
            // 这里实现gRPC请求逻辑
            // 由于Nacos的gRPC协议需要生成对应的PHP代码
            // 暂时返回模拟数据
            $this->logger->info('gRPC request', ['method' => $method, 'params' => $params]);
            
            // 模拟gRPC响应
            return [
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'method' => $method,
                    'params' => $params,
                    'grpc' => true
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('gRPC request failed', ['exception' => $e->getMessage()]);
            throw new NacosException('gRPC request failed: ' . $e->getMessage());
        }
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
        $result = $this->request('getAllInstances', [
            'serviceName' => $serviceName,
            'group' => $group,
            'healthyOnly' => $healthyOnly,
            'namespaceId' => $this->namespaceId
        ]);
        return $result['data'] ?? [];
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
        $result = $this->request('selectOneHealthyInstance', [
            'serviceName' => $serviceName,
            'group' => $group,
            'namespaceId' => $this->namespaceId
        ]);
        return $result['data'] ?? null;
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
        $this->request('listenConfig', [
            'listeners' => $listeners,
            'namespaceId' => $this->namespaceId
        ]);
        // 模拟配置变更回调
        $callback([
            'dataId' => $listeners[0]['dataId'] ?? '',
            'group' => $listeners[0]['group'] ?? '',
            'content' => 'Updated config content'
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
}
