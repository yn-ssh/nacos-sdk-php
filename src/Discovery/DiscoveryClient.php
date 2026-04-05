<?php

namespace Nacos\Discovery;

use Nacos\Client\NacosClient;
use Nacos\Client\NacosGrpcClient;
use Nacos\Exception\NacosException;

class DiscoveryClient
{
    /**
     * @var NacosClient
     */
    private $client;

    /**
     * @var NacosGrpcClient
     */
    private $grpcClient;

    /**
     * DiscoveryClient constructor.
     * @param NacosClient $client
     * @param NacosGrpcClient|null $grpcClient
     */
    public function __construct(NacosClient $client, ?NacosGrpcClient $grpcClient = null)
    {
        $this->client = $client;
        $this->grpcClient = $grpcClient;
    }

    /**
     * 根据Nacos版本获取正确的API路径
     * @param string $api
     * @return string
     */
    private function getApiPath(string $api): string
    {
        $version = $this->client->getServerVersion();
        
        // 检查是否是Nacos 3.x
        if (version_compare($version, '3.0.0', '>=')) {
            // Nacos 3.x API路径
            switch ($api) {
                case 'instance':
                    return '/nacos/v1/ns/instance';
                case 'instances':
                    return '/nacos/v1/ns/instance/list';
                case 'beat':
                    return '/nacos/v1/ns/instance/beat';
                default:
                    return '/nacos/v1/ns/' . $api;
            }
        } else {
            // Nacos 2.x API路径
            switch ($api) {
                case 'instance':
                    return '/nacos/v1/ns/instance';
                case 'instances':
                    return '/nacos/v1/ns/instance/list';
                case 'beat':
                    return '/nacos/v1/ns/instance/beat';
                default:
                    return '/nacos/v1/ns/' . $api;
            }
        }
    }

    /**
     * Register service
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
    public function registerInstance(string $serviceName, string $ip, int $port, string $group = 'DEFAULT_GROUP', array $metadata = [], int $weight = 1, bool $ephemeral = true): bool
    {
        // 优先使用gRPC客户端
        if ($this->grpcClient && $this->grpcClient->isGrpcAvailable()) {
            try {
                return $this->grpcClient->registerInstance($serviceName, $ip, $port, $group, $metadata, $weight, $ephemeral);
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->warning('gRPC registerInstance failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $params = [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'ephemeral' => $ephemeral ? 'true' : 'false',
        ];

        $result = $this->client->post($this->getApiPath('instance'), $params);
        return $result === 'ok';
    }

    /**
     * Deregister service
     * @param string $serviceName
     * @param string $ip
     * @param int $port
     * @param string $group
     * @param bool $ephemeral
     * @return bool
     * @throws NacosException
     */
    public function deregisterInstance(string $serviceName, string $ip, int $port, string $group = 'DEFAULT_GROUP', bool $ephemeral = true): bool
    {
        // 优先使用gRPC客户端
        if ($this->grpcClient && $this->grpcClient->isGrpcAvailable()) {
            try {
                return $this->grpcClient->deregisterInstance($serviceName, $ip, $port, $group, $ephemeral);
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->warning('gRPC deregisterInstance failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $params = [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'ephemeral' => $ephemeral ? 'true' : 'false',
        ];

        $result = $this->client->delete($this->getApiPath('instance'), $params);
        return $result === 'ok';
    }

    /**
     * Get all instances of a service
     * @param string $serviceName
     * @param string $group
     * @param bool $healthyOnly
     * @return array
     * @throws NacosException
     */
    public function getAllInstances(string $serviceName, string $group = 'DEFAULT_GROUP', bool $healthyOnly = true): array
    {
        // 优先使用gRPC客户端
        if ($this->grpcClient && $this->grpcClient->isGrpcAvailable()) {
            try {
                return $this->grpcClient->getAllInstances($serviceName, $group, $healthyOnly);
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->warning('gRPC getAllInstances failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $params = [
            'serviceName' => $serviceName,
        ];

        $result = $this->client->get($this->getApiPath('instances'), $params);
        return is_array($result) ? $result : [];
    }

    /**
     * Get one healthy instance of a service
     * @param string $serviceName
     * @param string $group
     * @return array|null
     * @throws NacosException
     */
    public function selectOneHealthyInstance(string $serviceName, string $group = 'DEFAULT_GROUP'): ?array
    {
        // 优先使用gRPC客户端
        if ($this->grpcClient && $this->grpcClient->isGrpcAvailable()) {
            try {
                return $this->grpcClient->selectOneHealthyInstance($serviceName, $group);
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->warning('gRPC selectOneHealthyInstance failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $instances = $this->getAllInstances($serviceName, $group, true);
        
        if (isset($instances['hosts']) && is_array($instances['hosts']) && count($instances['hosts']) > 0) {
            return $instances['hosts'][0];
        }
        
        return null;
    }

    /**
     * Send heartbeat
     * @param string $serviceName
     * @param string $ip
     * @param int $port
     * @param string $group
     * @return bool
     * @throws NacosException
     */
    public function sendHeartbeat(string $serviceName, string $ip, int $port, string $group = 'DEFAULT_GROUP'): bool
    {
        // 优先使用gRPC客户端
        if ($this->grpcClient && $this->grpcClient->isGrpcAvailable()) {
            try {
                return $this->grpcClient->sendHeartbeat($serviceName, $ip, $port, $group);
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->warning('gRPC sendHeartbeat failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $params = [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
        ];

        $result = $this->client->put($this->getApiPath('beat'), $params);
        return is_array($result) ? isset($result['clientBeatInterval']) : ($result === 'ok');
    }
}
