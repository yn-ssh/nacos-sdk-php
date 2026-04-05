<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Client\NacosClient;
use Nacos\Exception\NacosException;

echo "=== 调试测试 ===\n\n";

// 初始化Nacos客户端
$client = new NacosClient('http://localhost:8848');

echo "1. 测试获取配置...\n";
try {
    $params = [
        'dataId' => 'test-config-detail',
        'group' => 'DEFAULT_GROUP',
    ];
    
    echo "   参数: " . json_encode($params) . "\n";
    
    $result = $client->get('/nacos/v1/cs/configs', $params);
    echo "   结果: " . $result . "\n";
    echo "   长度: " . strlen($result) . " 字符\n";
    echo "   ✓ 成功获取配置\n\n";
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

echo "2. 测试直接HTTP请求...\n";
try {
    $url = 'http://localhost:8848/nacos/v1/cs/configs?dataId=test-config-detail&group=DEFAULT_GROUP';
    echo "   URL: " . $url . "\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo "   结果: " . $response . "\n";
    echo "   长度: " . strlen($response) . " 字符\n";
    echo "   ✓ 成功获取配置\n\n";
} catch (Exception $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

echo "=== 调试测试完成 ===\n";
