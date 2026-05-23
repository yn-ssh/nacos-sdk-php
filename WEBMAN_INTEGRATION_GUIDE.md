# Nacos SDK PHP + Webman 完整使用指南

## 目录

1. [环境要求](#环境要求)
2. [安装配置](#安装配置)
3. [基础配置](#基础配置)
4. [配置管理](#配置管理)
5. [服务发现](#服务发现)
6. [服务调用](#服务调用)
7. [Feign 客户端](#feign-客户端)
8. [认证配置](#认证配置)
9. [最佳实践](#最佳实践)
10. [完整示例](#完整示例)

---

## 环境要求

### 系统要求
- PHP >= 7.2（推荐 PHP 8.0+）
- Swoole / Workerman
- Composer
- Nacos Server 2.0+

### 推荐的 webman 环境
```bash
# PHP 8.1+ with Swoole
PHP_VERSION=8.1
SWOOLE_VERSION=5.0

# 或使用 Workerman
WORKERMAN_VERSION=5.0
```

### Nacos Server
```bash
# 启动 Nacos（单机模式）
sh startup.sh -m standalone

# 或使用 Docker
docker run --name nacos -d -p 8848:8848 -p 9848:9848 nacos/nacos-server:2.0.4
```

---

## 安装配置

### 1. 安装 webman（如未安装）

```bash
# 创建 webman 项目
composer create-project workerman/webman

# 进入项目目录
cd webman
```

### 2. 安装 Nacos SDK

```bash
# 在 webman 项目目录安装
composer require ssh/nacos-sdk-php

# 或指定版本
composer require ssh/nacos-sdk-php:^1.0
```

### 3. 目录结构

```
webman/
├── config/
│   ├── app.php              # 应用配置
│   ├── container.php        # 容器配置
│   └── nacos.php            # ⭐ Nacos 配置（需创建）
├── app/
│   ├── controller/
│   │   └── Index.php
│   ├── service/             # ⭐ 业务服务层
│   │   ├── ConfigService.php
│   │   └── UserService.php
│   └── bootstrap/           # ⭐ 启动引导（需创建）
│       └── nacos.php
├── support/
│   └── Request.php
├── vendor/
│   └── ssh/nacos-sdk-php   # SDK 已安装
└── composer.json
```

---

## 基础配置

### 方式一：独立配置文件（推荐）

#### 1. 创建 Nacos 配置文件

创建 `config/nacos.php`:

```php
<?php

return [
    // Nacos 服务器地址
    'server_url' => env('NACOS_SERVER_URL', 'http://127.0.0.1:8848'),
    
    // 命名空间（public 或自定义 ID）
    'namespace_id' => env('NACOS_NAMESPACE', 'public'),
    
    // 认证配置（生产环境务必配置）
    'username' => env('NACOS_USERNAME', 'nacos'),
    'password' => env('NACOS_PASSWORD', 'nacos'),
    
    // AK/SK 认证（阿里云/MSE 使用，与用户名密码二选一）
    'access_key' => env('NACOS_ACCESS_KEY', ''),
    'secret_key' => env('NACOS_SECRET_KEY', ''),
    
    // gRPC 配置（Nacos 2.x）
    'grpc_port' => env('NACOS_GRPC_PORT', 9848),
    
    // 客户端配置
    'client' => [
        'timeout' => 10,
        'connect_timeout' => 5,
    ],
    
    // 服务发现配置
    'discovery' => [
        'cache_ttl' => 30,           // 实例缓存时间（秒）
        'retry_count' => 3,          // 重试次数
        'retry_delay' => 500,        // 重试延迟（毫秒）
    ],
    
    // 配置监听配置
    'config' => [
        'listen_timeout' => 30,      // 监听超时时间（秒）
        'listen_interval' => 1000,   // 监听轮询间隔（毫秒）
    ],
    
    // 是否启用自动回退
    // gRPC 不可用时自动使用 HTTP
    'grpc_fallback' => true,
];
```

#### 2. 创建环境变量文件 `.env`

```bash
# .env 文件
NACOS_SERVER_URL=http://127.0.0.1:8848
NACOS_NAMESPACE=public
NACOS_USERNAME=nacos
NACOS_PASSWORD=nacos
NACOS_GRPC_PORT=9848

# 生产环境建议使用更安全的配置
# NACOS_USERNAME=your_prod_user
# NACOS_PASSWORD=your_secure_password
```

### 方式二：Bootstrap 引导初始化（推荐用于全局单例）

#### 创建引导文件 `app/bootstrap/nacos.php`

```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Nacos\Nacos;
use Webman\Bootstrap;

/**
 * Nacos 客户端引导类
 * 
 * 在应用启动时初始化 Nacos 客户端
 * 确保全局只有一个实例
 */
class NacosBootstrap implements Bootstrap
{
    /**
     * @var Nacos|null
     */
    protected static $nacos = null;

    /**
     * 启动引导
     * 
     * @param \Workerman\Worker $worker
     */
    public static function start($worker)
    {
        // 仅在主进程初始化
        if ($worker->id === 0) {
            self::initNacosClient();
        }
    }

    /**
     * 初始化 Nacos 客户端
     */
    protected static function initNacosClient()
    {
        $config = config('nacos');
        
        static::$nacos = new Nacos(
            $config['server_url'],
            $config['namespace_id'],
            $config['access_key'] ?? '',
            $config['secret_key'] ?? '',
            $config['grpc_port'],
            null,
            $config['username'] ?? '',
            $config['password'] ?? ''
        );
        
        echo "[Nacos] Client initialized: {$config['server_url']}\n";
    }

    /**
     * 获取 Nacos 客户端实例
     * 
     * @return Nacos
     */
    public static function getClient(): Nacos
    {
        if (static::$nacos === null) {
            self::initNacosClient();
        }
        
        return static::$nacos;
    }
}
```

#### 在 `config/bootstrap.php` 中注册

```php
<?php

return [
    // ... 其他引导 ...
    
    // Nacos 客户端
    app\bootstrap\NacosBootstrap::class,
];
```

---

## 配置管理

### 创建配置服务类

创建 `app/service/NacosConfigService.php`:

```php
<?php

namespace app\service;

use Nacos\Nacos;
use app\bootstrap\NacosBootstrap;

/**
 * Nacos 配置服务
 * 
 * 封装配置管理的常用操作
 * 支持配置监听和自动刷新
 */
class NacosConfigService
{
    /**
     * @var Nacos
     */
    protected $nacos;

    /**
     * 本地配置缓存
     */
    protected $configCache = [];

    /**
     * 监听器回调
     */
    protected $listeners = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->nacos = NacosBootstrap::getClient();
    }

    /**
     * 获取配置
     * 
     * @param string $dataId 配置ID
     * @param string $group 分组（默认 DEFAULT_GROUP）
     * @return string
     * 
     * @example
     * ```php
     * $content = $this->get('database.php');
     * $dbConfig = include $content; // 解析为 PHP 配置
     * ```
     */
    public function get(string $dataId, string $group = 'DEFAULT_GROUP'): string
    {
        $cacheKey = "{$group}:{$dataId}";
        
        return $this->nacos->config()->getConfig($dataId, $group);
    }

    /**
     * 获取配置并解析为数组
     * 
     * @param string $dataId 配置ID
     * @param string $group 分组
     * @param bool $assoc 是否返回关联数组
     * @return array
     * 
     * @example
     * ```php
     * // JSON 格式配置
     * $config = $this->getArray('app-config.json', 'DEFAULT_GROUP');
     * 
     * // YAML 格式配置
     * $config = $this->getArray('app-config.yaml', 'DEFAULT_GROUP');
     * ```
     */
    public function getArray(string $dataId, string $group = 'DEFAULT_GROUP', bool $assoc = true): array
    {
        $content = $this->get($dataId, $group);
        
        if (empty($content)) {
            return [];
        }

        // 根据文件扩展名自动识别格式
        $ext = pathinfo($dataId, PATHINFO_EXTENSION);
        
        return match (strtolower($ext)) {
            'json' => json_decode($content, $assoc) ?: [],
            'yaml', 'yml' => $this->parseYaml($content),
            'ini' => parse_ini_string($content, $assoc),
            'xml' => $this->parseXml($content),
            default => json_decode($content, $assoc) ?: [],
        };
    }

    /**
     * 发布配置
     * 
     * @param string $dataId 配置ID
     * @param string $group 分组
     * @param string $content 配置内容
     * @param string $type 配置类型（text, json, yaml, xml, html, properties）
     * @return bool
     * 
     * @example
     * ```php
     * // 发布文本配置
     * $this->publish('welcome.txt', 'DEFAULT_GROUP', 'Hello World');
     * 
     * // 发布 JSON 配置
     * $json = json_encode(['name' => 'app', 'version' => '1.0']);
     * $this->publish('app.json', 'DEFAULT_GROUP', $json, 'json');
     * ```
     */
    public function publish(string $dataId, string $group, string $content, string $type = 'text'): bool
    {
        return $this->nacos->config()->publishConfig($dataId, $group, $content, $type);
    }

    /**
     * 发布数组配置（自动序列化为 JSON）
     * 
     * @param string $dataId 配置ID
     * @param string $group 分组
     * @param array $data 配置数据
     * @param string $type 配置类型
     * @return bool
     */
    public function publishArray(string $dataId, string $group, array $data, string $type = 'json'): bool
    {
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return $this->publish($dataId, $group, $content, $type);
    }

    /**
     * 删除配置
     * 
     * @param string $dataId 配置ID
     * @param string $group 分组
     * @return bool
     */
    public function delete(string $dataId, string $group = 'DEFAULT_GROUP'): bool
    {
        return $this->nacos->config()->deleteConfig($dataId, $group);
    }

    /**
     * 监听配置变更
     * 
     * @param string $dataId 配置ID
     * @param string $group 分组
     * @param callable $callback 配置变更回调
     * @param int $timeout 超时时间（秒）
     * 
     * @example
     * ```php
     * $this->listen('database.php', 'DEFAULT_GROUP', function($changedConfig) {
     *     echo "配置已变更: {$changedConfig}\n";
     *     
     *     // 清除本地缓存
     *     $this->configCache = [];
     *     
     *     // 重新加载配置
     *     $this->reloadConfig();
     * });
     * ```
     */
    public function listen(string $dataId, string $group, callable $callback, int $timeout = 30): void
    {
        $this->listeners["{$group}:{$dataId}"] = $callback;
        
        $this->nacos->config()->listenConfig($dataId, $group, function($data) use ($dataId, $group) {
            $key = "{$group}:{$dataId}";
            
            if (isset($this->listeners[$key])) {
                call_user_func($this->listeners[$key], $data);
            }
            
            // 清除缓存
            unset($this->configCache[$key]);
        }, $timeout);
    }

    /**
     * 获取多配置（批量）
     * 
     * @param array $configs 配置列表 [['dataId' => 'xxx', 'group' => 'xxx'], ...]
     * @return array
     */
    public function getMultiple(array $configs): array
    {
        $results = [];
        
        foreach ($configs as $config) {
            $dataId = $config['dataId'] ?? '';
            $group = $config['group'] ?? 'DEFAULT_GROUP';
            
            if ($dataId) {
                $results[$group][$dataId] = $this->get($dataId, $group);
            }
        }
        
        return $results;
    }

    /**
     * 解析 YAML（简化版，需要安装 symfony/yaml）
     * 
     * @param string $content
     * @return array
     */
    protected function parseYaml(string $content): array
    {
        if (class_exists('\Symfony\Component\Yaml\Yaml')) {
            return \Symfony\Component\Yaml\Yaml::parse($content) ?: [];
        }
        
        // 简单的 YAML 解析（不支持复杂语法）
        $lines = explode("\n", $content);
        $result = [];
        $currentKey = null;
        $currentIndent = 0;
        
        foreach ($lines as $line) {
            if (preg_match('/^(\s*)([^:]+):\s*(.*)$/', $line, $matches)) {
                $indent = strlen($matches[1]);
                $key = trim($matches[2]);
                $value = trim($matches[3]);
                
                if ($indent === 0) {
                    $currentKey = $key;
                    $currentIndent = 0;
                    $result[$key] = $value ?: [];
                } elseif ($indent > $currentIndent) {
                    if ($currentKey) {
                        if ($value) {
                            $result[$currentKey][$key] = $value;
                        } else {
                            $result[$currentKey][$key] = [];
                        }
                    }
                }
            }
        }
        
        return $result;
    }

    /**
     * 解析 XML
     * 
     * @param string $content
     * @return array
     */
    protected function parseXml(string $content): array
    {
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        return json_decode(json_encode($xml), true) ?: [];
    }
}
```

### 使用示例

#### 在控制器中使用

创建 `app/controller/ConfigController.php`:

```php
<?php

namespace app\controller;

use app\service\NacosConfigService;
use support\Request;
use support\Response;

class ConfigController
{
    /**
     * @var NacosConfigService
     */
    protected $configService;

    /**
     * 构造函数 - 依赖注入
     */
    public function __construct()
    {
        $this->configService = new NacosConfigService();
    }

    /**
     * 获取配置
     * 
     * GET /config/get?dataId=database.php&group=DEFAULT_GROUP
     */
    public function get(Request $request): Response
    {
        $dataId = $request->get('dataId', '');
        $group = $request->get('group', 'DEFAULT_GROUP');
        
        if (empty($dataId)) {
            return json(['code' => 400, 'msg' => 'dataId is required']);
        }
        
        try {
            $content = $this->configService->get($dataId, $group);
            
            return json([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'dataId' => $dataId,
                    'group' => $group,
                    'content' => $content,
                ],
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 获取配置（数组格式）
     * 
     * GET /config/get-json?dataId=app-config.json
     */
    public function getJson(Request $request): Response
    {
        $dataId = $request->get('dataId', '');
        $group = $request->get('group', 'DEFAULT_GROUP');
        
        try {
            $config = $this->configService->getArray($dataId, $group);
            
            return json([
                'code' => 0,
                'msg' => 'success',
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 发布配置
     * 
     * POST /config/publish
     * Body: { "dataId": "test.php", "group": "DEFAULT_GROUP", "content": "Hello World" }
     */
    public function publish(Request $request): Response
    {
        $data = $request->post();
        
        $dataId = $data['dataId'] ?? '';
        $group = $data['group'] ?? 'DEFAULT_GROUP';
        $content = $data['content'] ?? '';
        $type = $data['type'] ?? 'text';
        
        if (empty($dataId) || empty($content)) {
            return json(['code' => 400, 'msg' => 'dataId and content are required']);
        }
        
        try {
            $result = $this->configService->publish($dataId, $group, $content, $type);
            
            return json([
                'code' => $result ? 0 : 1,
                'msg' => $result ? 'success' : 'failed',
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 删除配置
     * 
     * DELETE /config/delete?dataId=test.php&group=DEFAULT_GROUP
     */
    public function delete(Request $request): Response
    {
        $dataId = $request->get('dataId', '');
        $group = $request->get('group', 'DEFAULT_GROUP');
        
        if (empty($dataId)) {
            return json(['code' => 400, 'msg' => 'dataId is required']);
        }
        
        try {
            $result = $this->configService->delete($dataId, $group);
            
            return json([
                'code' => $result ? 0 : 1,
                'msg' => $result ? 'success' : 'failed',
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }
}
```

#### 在业务逻辑中使用

创建 `app/service/UserService.php`:

```php
<?php

namespace app\service;

use app\service\NacosConfigService;
use PDO;
use PDOException;

/**
 * 用户服务
 * 
 * 演示如何从 Nacos 获取数据库配置
 */
class UserService
{
    /**
     * @var NacosConfigService
     */
    protected $configService;

    /**
     * @var PDO|null
     */
    protected $db;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->configService = new NacosConfigService();
        $this->initDatabase();
    }

    /**
     * 初始化数据库连接
     */
    protected function initDatabase(): void
    {
        // 从 Nacos 获取数据库配置
        $dbConfig = $this->configService->getArray('database.json', 'DEFAULT_GROUP');
        
        if (empty($dbConfig)) {
            throw new \RuntimeException('Database configuration not found in Nacos');
        }
        
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? 3306;
        $database = $dbConfig['database'] ?? 'test';
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        
        try {
            $this->db = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * 获取所有用户
     * 
     * @return array
     */
    public function getAllUsers(): array
    {
        $stmt = $this->db->query('SELECT * FROM users');
        return $stmt->fetchAll();
    }

    /**
     * 根据 ID 获取用户
     * 
     * @param int $id
     * @return array|null
     */
    public function getUserById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    /**
     * 创建用户
     * 
     * @param array $data
     * @return int
     */
    public function createUser(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password) VALUES (?, ?, ?)'
        );
        
        $stmt->execute([
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * 配置变更回调
     * 
     * 当数据库配置变更时自动重连
     */
    public static function onConfigChanged(): void
    {
        echo "[UserService] Database config changed, reconnecting...\n";
        
        // 重新初始化数据库连接
        $service = new self();
        // 可以发送通知等
    }
}
```

---

## 服务发现

### 创建服务发现服务

创建 `app/service/NacosDiscoveryService.php`:

```php
<?php

namespace app\service;

use Nacos\Nacos;
use app\bootstrap\NacosBootstrap;

/**
 * Nacos 服务发现服务
 * 
 * 封装服务注册、发现、注销等操作
 */
class NacosDiscoveryService
{
    /**
     * @var Nacos
     */
    protected $nacos;

    /**
     * 本地 IP 地址
     */
    protected $localIp;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->nacos = NacosBootstrap::getClient();
        $this->localIp = $this->getLocalIp();
    }

    /**
     * 注册服务实例
     * 
     * @param string $serviceName 服务名
     * @param int $port 端口
     * @param string $group 分组
     * @param array $metadata 元数据
     * @param int $weight 权重
     * @param bool $ephemeral 是否临时实例
     * @return bool
     * 
     * @example
     * ```php
     * // 注册 HTTP 服务
     * $this->register('user-service', 8080);
     * 
     * // 注册带元数据的服务
     * $this->register('user-service', 8080, 'DEFAULT_GROUP', [
     *     'version' => '1.0.0',
     *     'environment' => 'prod',
     *     'region' => 'cn-hangzhou',
     * ]);
     * ```
     */
    public function register(
        string $serviceName,
        int $port,
        string $group = 'DEFAULT_GROUP',
        array $metadata = [],
        int $weight = 1,
        bool $ephemeral = true
    ): bool {
        return $this->nacos->discovery()->registerInstance(
            $serviceName,
            $this->localIp,
            $port,
            $group,
            $metadata,
            $weight,
            $ephemeral
        );
    }

    /**
     * 注销服务实例
     * 
     * @param string $serviceName 服务名
     * @param int $port 端口
     * @param string $group 分组
     * @return bool
     */
    public function deregister(string $serviceName, int $port, string $group = 'DEFAULT_GROUP'): bool
    {
        return $this->nacos->discovery()->deregisterInstance(
            $serviceName,
            $this->localIp,
            $port,
            $group
        );
    }

    /**
     * 获取所有服务实例
     * 
     * @param string $serviceName 服务名
     * @param string $group 分组
     * @param bool $healthyOnly 仅返回健康的实例
     * @return array
     */
    public function getInstances(
        string $serviceName,
        string $group = 'DEFAULT_GROUP',
        bool $healthyOnly = true
    ): array {
        $result = $this->nacos->discovery()->getAllInstances($serviceName, $group, $healthyOnly);
        
        return $result['hosts'] ?? [];
    }

    /**
     * 获取一个健康实例
     * 
     * @param string $serviceName 服务名
     * @param string $group 分组
     * @return array|null
     */
    public function selectOne(string $serviceName, string $group = 'DEFAULT_GROUP'): ?array
    {
        return $this->nacos->discovery()->selectOneHealthyInstance($serviceName, $group);
    }

    /**
     * 发送心跳
     * 
     * @param string $serviceName 服务名
     * @param int $port 端口
     * @param string $group 分组
     * @return bool
     */
    public function beat(string $serviceName, int $port, string $group = 'DEFAULT_GROUP'): bool
    {
        return $this->nacos->discovery()->sendHeartbeat($serviceName, $this->localIp, $port, $group);
    }

    /**
     * 获取本地 IP 地址
     * 
     * @return string
     */
    protected function getLocalIp(): string
    {
        // 优先使用内网 IP
        $interfaces = netifaces();
        
        foreach (['eth0', 'en0', 'br0'] as $iface) {
            if (isset($interfaces[$iface])) {
                foreach ($interfaces[$iface] as $info) {
                    if ($info['addr'] ?? '') {
                        return $info['addr'];
                    }
                }
            }
        }
        
        // 降级方案：使用命令行获取
        $ip = trim(shell_exec("hostname -I | awk '{print $1}'"));
        
        return $ip ?: '127.0.0.1';
    }

    /**
     * 获取本机所有网络接口
     * 
     * @return array
     */
    protected function getNetworkInterfaces(): array
    {
        if (function_exists('netifaces')) {
            return netifaces();
        }
        
        // 跨平台兼容
        $os = strtolower(PHP_OS);
        
        if (strpos($os, 'win') === 0) {
            $output = shell_exec('ipconfig');
            preg_match_all('/IPv4 Address.*:\s*([\d.]+)/', $output, $matches);
        } else {
            $output = shell_exec('ip addr show');
            preg_match_all('/inet ([\d.]+)/', $output, $matches);
        }
        
        return $matches[1] ?? ['127.0.0.1'];
    }
}
```

### 控制器示例

创建 `app/controller/DiscoveryController.php`:

```php
<?php

namespace app\controller;

use app\service\NacosDiscoveryService;
use support\Request;
use support\Response;

class DiscoveryController
{
    /**
     * @var NacosDiscoveryService
     */
    protected $discoveryService;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->discoveryService = new NacosDiscoveryService();
    }

    /**
     * 注册服务
     * 
     * POST /discovery/register
     * Body: { "serviceName": "user-service", "port": 8080, "group": "DEFAULT_GROUP" }
     */
    public function register(Request $request): Response
    {
        $data = $request->post();
        
        $serviceName = $data['serviceName'] ?? '';
        $port = (int) ($data['port'] ?? 0);
        $group = $data['group'] ?? 'DEFAULT_GROUP';
        $metadata = $data['metadata'] ?? [];
        
        if (empty($serviceName) || $port <= 0) {
            return json(['code' => 400, 'msg' => 'serviceName and port are required']);
        }
        
        try {
            $result = $this->discoveryService->register($serviceName, $port, $group, $metadata);
            
            return json([
                'code' => $result ? 0 : 1,
                'msg' => $result ? 'success' : 'failed',
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 注销服务
     * 
     * DELETE /discovery/deregister
     */
    public function deregister(Request $request): Response
    {
        $serviceName = $request->get('serviceName', '');
        $port = (int) $request->get('port', 0);
        $group = $request->get('group', 'DEFAULT_GROUP');
        
        if (empty($serviceName) || $port <= 0) {
            return json(['code' => 400, 'msg' => 'serviceName and port are required']);
        }
        
        try {
            $result = $this->discoveryService->deregister($serviceName, $port, $group);
            
            return json([
                'code' => $result ? 0 : 1,
                'msg' => $result ? 'success' : 'failed',
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 获取服务实例列表
     * 
     * GET /discovery/instances?serviceName=user-service&group=DEFAULT_GROUP
     */
    public function instances(Request $request): Response
    {
        $serviceName = $request->get('serviceName', '');
        $group = $request->get('group', 'DEFAULT_GROUP');
        
        if (empty($serviceName)) {
            return json(['code' => 400, 'msg' => 'serviceName is required']);
        }
        
        try {
            $instances = $this->discoveryService->getInstances($serviceName, $group);
            
            return json([
                'code' => 0,
                'msg' => 'success',
                'data' => $instances,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 获取一个健康实例
     * 
     * GET /discovery/select?serviceName=user-service&group=DEFAULT_GROUP
     */
    public function select(Request $request): Response
    {
        $serviceName = $request->get('serviceName', '');
        $group = $request->get('group', 'DEFAULT_GROUP');
        
        if (empty($serviceName)) {
            return json(['code' => 400, 'msg' => 'serviceName is required']);
        }
        
        try {
            $instance = $this->discoveryService->selectOne($serviceName, $group);
            
            return json([
                'code' => $instance ? 0 : 1,
                'msg' => $instance ? 'success' : 'no instance available',
                'data' => $instance,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 发送心跳
     * 
     * PUT /discovery/beat?serviceName=user-service&port=8080
     */
    public function beat(Request $request): Response
    {
        $serviceName = $request->get('serviceName', '');
        $port = (int) $request->get('port', 0);
        $group = $request->get('group', 'DEFAULT_GROUP');
        
        if (empty($serviceName) || $port <= 0) {
            return json(['code' => 400, 'msg' => 'serviceName and port are required']);
        }
        
        try {
            $result = $this->discoveryService->beat($serviceName, $port, $group);
            
            return json([
                'code' => $result ? 0 : 1,
                'msg' => $result ? 'success' : 'failed',
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }
}
```

---

## 服务调用

### 使用 Feign 客户端调用远程服务

创建 `app/service/FeignService.php`:

```php
<?php

namespace app\service;

use Nacos\Nacos;
use app\bootstrap\NacosBootstrap;

/**
 * Feign 服务调用
 * 
 * 声明式的服务调用，类似于 Spring Cloud OpenFeign
 */
class FeignService
{
    /**
     * @var Nacos
     */
    protected $nacos;

    /**
     * Feign 客户端缓存
     */
    protected static $clients = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->nacos = NacosBootstrap::getClient();
    }

    /**
     * 获取 Feign 客户端
     * 
     * @param string $serviceName 服务名
     * @param string $group 分组
     * @return \Nacos\Utils\FeignClient
     */
    public function client(string $serviceName, string $group = 'DEFAULT_GROUP'): \Nacos\Utils\FeignClient
    {
        $key = "{$serviceName}:{$group}";
        
        if (!isset(self::$clients[$key])) {
            self::$clients[$key] = $this->nacos->feign($serviceName, $group);
        }
        
        return self::$clients[$key];
    }

    /**
     * GET 请求
     * 
     * @param string $serviceName 服务名
     * @param string $path 路径
     * @param array $params 查询参数
     * @param string $group 分组
     * @return array
     * 
     * @example
     * ```php
     * $result = $this->get('user-service', '/api/users/1');
     * if ($result['success']) {
     *     $user = $result['data'];
     * }
     * ```
     */
    public function get(string $serviceName, string $path, array $params = [], string $group = 'DEFAULT_GROUP'): array
    {
        return $this->client($serviceName, $group)->get($path, $params);
    }

    /**
     * POST 请求
     * 
     * @param string $serviceName 服务名
     * @param string $path 路径
     * @param array $data 请求数据
     * @param string $group 分组
     * @return array
     */
    public function post(string $serviceName, string $path, array $data = [], string $group = 'DEFAULT_GROUP'): array
    {
        return $this->client($serviceName, $group)->post($path, $data);
    }

    /**
     * PUT 请求
     * 
     * @param string $serviceName 服务名
     * @param string $path 路径
     * @param array $data 请求数据
     * @param string $group 分组
     * @return array
     */
    public function put(string $serviceName, string $path, array $data = [], string $group = 'DEFAULT_GROUP'): array
    {
        return $this->client($serviceName, $group)->put($path, $data);
    }

    /**
     * DELETE 请求
     * 
     * @param string $serviceName 服务名
     * @param string $path 路径
     * @param array $params 查询参数
     * @param string $group 分组
     * @return array
     */
    public function delete(string $serviceName, string $path, array $params = [], string $group = 'DEFAULT_GROUP'): array
    {
        return $this->client($serviceName, $group)->delete($path, $params);
    }

    /**
     * 通用请求
     * 
     * @param string $method HTTP 方法
     * @param string $serviceName 服务名
     * @param string $path 路径
     * @param array $data 请求数据
     * @param string $group 分组
     * @return array
     */
    public function request(string $method, string $serviceName, string $path, array $data = [], string $group = 'DEFAULT_GROUP'): array
    {
        return $this->client($serviceName, $group)->request($method, $path, $data);
    }
}
```

### 控制器示例

创建 `app/controller/FeignController.php`:

```php
<?php

namespace app\controller;

use app\service\FeignService;
use support\Request;
use support\Response;

class FeignController
{
    /**
     * @var FeignService
     */
    protected $feignService;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->feignService = new FeignService();
    }

    /**
     * GET 请求示例
     * 
     * GET /feign/get?service=user-service&path=/api/users/1
     */
    public function get(Request $request): Response
    {
        $serviceName = $request->get('service', '');
        $path = $request->get('path', '/');
        $params = $request->get();
        
        unset($params['service'], $params['path']);
        
        if (empty($serviceName) || empty($path)) {
            return json(['code' => 400, 'msg' => 'service and path are required']);
        }
        
        try {
            $result = $this->feignService->get($serviceName, $path, $params);
            
            if ($result['success']) {
                return json([
                    'code' => 0,
                    'msg' => 'success',
                    'data' => $result['data'],
                    'status' => $result['status_code'],
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => 'request failed',
                    'error' => $result['raw'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * POST 请求示例
     * 
     * POST /feign/post
     * Body: { "service": "user-service", "path": "/api/users", "data": {...} }
     */
    public function post(Request $request): Response
    {
        $body = $request->post();
        
        $serviceName = $body['service'] ?? '';
        $path = $body['path'] ?? '/';
        $data = $body['data'] ?? [];
        
        if (empty($serviceName) || empty($path)) {
            return json(['code' => 400, 'msg' => 'service and path are required']);
        }
        
        try {
            $result = $this->feignService->post($serviceName, $path, $data);
            
            if ($result['success']) {
                return json([
                    'code' => 0,
                    'msg' => 'success',
                    'data' => $result['data'],
                    'status' => $result['status_code'],
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => 'request failed',
                    'error' => $result['raw'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 调用用户服务示例
     * 
     * GET /feign/users/1
     */
    public function getUser(Request $request, int $id = 0): Response
    {
        try {
            $result = $this->feignService->get('user-service', "/api/users/{$id}");
            
            return json([
                'code' => 0,
                'msg' => 'success',
                'data' => $result['data'] ?? null,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 创建用户示例
     * 
     * POST /feign/users
     * Body: { "name": "John", "email": "john@example.com" }
     */
    public function createUser(Request $request): Response
    {
        $data = $request->post();
        
        try {
            $result = $this->feignService->post('user-service', '/api/users', $data);
            
            return json([
                'code' => 0,
                'msg' => 'success',
                'data' => $result['data'] ?? null,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }
}
```

---

## 中间件示例

### 路由中间件：服务认证

创建 `app/middleware/ServiceAuth.php`:

```php
<?php

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use Nacos\Nacos;
use app\bootstrap\NacosBootstrap;

/**
 * 服务间调用认证中间件
 * 
 * 验证服务调用的合法性
 */
class ServiceAuth implements MiddlewareInterface
{
    /**
     * @var Nacos
     */
    protected $nacos;

    /**
     * 白名单
     */
    protected $whitelist = [
        '/api/health',
        '/api/info',
        '/api/public',
    ];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->nacos = NacosBootstrap::getClient();
    }

    /**
     * 中间件处理
     * 
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function process(Request $request, callable $next): Response
    {
        $path = $request->path();
        
        // 白名单跳过认证
        if ($this->isWhitelisted($path)) {
            return $next($request);
        }
        
        // 验证服务认证
        $serviceToken = $request->header('X-Service-Token', '');
        
        if (!$this->validateToken($serviceToken)) {
            return json([
                'code' => 401,
                'msg' => 'Unauthorized: Invalid service token',
            ], 401);
        }
        
        // 将调用者服务信息注入到请求中
        $request->serviceName = $this->getServiceName($serviceToken);
        
        return $next($request);
    }

    /**
     * 验证 Token
     * 
     * @param string $token
     * @return bool
     */
    protected function validateToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        
        // Token 格式：base64(serviceName:timestamp:signature)
        $decoded = base64_decode($token);
        
        if (!$decoded) {
            return false;
        }
        
        $parts = explode(':', $decoded);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        [$serviceName, $timestamp, $signature] = $parts;
        
        // 检查时间戳（5分钟内有效）
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        
        // 验证签名（简化版）
        $expectedSignature = md5($serviceName . ':' . $timestamp . ':secret');
        
        return $signature === $expectedSignature;
    }

    /**
     * 获取服务名
     * 
     * @param string $token
     * @return string
     */
    protected function getServiceName(string $token): string
    {
        $decoded = base64_decode($token);
        $parts = explode(':', $decoded);
        
        return $parts[0] ?? 'unknown';
    }

    /**
     * 检查是否在白名单中
     * 
     * @param string $path
     * @return bool
     */
    protected function isWhitelisted(string $path): bool
    {
        foreach ($this->whitelist as $pattern) {
            if (strpos($path, $pattern) === 0) {
                return true;
            }
        }
        
        return false;
    }
}
```

### 中间件注册

在 `config/middleware.php` 中注册：

```php
<?php

return [
    'api' => [
        // 全局中间件
        app\middleware\ServiceAuth::class,
    ],
    
    // 其他中间件配置...
];
```

---

## 定时任务

### 服务心跳定时任务

创建 `app/command/HeartbeatCommand.php`:

```php
<?php

namespace app\command;

use app\service\NacosDiscoveryService;
use Workerman\Timer;
use Workerman\Worker;
use support\Command;

/**
 * 服务心跳定时任务
 * 
 * 保持服务实例活跃
 */
class HeartbeatCommand extends Command
{
    /**
     * 命令配置
     */
    protected static $description = 'Start service heartbeat to Nacos';

    /**
     * @var NacosDiscoveryService
     */
    protected $discoveryService;

    /**
     * @var array 服务配置
     */
    protected $services = [];

    /**
     * @var int 心跳间隔（秒）
     */
    protected $interval = 5;

    /**
     * 执行命令
     */
    protected function execute()
    {
        $this->output->info('Starting Nacos service heartbeat...');
        
        // 从配置或数据库读取注册的服务
        $this->services = $this->loadRegisteredServices();
        
        if (empty($this->services)) {
            $this->output->warning('No services registered, exiting.');
            return;
        }
        
        $this->discoveryService = new NacosDiscoveryService();
        
        // 启动心跳定时器
        Timer::add($this->interval, function () {
            $this->sendHeartbeats();
        });
        
        $this->output->success("Heartbeat started for " . count($this->services) . " services");
    }

    /**
     * 发送心跳
     */
    protected function sendHeartbeats(): void
    {
        foreach ($this->services as $service) {
            try {
                $result = $this->discoveryService->beat(
                    $service['name'],
                    $service['port'],
                    $service['group'] ?? 'DEFAULT_GROUP'
                );
                
                if ($result) {
                    $this->output->debug("Heartbeat sent: {$service['name']}:{$service['port']}");
                } else {
                    $this->output->error("Heartbeat failed: {$service['name']}:{$service['port']}");
                }
            } catch (\Exception $e) {
                $this->output->error("Error: " . $e->getMessage());
            }
        }
    }

    /**
     * 加载已注册的服务
     * 
     * @return array
     */
    protected function loadRegisteredServices(): array
    {
        // 从配置文件读取
        $config = config('services', []);
        
        // 或从数据库读取
        // $config = Db::table('registered_services')->select()->where('status', 1)->get();
        
        return $config;
    }
}
```

### 注册命令

在 `config/command.php` 中注册：

```php
<?php

return [
    // 心跳命令
    app\command\HeartbeatCommand::class,
    
    // 其他命令...
];
```

### 启动心跳服务

```bash
# 启动心跳服务
php webman heartbeat

# 或在后台运行
php webman heartbeat > /dev/null 2>&1 &
```

---

## 进程事件

### 应用启动事件

创建 `app/event/ProcessEvent.php`:

```php
<?php

namespace app\event;

use app\service\NacosDiscoveryService;
use Workerman\Worker;
use support\Log;

/**
 * 进程事件处理
 */
class ProcessEvent
{
    /**
     * onWorkerStart 事件
     * 
     * @param Worker $worker
     */
    public static function onWorkerStart(Worker $worker): void
    {
        // 仅在主进程执行
        if ($worker->id === 0) {
            self::registerServices();
        }
    }

    /**
     * onWorkerStop 事件
     * 
     * @param Worker $worker
     */
    public static function onWorkerStop(Worker $worker): void
    {
        self::deregisterServices();
    }

    /**
     * 注册服务
     */
    protected static function registerServices(): void
    {
        $config = config('services', []);
        
        if (empty($config)) {
            return;
        }
        
        $discoveryService = new NacosDiscoveryService();
        
        foreach ($config as $service) {
            try {
                $result = $discoveryService->register(
                    $service['name'],
                    $service['port'],
                    $service['group'] ?? 'DEFAULT_GROUP',
                    $service['metadata'] ?? [],
                    $service['weight'] ?? 1,
                    $service['ephemeral'] ?? true
                );
                
                if ($result) {
                    Log::info("Service registered: {$service['name']}:{$service['port']}");
                    echo "[Nacos] Service registered: {$service['name']}:{$service['port']}\n";
                }
            } catch (\Exception $e) {
                Log::error("Failed to register service: " . $e->getMessage());
            }
        }
    }

    /**
     * 注销服务
     */
    protected static function deregisterServices(): void
    {
        $config = config('services', []);
        
        if (empty($config)) {
            return;
        }
        
        $discoveryService = new NacosDiscoveryService();
        
        foreach ($config as $service) {
            try {
                $result = $discoveryService->deregister(
                    $service['name'],
                    $service['port'],
                    $service['group'] ?? 'DEFAULT_GROUP'
                );
                
                if ($result) {
                    Log::info("Service deregistered: {$service['name']}:{$service['port']}");
                    echo "[Nacos] Service deregistered: {$service['name']}:{$service['port']}\n";
                }
            } catch (\Exception $e) {
                Log::error("Failed to deregister service: " . $e->getMessage());
            }
        }
    }
}
```

---

## 完整示例：微服务架构

### 项目结构

```
webman-microservice/
├── app/
│   ├── controller/
│   │   ├── user/
│   │   │   └── UserController.php
│   │   ├── order/
│   │   │   └── OrderController.php
│   │   └── product/
│   │       └── ProductController.php
│   ├── service/
│   │   ├── UserService.php
│   │   ├── OrderService.php
│   │   ├── ProductService.php
│   │   ├── NacosConfigService.php
│   │   ├── NacosDiscoveryService.php
│   │   └── FeignService.php
│   ├── model/
│   │   ├── User.php
│   │   ├── Order.php
│   │   └── Product.php
│   └── bootstrap/
│       └── nacos.php
├── config/
│   ├── nacos.php
│   ├── services.php    # 注册的服务列表
│   └── database.php
├── routes/
│   └── api.php
└── composer.json
```

### 配置文件

#### `config/nacos.php`

```php
<?php

return [
    'server_url' => env('NACOS_SERVER_URL', 'http://127.0.0.1:8848'),
    'namespace_id' => env('NACOS_NAMESPACE', 'public'),
    'username' => env('NACOS_USERNAME', 'nacos'),
    'password' => env('NACOS_PASSWORD', 'nacos'),
    'access_key' => env('NACOS_ACCESS_KEY', ''),
    'secret_key' => env('NACOS_SECRET_KEY', ''),
    'grpc_port' => env('NACOS_GRPC_PORT', 9848),
    'grpc_fallback' => true,
];
```

#### `config/services.php`

```php
<?php

return [
    // 用户服务
    [
        'name' => 'user-service',
        'port' => 8080,
        'group' => 'DEFAULT_GROUP',
        'metadata' => [
            'version' => '1.0.0',
            'environment' => env('APP_ENV', 'dev'),
        ],
        'weight' => 1,
        'ephemeral' => true,
    ],
    
    // 订单服务
    [
        'name' => 'order-service',
        'port' => 8081,
        'group' => 'DEFAULT_GROUP',
        'metadata' => [
            'version' => '1.0.0',
            'environment' => env('APP_ENV', 'dev'),
        ],
        'weight' => 1,
        'ephemeral' => true,
    ],
];
```

### 控制器示例

#### `app/controller/user/UserController.php`

```php
<?php

namespace app\controller\user;

use app\service\user\UserService;
use app\service\FeignService;
use support\Request;
use support\Response;

class UserController
{
    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var FeignService
     */
    protected $feignService;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->userService = new UserService();
        $this->feignService = new FeignService();
    }

    /**
     * 获取用户列表
     * 
     * GET /api/users
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 10);
        
        $users = $this->userService->paginate($page, $limit);
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $users,
        ]);
    }

    /**
     * 获取单个用户
     * 
     * GET /api/users/{id}
     */
    public function show(Request $request, int $id): Response
    {
        $user = $this->userService->findById($id);
        
        if (!$user) {
            return json([
                'code' => 404,
                'msg' => 'User not found',
            ], 404);
        }
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $user,
        ]);
    }

    /**
     * 创建用户
     * 
     * POST /api/users
     */
    public function store(Request $request): Response
    {
        $data = $request->post();
        
        $id = $this->userService->create($data);
        
        return json([
            'code' => 0,
            'msg' => 'User created',
            'data' => ['id' => $id],
        ], 201);
    }

    /**
     * 获取用户订单
     * 
     * GET /api/users/{id}/orders
     * 
     * 通过 Feign 调用订单服务
     */
    public function orders(Request $request, int $id): Response
    {
        try {
            // 通过 Feign 调用订单服务
            $result = $this->feignService->get('order-service', "/api/orders", [
                'user_id' => $id,
            ]);
            
            if ($result['success']) {
                return json([
                    'code' => 0,
                    'msg' => 'success',
                    'data' => $result['data'],
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => 'Failed to fetch orders',
                    'error' => $result['raw'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
```

### 路由配置

#### `routes/api.php`

```php
<?php

use Webman\Route;

// 用户服务
Route::group('/api/users', function () {
    Route::get('/', [app\controller\user\UserController::class, 'index']);
    Route::post('/', [app\controller\user\UserController::class, 'store']);
    Route::get('/{id}', [app\controller\user\UserController::class, 'show']);
    Route::get('/{id}/orders', [app\controller\user\UserController::class, 'orders']);
});

// 订单服务
Route::group('/api/orders', function () {
    Route::get('/', [app\controller\order\OrderController::class, 'index']);
    Route::post('/', [app\controller\order\OrderController::class, 'store']);
    Route::get('/{id}', [app\controller\order\OrderController::class, 'show']);
});

// 产品服务
Route::group('/api/products', function () {
    Route::get('/', [app\controller\product\ProductController::class, 'index']);
    Route::post('/', [app\controller\product\ProductController::class, 'store']);
    Route::get('/{id}', [app\controller\product\ProductController::class, 'show']);
});
```

---

## 认证配置

### 生产环境配置示例

#### `.env` 生产配置

```bash
# 生产环境 Nacos 配置
NACOS_SERVER_URL=http://nacos.prod.internal:8848
NACOS_NAMESPACE=prod
NACOS_USERNAME=prod_user
NACOS_PASSWORD=prod_secure_password_xxx
NACOS_GRPC_PORT=9848

# 或使用 AK/SK（推荐用于阿里云 MSE）
NACOS_ACCESS_KEY=LTAI5tXXXXXXXXXXXXXXXXX
NACOS_SECRET_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### 多环境配置

创建 `config/nacos.php` 支持多环境：

```php
<?php

$env = env('APP_ENV', 'production');

$configs = [
    'local' => [
        'server_url' => 'http://127.0.0.1:8848',
        'namespace_id' => 'public',
        'username' => 'nacos',
        'password' => 'nacos',
    ],
    
    'dev' => [
        'server_url' => 'http://nacos.dev.internal:8848',
        'namespace_id' => 'dev',
        'username' => 'dev_user',
        'password' => 'dev_password',
    ],
    
    'test' => [
        'server_url' => 'http://nacos.test.internal:8848',
        'namespace_id' => 'test',
        'username' => 'test_user',
        'password' => 'test_password',
    ],
    
    'production' => [
        'server_url' => 'http://nacos.prod.internal:8848',
        'namespace_id' => 'prod',
        'username' => 'prod_user',
        'password' => env('NACOS_PASSWORD'),
    ],
];

return array_merge($configs['production'], [
    'server_url' => $configs[$env]['server_url'] ?? $configs['production']['server_url'],
    'namespace_id' => $configs[$env]['namespace_id'] ?? $configs['production']['namespace_id'],
    'username' => $configs[$env]['username'] ?? $configs['production']['username'],
    'password' => $configs[$env]['password'] ?? $configs['production']['password'],
]);
```

---

## 最佳实践

### 1. 配置管理

```php
// ✅ 推荐：使用服务类封装
class ConfigService
{
    protected static $cache = [];
    
    public static function get(string $key, $default = null)
    {
        if (!isset(self::$cache[$key])) {
            $config = new NacosConfigService();
            self::$cache[$key] = $config->get($key);
        }
        
        return self::$cache[$key] ?? $default;
    }
    
    // 配置变更回调
    public static function refresh(): void
    {
        self::$cache = [];
    }
}

// ❌ 不推荐：直接在控制器中使用
class UserController
{
    public function index()
    {
        $nacos = NacosBootstrap::getClient();
        $dbConfig = $nacos->config()->getConfig('database.json'); // 每次都请求
    }
}
```

### 2. 服务发现

```php
// ✅ 推荐：使用单例模式
class ServiceDiscovery
{
    protected static $instance = null;
    
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getServiceInstance(string $name): ?array
    {
        $service = new NacosDiscoveryService();
        return $service->selectOne($name);
    }
}

// ✅ 推荐：添加重试机制
public function callService(string $serviceName, string $path): array
{
    $maxRetries = 3;
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            $instance = $this->getServiceInstance($serviceName);
            return $this->http->get($instance, $path);
        } catch (\Exception $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            usleep(100000 * $attempt); // 指数退避
        }
    }
}
```

### 3. 错误处理

```php
// ✅ 推荐：统一的异常处理
try {
    $result = $nacos->config()->getConfig($dataId, $group);
} catch (NacosException $e) {
    Log::error('Nacos error', [
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
    ]);
    
    // 返回默认值或从本地配置加载
    return config('fallback.' . $dataId);
}

// ❌ 不推荐：吞掉异常
try {
    $result = $nacos->config()->getConfig($dataId, $group);
} catch (\Exception $e) {
    return []; // 静默失败，难以排查问题
}
```

### 4. 性能优化

```php
// ✅ 推荐：使用本地缓存
class OptimizedConfigService
{
    protected static $cache = [];
    protected static $cacheTime = [];
    protected $ttl = 300; // 5分钟
    
    public function get(string $key): string
    {
        if ($this->isExpired($key)) {
            $configService = new NacosConfigService();
            self::$cache[$key] = $configService->get($key);
            self::$cacheTime[$key] = time();
        }
        
        return self::$cache[$key];
    }
    
    protected function isExpired(string $key): bool
    {
        return !isset(self::$cache[$key]) 
            || (time() - (self::$cacheTime[$key] ?? 0)) > $this->ttl;
    }
}
```

---

## 故障排查

### 常见问题

#### 1. 连接超时

```
错误: Connect timeout
解决: 增加超时时间或检查网络
```

```php
// config/nacos.php
'client' => [
    'timeout' => 30,          // 增加超时
    'connect_timeout' => 10,
],
```

#### 2. 认证失败

```
错误: 403 Forbidden - user not found
解决: 检查用户名密码或 AK/SK 配置
```

```php
// .env
NACOS_USERNAME=correct_username
NACOS_PASSWORD=correct_password
```

#### 3. gRPC 不可用

```
提示: gRPC extension is not installed
解决: SDK 会自动回退到 HTTP，无需处理
```

#### 4. 配置获取为空

```php
// 检查配置是否存在
$content = $nacos->config()->getConfig('my-config');
if (empty($content)) {
    Log::warning('Config is empty or not found', [
        'dataId' => 'my-config',
        'group' => 'DEFAULT_GROUP',
    ]);
}
```

### 日志配置

在 `config/log.php` 中添加 Nacos 相关日志：

```php
<?php

return [
    'default' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/default.log',
                    7, // 保留天数
                ],
                'formatter' => [
                    'class' => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [
                        "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                    ],
                ],
            ],
        ],
    ],
    
    // Nacos 专用日志
    'nacos' => [
        'handlers' => [
            [
                'class' => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/nacos.log',
                    7,
                ],
            ],
        ],
    ],
];
```

---

## API 参考

### NacosClient 常用方法

```php
// 配置管理
$nacos->config()->getConfig($dataId, $group);
$nacos->config()->publishConfig($dataId, $group, $content, $type);
$nacos->config()->deleteConfig($dataId, $group);
$nacos->config()->listenConfig($dataId, $group, $callback);

// 服务发现
$nacos->discovery()->registerInstance($serviceName, $ip, $port, $group);
$nacos->discovery()->deregisterInstance($serviceName, $ip, $port, $group);
$nacos->discovery()->getAllInstances($serviceName, $group, $healthyOnly);
$nacos->discovery()->selectOneHealthyInstance($serviceName, $group);
$nacos->discovery()->sendHeartbeat($serviceName, $ip, $port, $group);

// 服务调用
$nacos->invoker()->get($serviceName, $path, $params);
$nacos->invoker()->post($serviceName, $path, $data);
$nacos->invoker()->request($method, $serviceName, $path, $data);

// Feign 客户端
$nacos->feign($serviceName, $group)->get($path, $params);
$nacos->feign($serviceName, $group)->post($path, $data);
```

---

## 总结

本指南涵盖了 Nacos SDK PHP 与 Webman 集成的所有关键场景：

1. **配置管理**：发布、获取、监听配置变更
2. **服务发现**：注册、注销、获取服务实例
3. **服务调用**：Feign 风格的声明式调用
4. **最佳实践**：错误处理、性能优化、安全配置
5. **生产部署**：多环境配置、认证、日志

通过这些示例代码，您可以在 Webman 项目中快速集成 Nacos，实现：
- ✅ 集中化配置管理
- ✅ 动态服务注册与发现
- ✅ 负载均衡的服务调用
- ✅ 高可用的微服务架构

如有问题，请查阅 [SDK 文档](./README.md) 或提交 Issue。
