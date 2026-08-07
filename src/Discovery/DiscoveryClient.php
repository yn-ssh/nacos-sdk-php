<?php

namespace Nacos\Discovery;

use Nacos\Client\NacosClient;
use Nacos\Exception\NacosException;

class DiscoveryClient
{
    /**
     * @var NacosClient
     */
    private $client;

    /**
     * DiscoveryClient constructor.
     * @param NacosClient $client
     */
    public function __construct(NacosClient $client)
    {
        $this->client = $client;
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
            // Nacos 3.x uses v2 API paths
            switch ($api) {
                case 'instance':
                    return '/nacos/v2/ns/instance';
                case 'instances':
                    return '/nacos/v2/ns/instance/list';
                case 'beat':
                    return '/nacos/v2/ns/instance/beat';
                default:
                    return '/nacos/v2/ns/' . $api;
            }
        } else {
            // Nacos 2.x uses v1 API paths
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
        $this->client->getLogger()->debug('[HTTP] registerInstance', ['serviceName' => $serviceName, 'ip' => $ip, 'port' => $port]);
        $params = [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'groupName' => $group,
            'weight' => $weight,
            'ephemeral' => $ephemeral ? 'true' : 'false',
        ];

        if (!empty($metadata)) {
            $params['metadata'] = json_encode($metadata);
        }

        $namespaceId = $this->client->getNamespaceIdForApi();
        if ($namespaceId && $namespaceId !== 'public') {
            $params['namespaceId'] = $namespaceId;
        }

        $result = $this->client->post($this->getApiPath('instance'), $params);
        return $result === 'ok' || $result === true || (is_array($result) && ($result['code'] === 0 || $result['code'] === 200));
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
        $this->client->getLogger()->debug('[HTTP] deregisterInstance', ['serviceName' => $serviceName, 'ip' => $ip, 'port' => $port]);
        $params = [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'groupName' => $group,
            'ephemeral' => $ephemeral ? 'true' : 'false',
        ];

        $namespaceId = $this->client->getNamespaceIdForApi();
        if ($namespaceId && $namespaceId !== 'public') {
            $params['namespaceId'] = $namespaceId;
        }

        $result = $this->client->delete($this->getApiPath('instance'), $params);
        return $result === 'ok' || $result === true || (is_array($result) && ($result['code'] === 0 || $result['code'] === 200));
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
        $this->client->getLogger()->debug('[HTTP] getAllInstances', ['serviceName' => $serviceName, 'group' => $group]);
        $params = [
            'serviceName' => $serviceName,
            'groupName' => $group,
            'healthyOnly' => $healthyOnly ? 'true' : 'false',
        ];

        $namespaceId = $this->client->getNamespaceIdForApi();
        if ($namespaceId && $namespaceId !== 'public') {
            $params['namespaceId'] = $namespaceId;
        }

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
        $this->client->getLogger()->debug('[HTTP] selectOneHealthyInstance', ['serviceName' => $serviceName, 'group' => $group]);
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
        $this->client->getLogger()->debug('[HTTP] sendHeartbeat', ['serviceName' => $serviceName, 'ip' => $ip, 'port' => $port]);
        $params = [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'groupName' => $group,
        ];

        // 获取 namespaceId 并添加到参数
        $namespaceId = $this->client->getNamespaceIdForApi();
        if ($namespaceId && $namespaceId !== 'public') {
            $params['namespaceId'] = $namespaceId;
        }

        try {
            $result = $this->client->put($this->getApiPath('beat'), $params);
            return is_array($result) ? true : ($result === 'ok');
        } catch (NacosException $e) {
            // 心跳失败可能是因为没有正确格式，尝试用 JSON 格式的 beat 参数
            $beat = [
                'serviceName' => $serviceName,
                'ip' => $ip,
                'port' => $port,
                'weight' => 1,
                'healthy' => true,
            ];
            if ($group && $group !== 'DEFAULT_GROUP') {
                $beat['groupName'] = $group;
            }
            $params['beat'] = json_encode($beat);

            try {
                $result = $this->client->put($this->getApiPath('beat'), $params);
                return is_array($result) ? true : ($result === 'ok');
            } catch (NacosException $e2) {
                // 如果还是失败，尝试不带 group 和 namespace 的最基本请求
                $simpleParams = [
                    'serviceName' => $serviceName,
                    'ip' => $ip,
                    'port' => $port,
                ];
                $result = $this->client->put($this->getApiPath('beat'), $simpleParams);
                return is_array($result) ? true : ($result === 'ok');
            }
        }
    }
}
