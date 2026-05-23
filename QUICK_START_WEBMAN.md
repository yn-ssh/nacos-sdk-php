# Webman + Nacos SDK PHP 快速开始

## 🚀 5 分钟快速上手

### 1. 安装 SDK

```bash
cd your-webman-project
composer require ssh/nacos-sdk-php
```

### 2. 创建配置文件

创建 `config/nacos.php`:

```php
<?php

return [
    'server_url' => env('NACOS_SERVER_URL', 'http://127.0.0.1:8848'),
    'namespace_id' => env('NACOS_NAMESPACE', 'public'),
    'username' => env('NACOS_USERNAME', 'nacos'),
    'password' => env('NACOS_PASSWORD', 'nacos'),
    'grpc_port' => 9848,
];
```

创建 `.env`:

```bash
NACOS_SERVER_URL=http://127.0.0.1:8848
NACOS_USERNAME=nacos
NACOS_PASSWORD=nacos
```

### 3. 创建 Bootstrap（全局单例）

创建 `app/bootstrap/nacos.php`:

```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Nacos\Nacos;
use Webman\Bootstrap;

class NacosBootstrap implements Bootstrap
{
    protected static $nacos = null;

    public static function start($worker)
    {
        if ($worker->id === 0) {
            $config = config('nacos');
            static::$nacos = new Nacos(
                $config['server_url'],
                $config['namespace_id'],
                '',
                '',
                $config['grpc_port'],
                null,
                $config['username'],
                $config['password']
            );
            echo "[Nacos] Initialized: {$config['server_url']}\n";
        }
    }

    public static function getClient(): Nacos
    {
        return static::$nacos;
    }
}
```

在 `config/bootstrap.php` 添加:

```php
return [
    // ...
    app\bootstrap\NacosBootstrap::class,
];
```

---

## 📦 快速使用

### 配置管理

```php
// 在任意控制器或服务中使用
$nacos = NacosBootstrap::getClient();

// 获取配置
$content = $nacos->config()->getConfig('database.php');

// 发布配置
$nacos->config()->publishConfig('app.json', 'DEFAULT_GROUP', json_encode($data));

// 监听配置变更
$nacos->config()->listenConfig('app.json', 'DEFAULT_GROUP', function($data) {
    echo "配置变更: $data\n";
});
```

### 服务发现

```php
$nacos = NacosBootstrap::getClient();

// 注册服务
$nacos->discovery()->registerInstance('my-service', '127.0.0.1', 8080);

// 获取健康实例
$instance = $nacos->discovery()->selectOneHealthyInstance('user-service');

// 注销服务
$nacos->discovery()->deregisterInstance('my-service', '127.0.0.1', 8080);
```

### 服务调用（Feign 风格）

```php
$nacos = NacosBootstrap::getClient();

// 创建 Feign 客户端
$userClient = $nacos->feign('user-service');

// GET 请求
$result = $userClient->get('/api/users/1');
if ($result['success']) {
    $user = $result['data'];
}

// POST 请求
$result = $userClient->post('/api/users', [
    'name' => 'John',
    'email' => 'john@example.com'
]);
```

---

## 🎯 实际示例

### 控制器示例

```php
<?php

namespace app\controller;

use app\bootstrap\NacosBootstrap;
use support\Request;
use support\Response;

class UserController
{
    protected $nacos;

    public function __construct()
    {
        $this->nacos = NacosBootstrap::getClient();
    }

    /**
     * 获取用户列表（调用远程服务）
     */
    public function index(Request $request): Response
    {
        $page = $request->get('page', 1);
        
        // 通过 Feign 调用用户服务
        $userClient = $this->nacos->feign('user-service');
        $result = $userClient->get('/api/users', ['page' => $page]);
        
        return json([
            'code' => 0,
            'data' => $result['success'] ? $result['data'] : [],
        ]);
    }

    /**
     * 获取数据库配置
     */
    public function getDbConfig(): Response
    {
        $config = $this->nacos->config()->getConfig('database.json');
        
        return json([
            'code' => 0,
            'data' => json_decode($config, true),
        ]);
    }

    /**
     * 注册本服务
     */
    public function register(): Response
    {
        $result = $this->nacos->discovery()->registerInstance(
            'user-api',
            '127.0.0.1',
            8080
        );
        
        return json([
            'code' => $result ? 0 : 1,
            'msg' => $result ? '注册成功' : '注册失败',
        ]);
    }
}
```

### 服务类示例

```php
<?php

namespace app\service;

use app\bootstrap\NacosBootstrap;
use PDO;

/**
 * 用户服务
 */
class UserService
{
    protected $nacos;
    protected $db;

    public function __construct()
    {
        $this->nacos = NacosBootstrap::getClient();
        $this->initDb();
    }

    /**
     * 从 Nacos 加载数据库配置
     */
    protected function initDb(): void
    {
        $configContent = $this->nacos->config()->getConfig('database.json');
        $config = json_decode($configContent, true);
        
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );
        
        $this->db = new PDO(
            $dsn,
            $config['username'],
            $config['password']
        );
    }

    /**
     * 获取所有用户
     */
    public function getAll(): array
    {
        return $this->db->query('SELECT * FROM users')->fetchAll();
    }
}
```

---

## ⚡ 路由配置

在 `routes/api.php`:

```php
<?php

use Webman\Route;

// 用户相关
Route::group('/user', function () {
    Route::get('/', [app\controller\UserController::class, 'index']);
    Route::get('/config', [app\controller\UserController::class, 'getDbConfig']);
    Route::post('/register', [app\controller\UserController::class, 'register']);
});
```

---

## 🧪 测试

```bash
# 启动 webman
php webman start

# 测试配置获取
curl http://127.0.0.1:8787/user/config

# 测试服务注册
curl -X POST http://127.0.0.1:8787/user/register
```

---

## 📚 详细文档

完整文档请查看: [WEBMAN_INTEGRATION_GUIDE.md](./WEBMAN_INTEGRATION_GUIDE.md)

包含：
- ✅ 完整的服务封装
- ✅ 中间件配置
- ✅ 定时任务
- ✅ 进程事件
- ✅ 微服务架构示例
- ✅ 生产环境配置
- ✅ 最佳实践
- ✅ 故障排查

---

## 🎉 完成！

现在开始使用 Nacos SDK 吧！

```php
$nacos = NacosBootstrap::getClient();

// 配置管理
$nacos->config()->getConfig('my-config');

// 服务发现
$nacos->discovery()->selectOneHealthyInstance('my-service');

// 服务调用
$nacos->feign('my-service')->get('/api/data');
```
