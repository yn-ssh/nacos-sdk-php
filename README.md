# Nacos SDK for PHP

适用于 Nacos 2.x / 3.x 的 PHP SDK，提供服务配置管理、服务发现、服务调用、gRPC 通信等完整能力。

## 特性

- **配置管理** — 发布 / 获取 / 删除 / 监听配置变更
- **服务发现** — 注册 / 注销实例，获取实例列表与健康实例
- **服务调用** — 自动发现健康实例并调用，内置缓存与重试
- **Feign 声明式客户端** — 类 OpenFeign 的声明式 API 调用
- **三通道通信** — Swoole HTTP/2 双向流 → gRPC → HTTP 自动降级
- **双认证方式** — 用户名密码 (accessToken) / AK-SK 签名
- **版本自适应** — 自动检测 Nacos 服务器版本，适配 v1 / v2 API 路径
- **领域模型** — Instance / Config / Service 数据模型，支持序列化与反序列化

## 安装

```bash
composer require ssh/nacos-sdk-php
```

## 系统要求

| 依赖 | 最低版本 | 说明 |
|------|---------|------|
| PHP | >= 8.0 | |
| guzzlehttp/guzzle | ^6.0 \|\| ^7.0 | HTTP 客户端 |
| psr/log | ^1.0 \|\| ^2.0 \|\| ^3.0 | 日志接口 |
| Swoole 扩展 | 可选 | 启用 HTTP/2 真双向流 gRPC（推荐） |
| grpc + protobuf 扩展 | 可选 | 传统 gRPC 通道（Swoole 不可用时回退） |

## 快速开始

### 创建客户端

```php
use Nacos\Nacos;

$nacos = new Nacos(
    string $serverUrl   = 'http://localhost:8848',  // Nacos 服务器地址
    string $namespaceId = 'public',                 // 命名空间 ID
    string $accessKey   = '',                       // AK/SK 认证的 AccessKey
    string $secretKey   = '',                       // AK/SK 认证的 SecretKey
    int    $grpcPort    = 9848,                     // gRPC 端口（设为 0 则禁用 gRPC，仅用 HTTP）
    ?LoggerInterface $logger = null,                // 日志接口（PSR-3）
    string $username    = '',                       // 用户名密码认证的用户名
    string $password    = '',                       // 用户名密码认证的密码
);
```

#### 用户名密码认证（自建 Nacos）

```php
$nacos = new Nacos(
    'http://localhost:8848',  // serverUrl
    'public',                 // namespaceId
    '',                       // accessKey（不使用 AK/SK 时留空）
    '',                       // secretKey
    9848,                     // gRPC 端口
    null,                     // logger
    'nacos',                  // username
    'nacos'                   // password
);
```

#### AK/SK 认证（阿里云 MSE 等）

```php
$nacos = new Nacos(
    'http://localhost:8848',  // serverUrl
    'dev-namespace',          // namespaceId
    'your-access-key',        // accessKey
    'your-secret-key'         // secretKey
    // 其余参数使用默认值：grpcPort=9848, logger=null, username='', password=''
);
```

#### 仅使用 HTTP（禁用 gRPC）

```php
$nacos = new Nacos(
    'http://localhost:8848',
    'public',
    '', '',
    0,           // grpcPort=0 禁用 gRPC，所有请求走 HTTP
    null,
    'nacos', 'nacos'
);
```

#### 自定义命名空间

```php
$nacos = new Nacos(
    'http://localhost:8848',
    'dev-namespace',          // 非 public 的命名空间
    '', '', 9848, null,
    'nacos', 'nacos'
);
```

### 配置管理

```php
// 发布配置
bool $nacos->config()->publishConfig(
    string $dataId,              // 配置 ID
    string $group  = 'DEFAULT_GROUP',  // 分组
    string $content,             // 配置内容
    string $type    = 'text'     // 配置类型：text / json / yaml / properties / xml / html
);

// 获取配置（返回原始字符串，不会误解析 JSON 内容）
string $nacos->config()->getConfig(
    string $dataId,
    string $group = 'DEFAULT_GROUP'
);

// 删除配置
bool $nacos->config()->deleteConfig(
    string $dataId,
    string $group = 'DEFAULT_GROUP'
);

// 监听配置变更（长轮询）
$nacos->config()->listenConfig(
    string   $dataId,            // 配置 ID
    string   $group,             // 分组
    callable $callback,          // 变更回调函数
    int      $timeout = 30       // 长轮询超时（秒）
);
```

**使用示例：**

```php
// 发布 properties 配置
$nacos->config()->publishConfig('app.properties', 'DEFAULT_GROUP', 'host=127.0.0.1\nport=8080', 'properties');

// 发布 JSON 配置
$nacos->config()->publishConfig('app.json', 'DEFAULT_GROUP', '{"db":"mysql"}', 'json');

// 发布 YAML 配置
$nacos->config()->publishConfig('app.yml', 'DEFAULT_GROUP', 'server:\n  port: 8080', 'yaml');

// 获取配置
$content = $nacos->config()->getConfig('app.json', 'DEFAULT_GROUP');
// 返回: '{"db":"mysql"}'（字符串，不会被解析为数组）

// 监听配置变更
$nacos->config()->listenConfig('app.yml', 'DEFAULT_GROUP', function ($data) {
    echo "配置已变更: " . json_encode($data);
}, 30);
```

### 服务发现

```php
// 注册服务实例
bool $nacos->discovery()->registerInstance(
    string $serviceName,              // 服务名
    string $ip,                       // 实例 IP
    int    $port,                     // 实例端口
    string $group     = 'DEFAULT_GROUP',  // 分组名
    array  $metadata  = [],           // 元数据（键值对）
    int    $weight    = 1,            // 权重
    bool   $ephemeral = true          // true=临时实例  false=持久化实例
);

// 注销服务实例
bool $nacos->discovery()->deregisterInstance(
    string $serviceName,
    string $ip,
    int    $port,
    string $group     = 'DEFAULT_GROUP',
    bool   $ephemeral = true
);

// 获取所有实例
array $nacos->discovery()->getAllInstances(
    string $serviceName,
    string $group       = 'DEFAULT_GROUP',
    bool   $healthyOnly = true
);

// 选择一个健康实例
?array $nacos->discovery()->selectOneHealthyInstance(
    string $serviceName,
    string $group = 'DEFAULT_GROUP'
);

// 发送心跳（临时实例保活）
bool $nacos->discovery()->sendHeartbeat(
    string $serviceName,
    string $ip,
    int    $port,
    string $group = 'DEFAULT_GROUP'
);
```

**使用示例：**

```php
// 注册临时实例（推荐）
$nacos->discovery()->registerInstance(
    'user-service', '127.0.0.1', 8080,
    'DEFAULT_GROUP', ['version' => '1.0.0'], 10, true
);

// 注册持久化实例
$nacos->discovery()->registerInstance(
    'user-service', '127.0.0.1', 8081,
    'DEFAULT_GROUP', [], 5, false
);

// 注销实例
$nacos->discovery()->deregisterInstance('user-service', '127.0.0.1', 8080, 'DEFAULT_GROUP', true);

// 获取所有实例（含不健康）
$instances = $nacos->discovery()->getAllInstances('user-service', 'DEFAULT_GROUP', false);
// $instances['hosts'] 为实例数组

// 选择一个健康实例（随机负载均衡）
$instance = $nacos->discovery()->selectOneHealthyInstance('user-service');
// $instance['ip'], $instance['port'], $instance['metadata']

// 发送心跳
$nacos->discovery()->sendHeartbeat('user-service', '127.0.0.1', 8080);
```

### 服务调用

```php
// GET 请求
array $nacos->invoker()->get(
    string $serviceName,
    string $path,                              // 接口路径
    array  $params     = [],                   // 查询参数
    string $group      = 'DEFAULT_GROUP',
    int    $retryCount = 3                     // 重试次数
);

// POST 请求
array $nacos->invoker()->post(
    string $serviceName,
    string $path,
    array  $data        = [],                  // 请求体（JSON）
    string $group       = 'DEFAULT_GROUP',
    int    $retryCount  = 3
);

// 通用请求
array $nacos->invoker()->request(
    string $method,                            // HTTP 方法：GET/POST/PUT/DELETE/PATCH
    string $serviceName,
    string $path,
    array  $data        = [],
    string $group       = 'DEFAULT_GROUP',
    int    $retryCount  = 3
);

// 获取健康实例（30 秒缓存）
?array $nacos->invoker()->getHealthyInstance(
    string $serviceName,
    string $group = 'DEFAULT_GROUP'
);

// 构建服务 URL
string $nacos->invoker()->buildUrl(
    array|Instance $instance,                  // 实例信息
    string $path = '/'                         // 接口路径
);

// 清除缓存
$nacos->invoker()->clearCache(?string $serviceName = null, string $group = 'DEFAULT_GROUP');
```

**使用示例：**

```php
// GET 调用
$result = $nacos->invoker()->get('user-service', '/api/users', ['page' => 1]);

// POST 调用
$result = $nacos->invoker()->post('user-service', '/api/users', ['name' => 'test']);

// 获取健康实例
$instance = $nacos->invoker()->getHealthyInstance('user-service');

// 构建服务 URL（不发送请求）
$url = $nacos->invoker()->buildUrl($instance, '/api/users');
// http://127.0.0.1:8080/api/users

// 清除缓存
$nacos->invoker()->clearCache('user-service');  // 指定服务
$nacos->invoker()->clearCache();                 // 所有服务
```

**响应结构：**

```php
[
    'success'     => true,
    'status_code' => 200,
    'data'        => [...],   // JSON 解码后的数据
    'raw'         => '...',   // 原始响应体
]
```

### Feign 声明式客户端

```php
// 创建 Feign 客户端（自动缓存，同服务名+分组复用实例）
FeignClient $nacos->feign(
    string $serviceName,
    string $groupName = 'DEFAULT_GROUP'
);

// Feign 客户端方法
array $feign->get(string $path, array $params = [], int $retryCount = 3);
array $feign->post(string $path, array $data = [], int $retryCount = 3);
array $feign->put(string $path, array $data = [], int $retryCount = 3);
array $feign->delete(string $path, array $params = [], int $retryCount = 3);
array $feign->request(string $method, string $path, array $data = [], int $retryCount = 3);
```

**使用示例：**

```php
// 创建 Feign 客户端
$userClient  = $nacos->feign('user-service');
$orderClient = $nacos->feign('order-service', 'PROD_GROUP');

// 声明式调用
$users  = $userClient->get('/api/users', ['page' => 1]);
$result = $userClient->post('/api/users', ['name' => 'test']);
$result = $userClient->put('/api/users/1', ['name' => 'updated']);
$result = $userClient->delete('/api/users/1');

// 自定义重试次数
$result = $userClient->get('/api/users', [], 5);

// 同时使用多个 Feign 客户端
$productClient = $nacos->feign('product-service');
$products = $productClient->get('/api/products');
```

### gRPC 客户端

SDK 支持两种 gRPC 通信方式，自动检测可用扩展并按优先级选择：

| 优先级 | 方式 | 依赖 | 说明 |
|--------|------|------|------|
| 1 | Swoole HTTP/2 双向流 | Swoole 扩展 | 真双向流注册，性能最佳 |
| 2 | PHP gRPC 扩展 | grpc + protobuf | 传统 gRPC 通道 |
| 3 | HTTP 降级 | guzzlehttp | gRPC 不可用时自动回退 |

```php
// 检查 gRPC 可用性（grpcPort=0 时 grpc() 返回 null）
bool $nacos->grpc()?->isGrpcAvailable();

// gRPC 客户端自动共享 NacosClient 的 accessToken
?NacosClient $nacos->grpc()?->getHttpClient();

// 重置可用性缓存（强制下次重新检测）
$nacos->grpc()?->resetAvailabilityCache();
```

**使用示例：**

```php
// 检测 gRPC 是否可用
if ($nacos->grpc() && $nacos->grpc()->isGrpcAvailable()) {
    echo "gRPC 可用，配置和服务操作将优先走 gRPC 通道\n";
} else {
    echo "gRPC 不可用，自动使用 HTTP 通道\n";
}
```

> 无需额外代码，配置管理和服务发现操作会自动优先走 gRPC 通道。
> 如果不需要 gRPC，初始化时传入 `grpcPort=0` 即可完全禁用。

### 领域模型

```php
use Nacos\Model\Instance;
use Nacos\Model\Config;
use Nacos\Model\Service;

// ── Instance 模型 ──

// 从 Nacos 响应数组创建
$instance = Instance::fromArray([
    'serviceName' => 'my-service',
    'ip'          => '192.168.1.1',
    'port'        => 8080,
    'metadata'    => ['secure' => 'true'],
]);

// 直接构造
$instance = new Instance(
    string $serviceName,
    string $ip,
    int    $port,
    string $groupName  = 'DEFAULT_GROUP',
    array  $metadata   = [],
    int    $weight     = 1,
    bool   $ephemeral  = true,
    string $namespaceId = 'public'
);

// 常用方法
$instance->isSecure();                    // bool — metadata 中 secure=true 时返回 true
$instance->buildUrl('/api/test');         // string — 自动根据 secure 选择 http/https
$instance->toRequestParams();             // array — 转换为 API 请求参数
$instance->toArray();                     // array — 序列化为数组

// ── Config 模型 ──

// 从 Nacos 响应数组创建
$config = Config::fromArray([
    'dataId'  => 'app.yml',
    'group'   => 'PROD_GROUP',
    'content' => 'key: value',
    'type'    => 'yaml',
]);

// 直接构造
$config = new Config(
    string $dataId,
    string $group   = 'DEFAULT_GROUP',
    string $content = '',
    string $type    = 'text'
);

// 常用方法
$config->setContent('new content');       // 自动更新 md5
$config->getMd5();                        // string — 内容的 MD5 哈希
$config->parseContentAsArray();           // ?array — JSON 内容自动解析为数组
$config->toRequestParams();               // array — 转换为 API 请求参数
$config->toArray();                       // array — 序列化为数组

// ── Service 模型 ──

$service = Service::fromArray([
    'serviceName'      => 'my-svc',
    'groupName'        => 'TEST_GROUP',
    'protectThreshold' => 0.5,
]);

$service->getServiceName();               // string
$service->getGroupName();                 // string
$service->getProtectThreshold();          // float
$service->toArray();                      // array
```

## 项目结构

```
src/
├── Client/
│   ├── NacosClient.php          # HTTP 客户端（认证、请求、Token 管理）
│   ├── NacosGrpcClient.php      # gRPC 客户端（可用性检测、Token 共享）
│   └── SwooleGrpcClient.php     # Swoole HTTP/2 双向流客户端
├── Config/
│   └── ConfigClient.php         # 配置管理（发布 / 获取 / 删除 / 监听）
├── Discovery/
│   └── DiscoveryClient.php      # 服务发现（注册 / 注销 / 查询 / 心跳）
├── Model/
│   ├── Instance.php             # 服务实例模型
│   ├── Config.php               # 配置模型
│   └── Service.php              # 服务模型
├── Utils/
│   ├── ServiceInvoker.php       # 服务调用（发现 + 调用 + 缓存 + 重试）
│   └── FeignClient.php          # Feign 声明式客户端
├── Exception/
│   └── NacosException.php       # 异常类
└── Nacos.php                    # 主入口
```

## 通信策略

```
┌──────────────┐   Swoole 可用     ┌────────────────────┐
│              │ ────────────────→ │ Swoole HTTP/2 双向流 │
│              │                    └────────────────────┘
│   SDK 调用    │   gRPC扩展可用   ┌────────────────────┐
│              │ ────────────────→ │  gRPC 9848          │
│              │                    └────────────────────┘
│              │   均不可用         ┌────────────────────┐
│              │ ────────────────→ │  HTTP 8848          │
└──────────────┘                    └────────────────────┘
```

所有配置管理和服务发现操作自动遵循此策略，开发者无需手动切换。
传入 `grpcPort=0` 可强制所有请求走 HTTP 通道。

## 认证方式

| 方式 | 配置 | 适用场景 |
|------|------|---------|
| 用户名密码 | 构造函数传入 `username` / `password` | 自建 Nacos 集群 |
| AK/SK | 构造函数传入 `accessKey` / `secretKey` | 阿里云 MSE 等托管服务 |

两种方式可以同时配置，SDK 会按需使用。用户名密码认证会自动登录获取 `accessToken` 并定期刷新；gRPC 客户端自动共享 HTTP 客户端的 `accessToken`。

## 注意事项

1. **命名空间 `public`**：Nacos 的 `public` 命名空间实际 ID 为空字符串，SDK 内部自动处理，用户传入 `'public'` 即可。

2. **JSON 格式配置**：`getConfig()` 使用原始响应体获取配置内容，不会将 JSON 格式的配置误解析为数组。

3. **持久化实例**：Nacos standalone 模式下持久化实例可能因 Raft 一致性限制返回 500 错误，这是 Nacos 服务端已知问题，集群模式不受影响。

4. **gRPC 扩展**：gRPC 通道优先使用 Swoole HTTP/2 双向流（推荐），其次回退到 `grpc` + `protobuf` PHP 扩展。均未安装时自动降级为 HTTP，不影响功能使用。可通过 `grpcPort=0` 主动禁用 gRPC。

5. **服务调用缓存**：`ServiceInvoker` 默认缓存健康实例 30 秒，可通过 `clearCache()` 手动清除。

## 许可证

MIT License
