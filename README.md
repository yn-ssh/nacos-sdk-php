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
