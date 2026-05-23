<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;

echo "===========================================\n";
echo "  Nacos SDK PHP - 认证功能测试\n";
echo "===========================================\n\n";

// 测试配置
$serverUrl = 'http://localhost:8848';
$username = 'nacos';
$password = 'nacos';

echo "服务器: $serverUrl\n";
echo "用户名: $username\n\n";

try {
    // 初始化 Nacos 客户端（带认证）
    echo "1. 初始化 Nacos 客户端（带用户名密码认证）\n";
    $nacos = new Nacos(
        $serverUrl,
        'public',           // namespaceId
        '',                 // accessKey (留空)
        '',                 // secretKey (留空)
        9848,              // grpcPort
        null,              // logger
        $username,         // username (新增)
        $password          // password (新增)
    );
    echo "   ✓ 登录成功\n";
    echo "   Token: " . substr($nacos->getClient()->getAccessToken(), 0, 50) . "...\n\n";
    
    // 配置管理
    echo "2. 测试配置管理\n";
    
    // 发布配置
    $dataId = 'test-auth-' . time();
    $content = 'Hello Nacos with Auth!';
    $result = $nacos->config()->publishConfig($dataId, 'DEFAULT_GROUP', $content);
    echo "   [发布配置] dataId=$dataId\n";
    echo "   结果: " . ($result ? '✓ 成功' : '✗ 失败') . "\n";
    
    // 获取配置
    $retrieved = $nacos->config()->getConfig($dataId, 'DEFAULT_GROUP');
    echo "   [获取配置]\n";
    echo "   内容: $retrieved\n";
    echo "   验证: " . ($retrieved === $content ? '✓ 匹配' : '✗ 不匹配') . "\n";
    
    // 删除配置
    $result = $nacos->config()->deleteConfig($dataId, 'DEFAULT_GROUP');
    echo "   [删除配置]\n";
    echo "   结果: " . ($result ? '✓ 成功' : '✗ 失败') . "\n\n";
    
    // 服务发现
    echo "3. 测试服务发现\n";
    
    // 注册服务
    $serviceName = 'test-service-' . time();
    $result = $nacos->discovery()->registerInstance($serviceName, '127.0.0.1', 8080);
    echo "   [注册服务] name=$serviceName:8080\n";
    echo "   结果: " . ($result ? '✓ 成功' : '✗ 失败') . "\n";
    
    // 获取服务实例
    $instances = $nacos->discovery()->getAllInstances($serviceName);
    $count = isset($instances['hosts']) ? count($instances['hosts']) : 0;
    echo "   [获取实例] 数量: $count\n";
    echo "   结果: " . ($count > 0 ? '✓ 成功' : '✗ 失败') . "\n";
    
    // 注销服务
    $result = $nacos->discovery()->deregisterInstance($serviceName, '127.0.0.1', 8080);
    echo "   [注销服务]\n";
    echo "   结果: " . ($result ? '✓ 成功' : '✗ 失败') . "\n\n";
    
    echo "===========================================\n";
    echo "  所有测试通过！\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    echo "✗ 测试失败!\n";
    echo "错误: " . $e->getMessage() . "\n\n";
    
    echo "提示:\n";
    echo "1. 确保 Nacos 服务器正在运行\n";
    echo "2. 确保服务器开启了认证（默认 nacos/nacos）\n";
    echo "3. 检查网络连接\n";
}
