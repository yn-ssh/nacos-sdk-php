<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;

echo "=== 测试修复后的认证功能 ===\n\n";

$serverUrl = 'http://localhost:8848';
$username = 'nacos';
$password = 'nacos';

echo "服务器地址: $serverUrl\n";
echo "用户名: $username\n";
echo "密码: $password\n\n";

// 测试 1: 使用用户名密码认证
echo "=== 测试 1: 使用用户名密码认证 ===\n";
try {
    $nacos = new Nacos(
        $serverUrl,
        'public',
        '',  // accessKey (留空)
        '',  // secretKey (留空)
        9848,
        null,
        $username,  // username
        $password   // password
    );
    
    echo "✓ 登录成功\n";
    echo "Token: " . substr($nacos->getClient()->getAccessToken(), 0, 50) . "...\n\n";
    
    // 测试配置管理
    echo "=== 测试 2: 配置管理 ===\n";
    
    // 发布配置
    echo "发布配置...\n";
    $result = $nacos->config()->publishConfig('test-auth-fixed', 'DEFAULT_GROUP', 'test content with auth');
    echo "发布结果: " . ($result ? '✓ 成功' : '✗ 失败') . "\n";
    
    // 获取配置
    echo "获取配置...\n";
    $content = $nacos->config()->getConfig('test-auth-fixed', 'DEFAULT_GROUP');
    echo "配置内容: $content\n";
    
    // 删除配置
    echo "删除配置...\n";
    $result = $nacos->config()->deleteConfig('test-auth-fixed', 'DEFAULT_GROUP');
    echo "删除结果: " . ($result ? '✓ 成功' : '✗ 失败') . "\n\n";
    
    // 测试服务发现
    echo "=== 测试 3: 服务发现 ===\n";
    
    // 注册服务
    echo "注册服务...\n";
    $result = $nacos->discovery()->registerInstance('test-service', '127.0.0.1', 8080);
    echo "注册结果: " . ($result ? '✓ 成功' : '✗ 失败') . "\n";
    
    // 获取服务实例
    echo "获取服务实例...\n";
    $instances = $nacos->discovery()->getAllInstances('test-service');
    echo "实例数量: " . (isset($instances['hosts']) ? count($instances['hosts']) : 0) . "\n";
    
    // 注销服务
    echo "注销服务...\n";
    $result = $nacos->discovery()->deregisterInstance('test-service', '127.0.0.1', 8080);
    echo "注销结果: " . ($result ? '✓ 成功' : '✗ 失败') . "\n\n";
    
    echo "=== 所有测试通过！ ===\n";
    
} catch (Exception $e) {
    echo "✗ 测试失败: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n\n";
}

// 测试 2: 不带认证（应该失败）
echo "=== 测试 4: 不带认证（预期失败） ===\n";
try {
    $nacosNoAuth = new Nacos($serverUrl);
    $result = $nacosNoAuth->config()->publishConfig('test-no-auth', 'DEFAULT_GROUP', 'should fail');
    echo "⚠ 意外成功！这不应该发生\n\n";
} catch (Exception $e) {
    echo "✓ 预期失败: " . $e->getMessage() . "\n";
    echo "这是正确的行为，因为服务器开启了认证\n\n";
}

echo "=== 测试完成 ===\n";
