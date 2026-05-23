# Nacos SDK PHP 认证功能修复说明

## 问题描述

当 Nacos 服务器开启认证后，原 SDK 无法正常工作，报错：
```
403 Forbidden: user not found!
```

## 问题原因

1. **认证机制不匹配**：原 SDK 只实现了 AK/SK 签名认证，但大多数 Nacos 用户使用的是简单的用户名密码认证
2. **API 路径错误**：`/nacos/v2/cs/config` 应改为 `/nacos/v1/cs/configs`

## 修复内容

### 1. NacosClient.php 修改

- ✅ 添加 `username` 和 `password` 字段
- ✅ 添加 `accessToken` 和 `tokenExpireTime` 字段
- ✅ 实现 `login()` 方法：调用 `/nacos/v1/auth/login` 获取 token
- ✅ 实现 `refreshTokenIfNeeded()` 方法：自动刷新过期 token
- ✅ 修改 `request()` 方法：自动添加 accessToken 到请求参数
- ✅ 添加 `getUsername()` 和 `getAccessToken()` getter 方法

### 2. Nacos.php 修改

- ✅ 构造函数添加 `username` 和 `password` 参数
- ✅ 传递参数给 NacosClient

### 3. ConfigClient.php 修改

- ✅ 修复 API 路径：`/nacos/v2/cs/config` → `/nacos/v1/cs/configs`

## 使用方法

### 基本用法（推荐）

```php
use Nacos\Nacos;

$nacos = new Nacos(
    'http://localhost:8848',  // 服务器地址
    'public',                 // namespaceId
    '',                       // accessKey (留空)
    '',                       // secretKey (留空)
    9848,                    // grpcPort
    null,                    // logger
    'nacos',                 // username (新增)
    'nacos'                  // password (新增)
);

// 现在所有操作都会自动使用认证
$config = $nacos->config()->getConfig('my-config', 'DEFAULT_GROUP');
```

### 原有 AK/SK 认证

仍然支持原有的 AK/SK 签名认证方式：

```php
$nacos = new Nacos(
    'http://localhost:8848',
    'public',
    'your-access-key',       // accessKey
    'your-secret-key',       // secretKey
    9848,
    null,
    '',                      // username (留空)
    ''                       // password (留空)
);
```

## 认证流程

1. **初始化时登录**
   - 如果提供了 `username` 和 `password`，自动调用 `/nacos/v1/auth/login`
   - 获取 `accessToken` 并设置过期时间（tokenTtl - 5分钟）

2. **请求时自动添加 Token**
   - 每次请求前检查 token 是否过期
   - 如果 token 即将过期，自动重新登录
   - 自动将 `accessToken` 参数添加到请求中

3. **Token 刷新机制**
   - Token 过期前 5 分钟自动刷新
   - 确保请求不会因 token 过期而失败

## 测试验证

运行认证测试：

```bash
php test-auth-complete.php
```

测试内容：
- ✅ 用户名密码登录
- ✅ Token 自动管理
- ✅ 配置发布/读取/删除
- ✅ 服务注册/发现/注销

## API 路径修复

### 修复前
- Config: `/nacos/v2/cs/config`

### 修复后
- Config: `/nacos/v1/cs/configs`

## 兼容性

- ✅ 保持向后兼容
- ✅ 支持原有的 AK/SK 认证
- ✅ 支持新的用户名密码认证
- ✅ 自动检测最佳认证方式

## 注意事项

1. **用户名密码认证**和 **AK/SK 认证**是互斥的，不能同时使用
2. 建议使用用户名密码认证，因为它更简单直观
3. Token 默认有效期为 5 小时（18000秒），SDK 会在过期前自动刷新
4. 如果 Nacos 服务器未开启认证，不提供 username 和 password 即可

## 日志输出

SDK 会输出认证相关信息：

```
Login successful, token obtained
Login failed: no accessToken in response
Login failed: [错误信息]
```

可以通过实现 `Psr\Log\LoggerInterface` 接口来捕获日志：
```php
$logger = new MyLogger();
$nacos = new Nacos($serverUrl, 'public', '', '', 9848, $logger, 'nacos', 'nacos');
```

## 总结

修复后，SDK 完全支持 Nacos 服务端开启认证的情况，并且：
- ✅ 自动处理登录流程
- ✅ 自动管理 Token 生命周期
- ✅ 自动刷新过期 Token
- ✅ 同时支持多种认证方式
- ✅ 保持向后兼容
