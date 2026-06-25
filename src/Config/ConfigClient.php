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
            // Nacos 3.x uses v2 API paths
            switch ($api) {
                case 'configs':
                    return '/nacos/v2/cs/config';
                case 'listener':
                    return '/nacos/v2/cs/config/listener';
                default:
                    return '/nacos/v2/cs/' . $api;
            }
        } else {
            // Nacos 2.x uses v1 API paths
            switch ($api) {
                case 'configs':
                    return '/nacos/v1/cs/configs';
                case 'listener':
                    return '/nacos/v1/cs/configs/listener';
                default:
                    return '/nacos/v1/cs/' . $api;
            }
        }
    }

    /**
     * 构建带条件namespaceId的参数数组
     * Nacos的public命名空间ID为空字符串，不需要传namespaceId参数
     * @param array $baseParams
     * @return array
     */
    private function withNamespaceId(array $baseParams): array
    {
        $namespaceId = $this->client->getNamespaceIdForApi();
        if ($namespaceId !== '') {
            $baseParams['namespaceId'] = $namespaceId;
        }
        return $baseParams;
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
                $this->client->getLogger()->debug('[gRPC] getConfig succeeded', ['dataId' => $dataId, 'group' => $group]);
                // gRPC 响应中配置内容在 'content' 字段，兼容 'data' 字段
                if (is_array($result)) {
                    return $result['content'] ?? $result['data'] ?? '';
                }
                return '';
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->debug('[gRPC->HTTP] getConfig failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $this->client->getLogger()->debug('[HTTP] getConfig', ['dataId' => $dataId, 'group' => $group]);
        $params = $this->withNamespaceId([
            'dataId' => $dataId,
            'group' => $group,
        ]);

        try {
            // 使用getRaw获取原始响应体，避免JSON格式的配置内容被错误解析为数组
            $result = $this->client->getRaw($this->getApiPath('configs'), $params);
            // Nacos v2 API的JSON响应已在requestRaw中处理，直接返回
            // Nacos v1 API直接返回配置内容字符串
            // 两种情况都直接返回原始字符串
            return $result;
        } catch (NacosException $e) {
            $msg = $e->getMessage();
            // HTTP 404 或 gRPC "config data not exist" 都视为配置不存在
            if (strpos($msg, '404') !== false || stripos($msg, 'not exist') !== false) {
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
                $result = $this->grpcClient->publishConfig($dataId, $group, $content, $type);
                $this->client->getLogger()->debug('[gRPC] publishConfig succeeded', ['dataId' => $dataId, 'group' => $group]);
                return $result;
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->debug('[gRPC->HTTP] publishConfig failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $this->client->getLogger()->debug('[HTTP] publishConfig', ['dataId' => $dataId, 'group' => $group]);
        $params = $this->withNamespaceId([
            'dataId' => $dataId,
            'group' => $group,
            'content' => $content,
        ]);

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
                $result = $this->grpcClient->deleteConfig($dataId, $group);
                $this->client->getLogger()->debug('[gRPC] deleteConfig succeeded', ['dataId' => $dataId, 'group' => $group]);
                return $result;
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->debug('[gRPC->HTTP] deleteConfig failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $this->client->getLogger()->debug('[HTTP] deleteConfig', ['dataId' => $dataId, 'group' => $group]);
        $params = $this->withNamespaceId([
            'dataId' => $dataId,
            'group' => $group,
        ]);

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
                $this->client->getLogger()->debug('[gRPC] listenConfig succeeded', ['dataId' => $dataId, 'group' => $group]);
                return;
            } catch (NacosException $e) {
                // gRPC失败时回退到HTTP
                $this->client->getLogger()->debug('[gRPC->HTTP] listenConfig failed, fallback to HTTP', ['exception' => $e->getMessage()]);
            }
        }

        $this->client->getLogger()->debug('[HTTP] listenConfig', ['dataId' => $dataId, 'group' => $group]);
        $currentContent = $this->getConfig($dataId, $group);
        $md5 = md5($currentContent);
        $tenant = $this->client->getNamespaceIdForApi();
        
        // 使用正确的分隔符：%02 (STX) 用于字段分隔，%01 (SOH) 用于多个配置分隔
        $listeningConfigs = $dataId . chr(2) . $group . chr(2) . $md5 . chr(2) . $tenant . chr(1);
        
        $params = [
            'Listening-Configs' => $listeningConfigs,
        ];

        $result = $this->client->post($this->getApiPath('listener'), $params);
        if (!empty($result)) {
            call_user_func($callback, $result);
        }
    }
}