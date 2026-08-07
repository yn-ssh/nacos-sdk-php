<?php

namespace Nacos\Webman;

use Nacos\Nacos;
use Nacos\Utils\FeignClient;
use Psr\Log\NullLogger;
use support\Log;

/**
 * Nacos 服务封装（Webman 环境）
 * 提供配置管理、服务发现、Feign 调用等能力
 * 可在控制器中通过依赖注入或容器使用
 */
class NacosService
{
    private static ?Nacos $nacos = null;

    /**
     * 获取 Nacos SDK 实例（懒加载单例）
     */
    public static function getNacos(): Nacos
    {
        if (self::$nacos === null) {
            $sdkLogEnable = filter_var(getenv('NACOS_SDK_LOG_ENABLE'), FILTER_VALIDATE_BOOLEAN);
            $logger = $sdkLogEnable ? Log::channel(getenv('NACOS_LOG_CHANNEL') ?: 'default') : new NullLogger();

            self::$nacos = new Nacos(
                getenv('NACOS_HOST') ?: 'http://127.0.0.1:8848',
                getenv('NACOS_NAMESPACE') ?: 'public',
                getenv('NACOS_ACCESS_KEY') ?: '',
                getenv('NACOS_SECRET_KEY') ?: '',
                $logger,
                getenv('NACOS_USERNAME') ?: '',
                getenv('NACOS_PASSWORD') ?: ''
            );
        }
        return self::$nacos;
    }

    // ==================== 配置管理 ====================

    /**
     * 读取 Nacos 配置（实时获取）
     */
    public static function getConfig(string $dataId, string $group = 'DEFAULT_GROUP'): string
    {
        return self::getNacos()->config()->getConfig($dataId, $group);
    }

    /**
     * 从本地缓存文件读取 Nacos 配置（由 NacosProcess 同步，更快）
     */
    public static function getConfigFromCache(string $dataId, string $group = 'DEFAULT_GROUP'): array
    {
        $file = runtime_path() . '/nacos_cache/' . $dataId . '.php';
        if (is_file($file)) {
            return include $file;
        }
        return [];
    }

    /**
     * 发布配置
     */
    public static function publishConfig(string $dataId, string $group, string $content, string $type = 'text'): bool
    {
        return self::getNacos()->config()->publishConfig($dataId, $group, $content, $type);
    }

    /**
     * 删除配置
     */
    public static function deleteConfig(string $dataId, string $group): bool
    {
        return self::getNacos()->config()->deleteConfig($dataId, $group);
    }

    // ==================== 服务发现 ====================

    /**
     * 获取服务所有实例
     */
    public static function getAllInstances(string $serviceName, string $group = 'DEFAULT_GROUP'): array
    {
        return self::getNacos()->discovery()->getAllInstances($serviceName, $group);
    }

    /**
     * 获取一个健康实例
     */
    public static function getHealthyInstance(string $serviceName, string $group = 'DEFAULT_GROUP'): ?array
    {
        return self::getNacos()->discovery()->selectOneHealthyInstance($serviceName, $group);
    }

    // ==================== Feign 声明式调用 ====================

    /**
     * 创建 Feign 客户端
     */
    public static function feign(string $serviceName, string $group = 'DEFAULT_GROUP'): FeignClient
    {
        return self::getNacos()->feign($serviceName, $group);
    }

    /**
     * 快捷 GET 调用远程服务
     */
    public static function get(string $serviceName, string $path, array $params = [], string $group = 'DEFAULT_GROUP'): array
    {
        return self::feign($serviceName, $group)->get($path, $params);
    }

    /**
     * 快捷 POST 调用远程服务
     */
    public static function post(string $serviceName, string $path, array $data = [], string $group = 'DEFAULT_GROUP'): array
    {
        return self::feign($serviceName, $group)->post($path, $data);
    }
}
