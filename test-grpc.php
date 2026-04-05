<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;

// 创建Nacos客户端实例
$nacos = new Nacos(
    'http://localhost:8848', // Nacos服务器地址
    'public', // 命名空间ID
    '', // 访问密钥
    '' // 密钥
);

// 测试gRPC客户端
$grpcClient = $nacos->grpc();

echo "=== 测试gRPC客户端 ===\n";

// 检查gRPC服务是否可用
echo "检查gRPC服务是否可用: " . ($grpcClient->isGrpcAvailable() ? '可用' : '不可用') . "\n";

// 测试配置管理
echo "\n=== 测试配置管理 ===\n";

try {
    // 发布配置
    $publishResult = $nacos->config()->publishConfig('test-grpc-config', 'DEFAULT_GROUP', 'Hello gRPC!');
    echo "发布配置: " . ($publishResult ? '成功' : '失败') . "\n";

    // 获取配置
    $config = $nacos->config()->getConfig('test-grpc-config', 'DEFAULT_GROUP');
    echo "获取配置: $config\n";

    // 监听配置变更
    echo "监听配置变更...\n";
    $nacos->config()->listenConfig('test-grpc-config', 'DEFAULT_GROUP', function($config) {
        echo "配置变更: $config\n";
    });

    // 删除配置
    $deleteResult = $nacos->config()->deleteConfig('test-grpc-config', 'DEFAULT_GROUP');
    echo "删除配置: " . ($deleteResult ? '成功' : '失败') . "\n";
} catch (Exception $e) {
    echo "配置管理测试失败: " . $e->getMessage() . "\n";
}

// 测试服务发现
echo "\n=== 测试服务发现 ===\n";

try {
    // 注册服务实例
    $registerResult = $nacos->discovery()->registerInstance(
        'test-grpc-service',
        '127.0.0.1',
        8080
    );
    echo "注册服务实例: " . ($registerResult ? '成功' : '失败') . "\n";

    // 获取所有实例
    $instances = $nacos->discovery()->getAllInstances('test-grpc-service');
    echo "获取所有实例: " . json_encode($instances) . "\n";

    // 选择一个健康实例
    $instance = $nacos->discovery()->selectOneHealthyInstance('test-grpc-service');
    echo "选择一个健康实例: " . json_encode($instance) . "\n";

    // 发送心跳
    $heartbeatResult = $nacos->discovery()->sendHeartbeat('test-grpc-service', '127.0.0.1', 8080);
    echo "发送心跳: " . ($heartbeatResult ? '成功' : '失败') . "\n";

    // 注销服务实例
    $deregisterResult = $nacos->discovery()->deregisterInstance(
        'test-grpc-service',
        '127.0.0.1',
        8080
    );
    echo "注销服务实例: " . ($deregisterResult ? '成功' : '失败') . "\n";
} catch (Exception $e) {
    echo "服务发现测试失败: " . $e->getMessage() . "\n";
}

echo "\n测试完成！\n";
