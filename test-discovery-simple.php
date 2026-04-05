<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;
use Nacos\Exception\NacosException;

echo "=== 简化服务发现测试 ===\n\n";

// 初始化Nacos客户端
$nacos = new Nacos('http://localhost:8848');

try {
    // 测试1：使用SDK注册
    echo "1. 使用SDK注册服务...\n";
    try {
        $result = $nacos->discovery()->registerInstance(
            'test-sdk-service',
            '127.0.0.1',
            8888
        );
        echo "   " . ($result ? '✓' : '✗') . " SDK注册: " . var_export($result, true) . "\n";
    } catch (Exception $e) {
        echo "   ✗ SDK注册失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // 测试2：直接使用curl注册
    echo "2. 使用curl注册服务...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8848/nacos/v1/ns/instance');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'serviceName' => 'test-curl-service',
        'ip' => '127.0.0.1',
        'port' => 9999,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $curlResult = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "   curl注册结果: HTTP $httpCode, 响应: " . var_export($curlResult, true) . "\n";
    
    echo "\n";
    
    // 测试3：获取服务实例
    echo "3. 获取服务实例...\n";
    $instances = $nacos->discovery()->getAllInstances('test-curl-service', 'DEFAULT_GROUP', false);
    echo "   实例信息: " . json_encode($instances, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    echo "\n";
    
    // 清理
    echo "4. 清理服务...\n";
    $nacos->discovery()->deregisterInstance('test-curl-service', '127.0.0.1', 9999);
    echo "   已清理 test-curl-service\n";
    
    try {
        $nacos->discovery()->deregisterInstance('test-sdk-service', '127.0.0.1', 8888);
        echo "   已清理 test-sdk-service\n";
    } catch (Exception $e) {
        // 忽略
    }
    
} catch (Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== 简化服务发现测试完成 ===\n";
