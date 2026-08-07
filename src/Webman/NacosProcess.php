<?php

namespace Nacos\Webman;

use Nacos\Nacos;
use Psr\Log\NullLogger;
use support\Log;
use Workerman\Timer;

/**
 * Nacos 服务注册、心跳与配置管理进程
 *
 * 配置缓存策略：将 Nacos 配置写入本地 PHP 文件（runtime/nacos_cache/），
 * Worker 进程通过 include 读取，无需 Redis 中转，消除启动依赖。
 *
 * 环境变量配置：
 * - NACOS_ENABLE          是否启用（默认 false）
 * - NACOS_HOST            Nacos 服务地址（默认 http://127.0.0.1:8848）
 * - NACOS_NAMESPACE       命名空间（默认 public）
 * - NACOS_GROUP           分组（默认 DEFAULT_GROUP）
 * - NACOS_ACCESS_KEY      AK（可选）
 * - NACOS_SECRET_KEY      SK（可选）
 * - NACOS_USERNAME        用户名（可选）
 * - NACOS_PASSWORD        密码（可选）
 * - NACOS_CONFIG_DATA_IDS 需要同步的配置 dataId，逗号分隔
 * - NACOS_LOG_ENABLE      是否记录业务日志（默认 true）
 * - NACOS_SDK_LOG_ENABLE  是否记录 SDK 内部日志（默认 false）
 * - NACOS_LOG_CHANNEL     日志通道名称（默认 default）
 */
class NacosProcess
{
    private string $host;
    private string $namespace;
    private string $group;
    private string $accessKey;
    private string $secretKey;
    private string $serverName;
    private string $serverIP;
    private int $serverPort;
    private string $username;
    private string $password;
    private bool $logEnable;
    private bool $sdkLogEnable;
    private string $configDataIds;

    /** Nacos 配置本地缓存目录 */
    private string $cacheDir;

    /** @var int|null 心跳定时器 ID */
    private ?int $heartbeatTimerId = null;

    /** @var int|null 配置轮询定时器 ID */
    private ?int $pollingTimerId = null;

    public function __construct(
        string $host = '',
        string $namespace = '',
        string $group = '',
        string $accessKey = '',
        string $secretKey = '',
        string $serverName = '',
        string $serverIP = '',
        int|string $serverPort = '',
        string $username = '',
        string $password = '',
        bool|string $logEnable = true,
        bool|string $sdkLogEnable = false,
        string $configDataIds = ''
    ) {
        $this->host = $host ?: (getenv('NACOS_HOST') ?: 'http://127.0.0.1:8848');
        $this->namespace = $namespace ?: (getenv('NACOS_NAMESPACE') ?: 'public');
        $this->group = $group ?: (getenv('NACOS_GROUP') ?: 'DEFAULT_GROUP');
        $this->accessKey = $accessKey ?: (getenv('NACOS_ACCESS_KEY') ?: '');
        $this->secretKey = $secretKey ?: (getenv('NACOS_SECRET_KEY') ?: '');
        $this->serverName = $serverName;
        $this->serverIP = $serverIP;
        $this->serverPort = $serverPort === '' ? 0 : (int)$serverPort;
        $this->username = $username ?: (getenv('NACOS_USERNAME') ?: '');
        $this->password = $password ?: (getenv('NACOS_PASSWORD') ?: '');
        $this->logEnable = filter_var($logEnable, FILTER_VALIDATE_BOOLEAN);
        $this->sdkLogEnable = filter_var($sdkLogEnable, FILTER_VALIDATE_BOOLEAN);
        $this->configDataIds = $configDataIds ?: (getenv('NACOS_CONFIG_DATA_IDS') ?: '');
        $this->cacheDir = runtime_path() . '/nacos_cache';

        // 自动检测未配置的服务信息
        if ($this->serverName === '') {
            $this->serverName = getenv('APP_NAME') ?: 'webman-app';
        }
        if ($this->serverIP === '') {
            $this->serverIP = $this->detectLocalIP();
        }
        if ($this->serverPort === 0) {
            $this->serverPort = $this->detectServerPort();
        }
    }

    public function onWorkerStart(): void
    {
        $logger = $this->sdkLogEnable ? Log::channel($this->getLogChannel()) : new NullLogger();

        $nacos = new Nacos(
            $this->host,
            $this->namespace,
            $this->accessKey,
            $this->secretKey,
            $logger,
            $this->username,
            $this->password
        );

        $serverName = $this->serverName;
        $group = $this->group;
        $ip = $this->serverIP;
        $port = $this->serverPort;
        $logEnable = $this->logEnable;

        $this->log('info', 'Nacos process started', [
            'serverName' => $serverName,
            'host' => $this->host,
            'namespace' => $this->namespace,
        ], $logEnable);

        // ==================== 配置管理（优先初始化）====================
        $configCache = $this->initConfigSync($nacos, $group, $logEnable);

        // ==================== 服务注册与心跳 ====================
        $this->heartbeatTimerId = Timer::add(5, function () use ($nacos, $serverName, $group, $ip, $port, $logEnable) {
            try {
                $nacos->discovery()->registerInstance($serverName, $ip, $port, $group);
                $this->log('info', 'Nacos heartbeat', ['service' => $serverName, 'ip' => $ip, 'port' => $port], $logEnable);
            } catch (\Exception $e) {
                $this->log('error', 'Nacos register failed', ['service' => $serverName, 'exception' => $e->getMessage()]);
            }
        });

        // ==================== 配置变更监听（定时轮询）====================
        $this->pollingTimerId = $this->startConfigPolling($nacos, $group, $logEnable, $configCache);
    }

    /**
     * 优雅关闭：停止时取消定时器
     */
    public function onWorkerStop(): void
    {
        if ($this->heartbeatTimerId !== null) {
            Timer::del($this->heartbeatTimerId);
        }
        if ($this->pollingTimerId !== null) {
            Timer::del($this->pollingTimerId);
        }
    }

    /**
     * 启动时同步拉取配置并写入缓存文件
     */
    private function initConfigSync(Nacos $nacos, string $group, bool $logEnable): array
    {
        if (empty($this->configDataIds)) {
            return [];
        }

        $dataIds = array_filter(array_map('trim', explode(',', $this->configDataIds)));
        if (empty($dataIds)) {
            return [];
        }

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        $configCache = [];
        foreach ($dataIds as $dataId) {
            try {
                $content = $nacos->config()->getConfig($dataId, $group);
                $configCache[$dataId] = $content;
                $this->writeCacheFile($dataId, $content);

                $status = $content !== '' ? 'loaded' : 'empty';
                $this->log('info', 'Nacos config ' . $status, ['dataId' => $dataId, 'group' => $group], $logEnable);
            } catch (\Exception $e) {
                $configCache[$dataId] = '';
                $this->log('error', 'Nacos config load failed', ['dataId' => $dataId, 'exception' => $e->getMessage()]);
            }
        }

        return $configCache;
    }

    /**
     * 启动配置变更定时轮询（每 2 秒）
     */
    private function startConfigPolling(Nacos $nacos, string $group, bool $logEnable, array &$configCache): ?int
    {
        if (empty($this->configDataIds)) {
            return null;
        }

        $dataIds = array_filter(array_map('trim', explode(',', $this->configDataIds)));
        if (empty($dataIds)) {
            return null;
        }

        return Timer::add(2, function () use ($nacos, $group, $dataIds, $logEnable, &$configCache) {
            foreach ($dataIds as $dataId) {
                try {
                    $newContent = $nacos->config()->getConfig($dataId, $group);
                    $oldContent = $configCache[$dataId] ?? '';

                    if ($newContent !== $oldContent) {
                        $this->writeCacheFile($dataId, $newContent);
                        $configCache[$dataId] = $newContent;
                        $this->log('info', 'Nacos config changed', ['dataId' => $dataId, 'group' => $group], $logEnable);
                    }
                } catch (\Exception $e) {
                    $this->log('error', 'Nacos config poll failed', ['dataId' => $dataId, 'exception' => $e->getMessage()]);
                }
            }
        });
    }

    /**
     * 将 Nacos 配置写入本地 PHP 缓存文件
     */
    private function writeCacheFile(string $dataId, string $jsonContent): void
    {
        $file = $this->cacheDir . '/' . $dataId . '.php';
        $data = json_decode($jsonContent, true);
        if (!is_array($data)) {
            $data = [];
        }
        $phpCode = "<?php\n// Nacos config cache: {$dataId}\n// Generated: " . date('Y-m-d H:i:s') . "\nreturn " . var_export($data, true) . ";\n";
        file_put_contents($file, $phpCode, LOCK_EX);
    }

    /**
     * 自动检测本机出口 IP
     */
    private function detectLocalIP(): string
    {
        try {
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            socket_connect($socket, '8.8.8.8', 80);
            socket_getsockname($socket, $ip);
            socket_close($socket);
            if (!empty($ip) && $ip !== '0.0.0.0') {
                return $ip;
            }
        } catch (\Exception $e) {
            // fallback
        }
        return @gethostbyname(gethostname()) ?: '127.0.0.1';
    }

    /**
     * 自动从 APP_SERVER 检测服务端口
     */
    private function detectServerPort(): int
    {
        $server = getenv('APP_SERVER');
        if ($server) {
            $port = parse_url($server, PHP_URL_PORT);
            if ($port) {
                return (int)$port;
            }
        }
        return 8787;
    }

    /**
     * 统一日志输出
     * @param string $level 日志级别 (info/error/warning/debug)
     * @param string $message 日志消息
     * @param array $context 结构化上下文
     * @param bool|null $conditional 为 true 时才输出（用于可控日志），null 表示始终输出
     */
    private function log(string $level, string $message, array $context = [], ?bool $conditional = null): void
    {
        if ($conditional === false) {
            return;
        }
        Log::channel($this->getLogChannel())->$level($message, $context);
    }

    /**
     * 获取日志通道名称
     */
    private function getLogChannel(): string
    {
        return getenv('NACOS_LOG_CHANNEL') ?: 'default';
    }
}
