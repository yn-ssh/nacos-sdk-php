<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "=== 清理持久化服务 ===\n\n";

// 要清理的服务列表
$servicesToClean = [
    'test-service',
    'user-service',
    'demo-service',
];

foreach ($servicesToClean as $serviceName) {
    echo "清理服务: $serviceName\n";
    
    // 先用curl获取所有实例
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost:8848/nacos/v1/ns/instance/list?serviceName=$serviceName");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['hosts']) && is_array($data['hosts'])) {
        foreach ($data['hosts'] as $host) {
            echo "  注销实例: {$host['ip']}:{$host['port']}\n";
            
            $deleteCh = curl_init();
            curl_setopt($deleteCh, CURLOPT_URL, 'http://localhost:8848/nacos/v1/ns/instance');
            curl_setopt($deleteCh, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($deleteCh, CURLOPT_POSTFIELDS, http_build_query([
                'serviceName' => $serviceName,
                'ip' => $host['ip'],
                'port' => $host['port'],
                'ephemeral' => 'false',
            ]));
            curl_setopt($deleteCh, CURLOPT_RETURNTRANSFER, true);
            $deleteResult = curl_exec($deleteCh);
            curl_close($deleteCh);
            
            echo "    结果: " . var_export($deleteResult, true) . "\n";
        }
    }
    echo "\n";
}

echo "=== 清理完成 ===\n";
