<?php

namespace Nacos\Webman;

/**
 * Webman 插件自动安装类
 *
 * 当通过 composer require 安装到 webman 项目时，
 * 自动创建 config/plugin/nacos/ 目录下的配置文件。
 */
class Plugin
{
    /**
     * 安装时自动执行
     * 创建 webman 插件配置文件
     */
    public static function install($event): void
    {
        $installDir = static::getInstallDir();
        if ($installDir === null) {
            return;
        }

        // 创建插件配置目录
        $pluginDir = $installDir . '/config/plugin/nacos';
        if (!is_dir($pluginDir)) {
            mkdir($pluginDir, 0755, true);
        }

        // 创建 process.php（进程配置）
        $processFile = $pluginDir . '/process.php';
        if (!is_file($processFile)) {
            file_put_contents($processFile, static::getProcessConfig());
        }

        // 创建 app.php（插件主配置）
        $appFile = $pluginDir . '/app.php';
        if (!is_file($appFile)) {
            file_put_contents($appFile, static::getAppConfig());
        }

        echo "\n";
        echo "  ╔══════════════════════════════════════════╗\n";
        echo "  ║  ssh/nacos-sdk-php 已安装到 webman       ║\n";
        echo "  ║  配置文件: config/plugin/nacos/          ║\n";
        echo "  ║  请在 .env 中配置 NACOS_* 环境变量        ║\n";
        echo "  ╚══════════════════════════════════════════╝\n";
        echo "\n";
    }

    /**
     * 卸载时自动执行
     */
    public static function uninstall($event): void
    {
        // 不自动删除配置文件，避免误删用户配置
    }

    /**
     * 获取 webman 项目根目录
     */
    protected static function getInstallDir(): ?string
    {
        // 通过 composer 事件获取项目根目录
        if (defined('BASE_PATH')) {
            return constant('BASE_PATH');
        }

        // 尝试从 vendor 目录推断
        $vendorDir = dirname(__DIR__, 3);
        $projectDir = dirname($vendorDir);

        // 检查是否是 webman 项目（有 webman 配置文件）
        if (is_dir($projectDir . '/config') || is_file($projectDir . '/windows.php') || is_file($projectDir . '/start.php')) {
            return $projectDir;
        }

        return null;
    }

    /**
     * 进程配置模板
     */
    protected static function getProcessConfig(): string
    {
        return <<<'PHP'
<?php
/**
 * Nacos 进程配置
 *
 * 环境变量（.env）：
 * NACOS_ENABLE=1                    # 是否启用
 * NACOS_HOST=http://127.0.0.1:8848 # Nacos 服务地址
 * NACOS_NAMESPACE=public            # 命名空间
 * NACOS_GROUP=DEFAULT_GROUP         # 分组
 * NACOS_USERNAME=nacos              # 用户名（可选）
 * NACOS_PASSWORD=nacos              # 密码（可选）
 * NACOS_CONFIG_DATA_IDS=            # 需同步的配置ID，逗号分隔
 * NACOS_LOG_ENABLE=1                # 业务日志
 * NACOS_SDK_LOG_ENABLE=0            # SDK内部日志
 * NACOS_LOG_CHANNEL=default          # 日志通道
 */

$enable = getenv('NACOS_ENABLE');
if ($enable === false || !filter_var($enable, FILTER_VALIDATE_BOOLEAN)) {
    return [];
}

return [
    'nacos' => [
        'handler' => \Nacos\Webman\NacosProcess::class,
        'constructor' => [
            'host' => getenv('NACOS_HOST') ?: 'http://127.0.0.1:8848',
            'namespace' => getenv('NACOS_NAMESPACE') ?: 'public',
            'group' => getenv('NACOS_GROUP') ?: 'DEFAULT_GROUP',
            'accessKey' => getenv('NACOS_ACCESS_KEY') ?: '',
            'secretKey' => getenv('NACOS_SECRET_KEY') ?: '',
            'serverName' => getenv('APP_NAME') ?: 'webman-app',
            'username' => getenv('NACOS_USERNAME') ?: '',
            'password' => getenv('NACOS_PASSWORD') ?: '',
            'logEnable' => getenv('NACOS_LOG_ENABLE') ?: true,
            'sdkLogEnable' => getenv('NACOS_SDK_LOG_ENABLE') ?: false,
            'configDataIds' => getenv('NACOS_CONFIG_DATA_IDS') ?: '',
        ],
    ],
];
PHP;
    }

    /**
     * 插件主配置模板
     */
    protected static function getAppConfig(): string
    {
        return <<<'PHP'
<?php
/**
 * Nacos 插件配置
 *
 * 启用后可在中间件中使用 NacosReadyMiddleware 检查配置就绪状态
 */
return [
    'enable' => filter_var(getenv('NACOS_ENABLE'), FILTER_VALIDATE_BOOLEAN),
];
PHP;
    }
}
