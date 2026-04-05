<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;
use Nacos\Exception\NacosException;

echo "=== 服务发现详细测试 ===\n\n";

// 初始化Nacos客户端
$nacos = new Nacos('http://localhost:8848');

// 测试服务列表（使用全新的服务名称）
$testServices = [
    'new-test-service' => ['ip' => '127.0.0.1', 'port' => 9080],
    'new-user-service' => ['ip' => '127.0.0.1', 'port' => 9081],
    'new-demo-service' => ['ip' => '127.0.0.1', 'port' => 9082],
];

try {
    // 1. 先清理旧的服务实例（先尝试临时实例，再尝试持久化实例）
    echo "1. 清理旧的服务实例...\n";
    foreach ($testServices as $serviceName => $info) {
        try {
            // 先尝试注销临时实例
            $result = $nacos->discovery()->deregisterInstance(
                $serviceName,
                $info['ip'],
                $info['port'],
                'DEFAULT_GROUP',
                true
            );
            echo "   ✓ 已尝试注销临时实例: $serviceName\n";
        } catch (Exception $e) {
            // 忽略
        }
        try {
            // 再尝试注销持久化实例
            $result = $nacos->discovery()->deregisterInstance(
                $serviceName,
                $info['ip'],
                $info['port'],
                'DEFAULT_GROUP',
                false
            );
            echo "   ✓ 已尝试注销持久化实例: $serviceName\n";
        } catch (Exception $e) {
            // 忽略
        }
    }
    echo "   已完成清理\n\n";
    
    sleep(1);
    
    // 2. 注册服务
    echo "2. 注册服务...\n";
    foreach ($testServices as $serviceName => $info) {
        $result = $nacos->discovery()->registerInstance(
            $serviceName,
            $info['ip'],
            $info['port']
        );
        echo "   " . ($result ? '✓' : '✗') . " 注册服务: $serviceName @ {$info['ip']}:{$info['port']}\n";
    }
    echo "\n";
    
    sleep(2);
    
    // 3. 获取服务实例列表
    echo "3. 获取服务实例列表...\n";
    foreach ($testServices as $serviceName => $info) {
        $instances = $nacos->discovery()->getAllInstances($serviceName, 'DEFAULT_GROUP', false);
        $count = isset($instances['hosts']) ? count($instances['hosts']) : 0;
        echo "   $serviceName: 找到 $count 个实例\n";
        if (isset($instances['hosts']) && is_array($instances['hosts'])) {
            foreach ($instances['hosts'] as $instance) {
                echo "      - {$instance['ip']}:{$instance['port']} (健康: " . ($instance['healthy'] ? '是' : '否') . ", 临时: " . ($instance['ephemeral'] ? '是' : '否') . ")\n";
            }
        }
    }
    echo "\n";
    
    // 4. 选择健康的服务实例
    echo "4. 选择健康的服务实例...\n";
    foreach ($testServices as $serviceName => $info) {
        $instance = $nacos->discovery()->selectOneHealthyInstance($serviceName);
        if ($instance) {
            echo "   $serviceName: 选中 {$instance['ip']}:{$instance['port']}\n";
        } else {
            echo "   $serviceName: 没有找到健康实例\n";
        }
    }
    echo "\n";
    
    // 5. 注销服务
    echo "5. 注销服务...\n";
    foreach ($testServices as $serviceName => $info) {
        $result = $nacos->discovery()->deregisterInstance(
            $serviceName,
            $info['ip'],
            $info['port']
        );
        echo "   " . ($result ? '✓' : '✗') . " 注销服务: $serviceName @ {$info['ip']}:{$info['port']}\n";
    }
    echo "\n";
    
    sleep(1);
    
    // 6. 验证服务是否已注销
    echo "6. 验证服务是否已注销...\n";
    foreach ($testServices as $serviceName => $info) {
        $instances = $nacos->discovery()->getAllInstances($serviceName, 'DEFAULT_GROUP', false);
        $count = isset($instances['hosts']) ? count($instances['hosts']) : 0;
        echo "   $serviceName: " . ($count === 0 ? '✓ 已注销' : "✗ 还有 $count 个实例") . "\n";
    }
    echo "\n";
    
} catch (NacosException $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n\n";
}

echo "=== 服务发现详细测试完成 ===\n";
