<?php

namespace Nacos\Config;

use Nacos\Client\NacosClient;
use Nacos\Client\NacosGrpcClient;
use Nacos\Exception\NacosException;

class ConfigClient
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
     * ConfigClient constructor.
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
                case 'configs':
                    return '/nacos/v2/cs/config';
                case 'listener':
                    return '/nacos/v1/cs/configs/listener';
                default:
                    return '/nacos/v2/cs/' . $api;
            }
        } else {
            // Nacos 2.x API路径
            switch ($api) {
                case 'configs':
                    return '/nacos/v2/cs/config';
                case 'listener':
                    return '/nacos/v1/cs/configs/listener';
                default:
                    return '/nacos/v2/cs/' . $api;
            }
        }
    }

    /**
     * Get configuration
     * @param string $dataId
     * @param string $group
     * @return string
     * @throws NacosException
     */
    public function getConfig(string $dataId, string $group = 'DEFAULT_GROUP'): string
    {
        // 优先使用gRPC客户端
        if ($this->grpcClient && $this->grpcClient->isGrpcAvailable()) {
            try {
                $result = $this->grpcClient->getConfig($dataId, $group);
                return is_array($result) && isset($result['data']) ? $result['data'] : '';
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->warning('gRPC getConfig failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $params = [
            'dataId' => $dataId,
            'group' => $group,
            'namespaceId' => $this->client->getNamespaceId(),
        ];

        try {
            $result = $this->client->get($this->getApiPath('configs'), $params);
            // Nacos v2 API返回JSON格式，包含data字段
            if (is_array($result) && isset($result['data'])) {
                return $result['data'];
            }
            // Nacos v1 API直接返回配置内容字符串
            return is_string($result) ? $result : ($result['content'] ?? '');
        } catch (NacosException $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                return '';
            }
            throw $e;
        }
    }

    /**
     * Publish configuration
     * @param string $dataId
     * @param string $group
     * @param string $content
     * @param string $type
     * @return bool
     * @throws NacosException
     */
    public function publishConfig(string $dataId, string $group, string $content, string $type = 'text'): bool
    {
        // 优先使用gRPC客户端
        if ($this->grpcClient && $this->grpcClient->isGrpcAvailable()) {
            try {
                return $this->grpcClient->publishConfig($dataId, $group, $content, $type);
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->warning('gRPC publishConfig failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $params = [
            'dataId' => $dataId,
            'group' => $group,
            'content' => $content,
            'namespaceId' => $this->client->getNamespaceId(),
        ];

        if (!empty($type)) {
            $params['type'] = $type;
        }

        $result = $this->client->post($this->getApiPath('configs'), $params);
        // Nacos v2 API返回JSON，code=0表示成功
        if (is_array($result) && isset($result['code'])) {
            return $result['code'] === 0;
        }
        // Nacos v1 API返回'true'字符串表示成功
        return $result === 'true' || $result === true;
    }

    /**
     * Delete configuration
     * @param string $dataId
     * @param string $group
     * @return bool
     * @throws NacosException
     */
    public function deleteConfig(string $dataId, string $group): bool
    {
        // 优先使用gRPC客户端
        if ($this->grpcClient && $this->grpcClient->isGrpcAvailable()) {
            try {
                return $this->grpcClient->deleteConfig($dataId, $group);
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->warning('gRPC deleteConfig failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $params = [
            'dataId' => $dataId,
            'group' => $group,
            'namespaceId' => $this->client->getNamespaceId(),
        ];

        $result = $this->client->delete($this->getApiPath('configs'), $params);
        // Nacos v2 API返回JSON，code=0表示成功
        if (is_array($result) && isset($result['code'])) {
            return $result['code'] === 0;
        }
        // Nacos v1 API返回'true'字符串表示成功
        return $result === 'true' || $result === true;
    }

    /**
     * Listen for configuration changes
     * @param string $dataId
     * @param string $group
     * @param callable $callback
     * @param int $timeout
     * @throws NacosException
     */
    public function listenConfig(string $dataId, string $group, callable $callback, int $timeout = 30): void
    {
        // 优先使用gRPC客户端
        if ($this->grpcClient && $this->grpcClient->isGrpcAvailable()) {
            try {
                $this->grpcClient->listenConfig([
                    [
                        'dataId' => $dataId,
                        'group' => $group
                    ]
                ], $callback);
                return;
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->warning('gRPC listenConfig failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $currentContent = $this->getConfig($dataId, $group);
        $md5 = md5($currentContent);
        $tenant = $this->client->getNamespaceId();
        
        // 使用正确的分隔符：%02 (STX) 用于字段分隔，%01 (SOH) 用于多个配置分隔
        $listeningConfigs = $dataId . chr(2) . $group . chr(2) . $md5 . chr(2) . $tenant . chr(1);
        
        $params = [
            'Listening-Configs' => $listeningConfigs,
        ];

        $result = $this->client->post('/nacos/v1/cs/configs/listener', $params);
        if (!empty($result)) {
            call_user_func($callback, $result);
        }
    }
}