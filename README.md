# Nacos SDK for PHP

适用于 Nacos 2.x / 3.x 的 PHP SDK，提供服务配置管理、服务发现、服务调用、gRPC 通信等完整能力。

## 特性

- **配置管理** — 发布 / 获取 / 删除 / 监听配置变更
- **服务发现** — 注册 / 注销实例，获取实例列表与健康实例
- **服务调用** — 自动发现健康实例并调用，内置缓存与重试
- **Feign 声明式客户端** — 类 OpenFeign 的声明式 API 调用
- **双通道通信** — gRPC 优先 → HTTP 自动降级
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
| grpc 扩展 | 可选 | 启用 gRPC 通道 |
| protobuf 扩展 | 可选 | 配合 gRPC 使用 |

## 快速开始

### 创建客户端

```php
use Nacos\Nacos;

$nacos = new Nacos(
    string $serverUrl   = 'http://localhost:8848',  // Nacos 服务器地址
    string $namespaceId = 'public',                 // 命名空间 ID
    string $accessKey   = '',                       // AK/SK 认证的 AccessKey
    string $secretKey   = '',                       // AK/SK 认证的 SecretKey
    int    $grpcPort    = 9848,                     // gRPC 端口
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

SDK 自动检测 gRPC 扩展和服务可用性，优先使用 gRPC，不可用时自动降级为 HTTP。

```php
// 检查 gRPC 可用性
bool $nacos->grpc()->isGrpcAvailable();

// gRPC 客户端自动共享 NacosClient 的 accessToken
?NacosClient $nacos->grpc()->getHttpClient();

// 重置可用性缓存（强制下次重新检测）
$nacos->grpc()->resetAvailabilityCache();
```

**使用示例：**

```php
// 检测 gRPC 是否可用
if ($nacos->grpc()->isGrpcAvailable()) {
    echo "gRPC 可用，配置和服务操作将优先走 gRPC 通道\n";
} else {
    echo "gRPC 不可用，自动使用 HTTP 通道\n";
}

// 验证 Token 共享
$httpClient = $nacos->grpc()->getHttpClient();
if ($httpClient) {
    echo "accessToken: " . substr($httpClient->getAccessToken(), 0, 16) . "...\n";
}
```

> 无需额外代码，配置管理和服务发现操作会自动优先走 gRPC 通道。

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
│   └── NacosGrpcClient.php      # gRPC 客户端（可用性检测、Token 共享）
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
┌──────────────┐     gRPC 可用      ┌──────────────┐
│              │ ─────────────────→ │  gRPC 9848   │
│   SDK 调用    │                    └──────────────┘
│              │     gRPC 不可用     ┌──────────────┐
│              │ ─────────────────→ │  HTTP 8848   │
└──────────────┘                    └──────────────┘
```

所有配置管理和服务发现操作自动遵循此策略，开发者无需手动切换。

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

4. **gRPC 扩展**：gRPC 通道需要安装 `grpc` 和 `protobuf` PHP 扩展。未安装时 SDK 自动降级为 HTTP，不影响功能使用。

5. **服务调用缓存**：`ServiceInvoker` 默认缓存健康实例 30 秒，可通过 `clearCache()` 手动清除。

## 许可证

MIT License
# Nacos SDK for PHP

PHP SDK for Nacos service discovery and configuration management.

## 安装

```bash
composer require ssh/nacos-sdk-php
```

## 功能特性

- **配置管理**
  - 发布配置
  - 获取配置
  - 删除配置
  - 监听配置变更

- **服务发现**
  - 注册服务实例（支持临时和持久化服务）
  - 注销服务实例
  - 获取服务实例列表
  - 获取单个健康实例
  - 发送心跳（临时服务）

- **服务调用**
  - 自动获取健康服务实例
  - 支持GET/POST等HTTP方法
  - 服务实例缓存（30秒）
  - 自动重试机制（默认3次）
  - 支持HTTPS（通过元数据配置）

- **Feign风格声明式客户端**
  - 声明式API调用，类似OpenFeign
  - 自动服务发现和HTTP调用
  - 简单易用，减少样板代码
  - 支持缓存多个Feign客户端

- **gRPC支持（Nacos 2.x/3.x）**
  - 支持 Nacos 9848 端口的 gRPC 服务
  - 自动检测 gRPC 服务可用性
  - 优先使用 gRPC，HTTP 作为后备方案
  - 支持配置管理和服务发现的所有功能

## 使用方法

### 初始化客户端

```php
use Nacos\Nacos;

// 初始化Nacos客户端
$nacos = new Nacos(
    'http://localhost:8848', // Nacos服务器地址
    'public',                 // 命名空间ID，默认为'public'
    '',                       // Access Key（可选）
    '',                       // Secret Key（可选）
    9848,                     // gRPC端口（可选，默认9848）
    null                      // 日志接口（可选，实现Psr\Log\LoggerInterface）
);
```

### 配置管理

#### 1. 发布配置

```php
// 发布配置
$result = $nacos->config()->publishConfig(
    'test-config',           // dataId
    'DEFAULT_GROUP',         // group
    'Hello Nacos!',          // 配置内容
    'text'                   // 配置类型
);

if ($result) {
    echo "配置发布成功！\n";
}
```

#### 2. 获取配置

```php
// 获取配置
$content = $nacos->config()->getConfig(
    'test-config',           // dataId
    'DEFAULT_GROUP'          // group
);

echo "配置内容: " . $content . "\n";
```

#### 3. 删除配置

```php
// 删除配置
$result = $nacos->config()->deleteConfig(
    'test-config',           // dataId
    'DEFAULT_GROUP'          // group
);

if ($result) {
    echo "配置删除成功！\n";
}
```

#### 4. 监听配置变更

```php
// 监听配置变更（长轮询）
$nacos->config()->listenConfig(
    'test-config',           // dataId
    'DEFAULT_GROUP',         // group
    function($data) {        // 回调函数
        echo "配置变更了！\n";
        echo "变更数据: " . json_encode($data) . "\n";
    },
    30                      // 超时时间（秒）
);
```

### 服务发现

#### 1. 注册服务实例

```php
// 注册持久化服务实例
$result = $nacos->discovery()->registerInstance(
    'user-service',          // 服务名
    '127.0.0.1',             // IP地址
    8080,                    // 端口
    'DEFAULT_GROUP',         // 分组
    ['version' => '1.0.0'],  // 元数据
    10,                      // 权重
    false                    // 是否为临时服务（false表示持久化）
);

if ($result) {
    echo "服务注册成功！\n";
}
```

#### 2. 注销服务实例

```php
// 注销服务实例
$result = $nacos->discovery()->deregisterInstance(
    'user-service',          // 服务名
    '127.0.0.1',             // IP地址
    8080,                    // 端口
    'DEFAULT_GROUP'          // 分组
);

if ($result) {
    echo "服务注销成功！\n";
}
```

#### 3. 获取所有服务实例

```php
// 获取所有服务实例
$instances = $nacos->discovery()->getAllInstances(
    'user-service',          // 服务名
    'DEFAULT_GROUP',         // 分组
    true                     // 是否只获取健康实例
);

echo "实例数量: " . count($instances['hosts']) . "\n";
echo "实例列表: " . json_encode($instances, JSON_PRETTY_PRINT) . "\n";
```

#### 4. 获取单个健康实例

```php
// 获取单个健康实例
$instance = $nacos->discovery()->selectOneHealthyInstance(
    'user-service',          // 服务名
    'DEFAULT_GROUP'          // 分组
);

if ($instance) {
    echo "健康实例: " . json_encode($instance) . "\n";
    echo "IP: " . $instance['ip'] . "\n";
    echo "端口: " . $instance['port'] . "\n";
}
```

### 服务调用

#### 1. 调用服务（GET方法）

```php
// 调用服务的GET接口
$result = $nacos->invoker()->get(
    'user-service',                    // 服务名
    '/api/users',                     // 接口路径
    ['page' => 1, 'limit' => 10],     // 查询参数
    'DEFAULT_GROUP',                  // 分组（可选）
    3                                 // 重试次数（可选）
);

// 处理响应
if ($result['success']) {
    echo "状态码: " . $result['status_code'] . "\n";
    echo "数据: " . json_encode($result['data']) . "\n";
    echo "原始响应: " . $result['raw'] . "\n";
}
```

#### 2. 调用服务（POST方法）

```php
// 调用服务的POST接口
$result = $nacos->invoker()->post(
    'user-service',                    // 服务名
    '/api/users',                     // 接口路径
    [                                  // 请求数据
        'name' => '张三',
        'email' => 'zhangsan@example.com',
        'age' => 25
    ],
    'DEFAULT_GROUP',                  // 分组（可选）
    3                                 // 重试次数（可选）
);
```

#### 3. 调用服务（通用方法）

```php
// 调用服务的PUT接口
$result = $nacos->invoker()->request(
    'PUT',                            // HTTP方法
    'user-service',                    // 服务名
    '/api/users/1',                   // 接口路径
    ['name' => '更新后的名字'],        // 请求数据
    'DEFAULT_GROUP',                  // 分组（可选）
    3                                 // 重试次数（可选）
);
```

#### 4. 获取健康实例

```php
// 单独获取健康实例
$instance = $nacos->invoker()->getHealthyInstance(
    'user-service',
    'DEFAULT_GROUP'
);

if ($instance) {
    echo "找到健康实例！\n";
    echo "IP: " . $instance['ip'] . "\n";
    echo "端口: " . $instance['port'] . "\n";
    echo "元数据: " . json_encode($instance['metadata']) . "\n";
}
```

#### 5. 构建服务URL

```php
// 构建服务URL（不发送请求）
$instance = $nacos->invoker()->getHealthyInstance('user-service');
if ($instance) {
    $url = $nacos->invoker()->buildUrl($instance, '/api/users');
    echo "服务URL: " . $url . "\n";
    // 输出: http://127.0.0.1:8080/api/users
}
```

#### 6. 清除缓存

```php
// 清除指定服务的缓存
$nacos->invoker()->clearCache('user-service');

// 清除所有服务的缓存
$nacos->invoker()->clearCache();
```

#### 7. 配置HTTPS

在注册服务时，通过元数据设置`secure`为`true`来启用HTTPS：

```php
// 注册HTTPS服务
$nacos->discovery()->registerInstance(
    'secure-service',
    '127.0.0.1',
    443,
    'DEFAULT_GROUP',
    ['secure' => 'true']  // 标记为安全服务
);

// 调用时会自动使用HTTPS
$result = $nacos->invoker()->get('secure-service', '/api/data');
```

### Feign风格声明式客户端

FeignClient提供了声明式的API调用方式，类似Java的OpenFeign，让服务调用更加简单直观。

#### 1. 创建Feign客户端

```php
// 创建Feign客户端（声明式）
$userClient = $nacos->feign('user-service');

// 指定分组
$orderClient = $nacos->feign('order-service', 'DEFAULT_GROUP');
```

#### 2. GET请求

```php
// 获取用户列表
$result = $userClient->get('/api/users', [
    'page' => 1,
    'limit' => 10
]);

// 处理响应
if ($result['success']) {
    echo "状态码: " . $result['status_code'] . "\n";
    echo "用户列表: " . json_encode($result['data']) . "\n";
}
```

#### 3. POST请求

```php
// 创建用户
$result = $userClient->post('/api/users', [
    'name' => '张三',
    'email' => 'zhangsan@example.com',
    'age' => 25
]);

if ($result['success']) {
    echo "创建用户成功，ID: " . $result['data']['id'] . "\n";
}
```

#### 4. PUT请求

```php
// 更新用户
$result = $userClient->put('/api/users/1', [
    'name' => '更新后的名字',
    'email' => 'updated@example.com'
]);
```

#### 5. DELETE请求

```php
// 删除用户
$result = $userClient->delete('/api/users/1');
```

#### 6. 通用请求方法

```php
// 使用任意HTTP方法
$result = $userClient->request('PATCH', '/api/users/1', [
    'status' => 'active'
]);
```

#### 7. 自定义重试次数

```php
// 自定义重试次数（默认3次）
$result = $userClient->get('/api/users', ['page' => 1], 5);
```

#### 8. 同时使用多个Feign客户端

```php
// 为不同服务创建不同的Feign客户端
$userClient = $nacos->feign('user-service');
$orderClient = $nacos->feign('order-service');
$productClient = $nacos->feign('product-service');

// 调用不同的服务
$users = $userClient->get('/api/users');
$orders = $orderClient->get('/api/orders');
$products = $productClient->get('/api/products');
```

#### 9. FeignClient与ServiceInvoker对比

**ServiceInvoker方式：**
```php
$result = $nacos->invoker()->get('user-service', '/api/users', ['page' => 1]);
```

**FeignClient方式（推荐）：**
```php
$userClient = $nacos->feign('user-service');
$result = $userClient->get('/api/users', ['page' => 1]);
```

FeignClient方式更加简洁，不需要每次都指定服务名，代码更易读。

### gRPC功能使用

SDK 支持 Nacos 9848 端口的 gRPC 服务，可以通过 gRPC 协议与 Nacos 服务器通信，获得更好的性能。

#### 1. 使用 gRPC 客户端

```php
// 获取 gRPC 客户端
$grpcClient = $nacos->grpc();

// 检查 gRPC 服务是否可用
if ($grpcClient->isAvailable()) {
    echo "gRPC 服务可用！\n";
} else {
    echo "gRPC 服务不可用，将使用 HTTP 协议\n";
}
```

#### 2. 使用 gRPC 进行配置管理

```php
// 发布配置（通过 gRPC）
$result = $nacos->config()->publishConfig(
    'test-config',
    'DEFAULT_GROUP',
    'Hello Nacos gRPC!'
);

// 获取配置（通过 gRPC）
$content = $nacos->config()->getConfig(
    'test-config',
    'DEFAULT_GROUP'
);

// 删除配置（通过 gRPC）
$result = $nacos->config()->deleteConfig(
    'test-config',
    'DEFAULT_GROUP'
);
```

#### 3. 使用 gRPC 进行服务发现

```php
// 注册服务实例（通过 gRPC）
$result = $nacos->discovery()->registerInstance(
    'user-service',
    '127.0.0.1',
    8080,
    'DEFAULT_GROUP',
    ['version' => '1.0.0'],
    10,
    true
);

// 获取服务实例（通过 gRPC）
$instances = $nacos->discovery()->getAllInstances(
    'user-service',
    'DEFAULT_GROUP',
    true
);

// 注销服务实例（通过 gRPC）
$result = $nacos->discovery()->deregisterInstance(
    'user-service',
    '127.0.0.1',
    8080,
    'DEFAULT_GROUP'
);
```

**注意**：SDK 会自动检测 gRPC 服务可用性。如果 gRPC 服务不可用，会自动回退到 HTTP 协议，确保功能正常。

## 测试

SDK提供了完整的测试脚本：

### 分步测试

```bash
php test-step-by-step.php
```

这个脚本会依次测试所有功能：
- 配置管理：发布、获取、删除配置
- 服务发现：注册、获取实例列表、获取健康实例、注销

### 配置监听测试

```bash
php test-config-listener.php
```

这个脚本会演示如何监听配置变更。

### gRPC 功能测试

```bash
php test-grpc.php
```

这个脚本会测试 gRPC 客户端功能，包括：
- gRPC 服务可用性检测
- 配置管理（发布、获取、删除）
- 服务发现（注册、获取实例、注销）

## 系统要求

- PHP >= 7.2
- GuzzleHTTP >= 7.0
- PSR-Log >= 1.1
- Symfony OptionsResolver >= 5.0
- gRPC扩展（可选，用于使用gRPC协议）
- Protobuf扩展（可选，用于使用gRPC协议）

## 启动Nacos服务器

要完全测试SDK功能，需要启动Nacos服务器：

1. 下载Nacos服务器：https://github.com/alibaba/nacos/releases
2. 解压并运行：
   ```bash
   # Linux/Mac
   sh startup.sh -m standalone
   
   # Windows
   cmd startup.cmd -m standalone
   ```
3. 访问 http://localhost:8848/nacos 确认服务器运行
   - 默认用户名：nacos
   - 默认密码：nacos

## 项目结构

```
nacos-sdk/
├── src/
│   ├── Client/
│   │   ├── NacosClient.php       # 核心HTTP客户端
│   │   └── NacosGrpcClient.php   # gRPC客户端
│   ├── Config/
│   │   └── ConfigClient.php      # 配置管理客户端
│   ├── Discovery/
│   │   └── DiscoveryClient.php   # 服务发现客户端
│   ├── Utils/
│   │   ├── ServiceInvoker.php   # 服务调用工具类
│   │   └── FeignClient.php       # Feign风格声明式客户端
│   ├── Exception/
│   │   └── NacosException.php    # 异常类
│   └── Nacos.php                 # 主入口类
├── composer.json                 # Composer配置
├── README.md                     # 使用说明
├── nacos_grpc_service.proto     # gRPC服务定义
├── test-step-by-step.php         # 分步测试脚本
├── test-service-invoker.php       # 服务调用测试脚本
├── test-feign.php                # Feign客户端测试脚本
├── test-config-listener.php      # 配置监听测试脚本
└── test-grpc.php                 # gRPC功能测试脚本
```

## 许可证

MIT License
