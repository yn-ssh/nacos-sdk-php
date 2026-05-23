<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;

try {
    // 这里使用默认的nacos/nacos作为示例
    // 实际使用时需要根据Nacos服务器的配置进行修改
    $nacosWithAuth = new Nacos(
        'http://localhost:8848',
        'public',
        'nacos',  // accessKey
        'nacos'   // secretKey
    );
    echo "   ✓ 成功连接（带鉴权）\n\n";
    echo "   服务器版本: " . $nacosWithAuth->getClient()->getServerVersion() . "\n\n";
    // Test configuration management
    echo "=== Testing Configuration Management ===\n";

    // Publish configuration
    $publishResult = $nacosWithAuth->config()->publishConfig('test', 'DEFAULT_GROUP', 'Hello Nacos!');
    echo "Publish config result: " . ($publishResult ? 'Success' : 'Failed') . "\n";

    // Get configuration
    $content = $nacosWithAuth->config()->getConfig('test', 'DEFAULT_GROUP');
    echo "Get config result: $content\n";

    // Test service discovery
    echo "\n=== Testing Service Discovery ===\n";

    // Register service
    $registerResult = $nacosWithAuth->discovery()->registerInstance('test-service', '127.0.0.1', 8080);
    echo "Register instance result: " . ($registerResult ? 'Success' : 'Failed') . "\n";

    // Get all instances
    $instances = $nacosWithAuth->discovery()->getAllInstances('test-service');
    echo "Get all instances: " . json_encode($instances) . "\n";

    // Get one healthy instance
    $instance = $nacosWithAuth->discovery()->selectOneHealthyInstance('test-service');
    echo "Get one healthy instance: " . json_encode($instance) . "\n";

    // Send heartbeat
    $heartbeatResult = $nacosWithAuth->discovery()->sendHeartbeat('test-service', '127.0.0.1', 8080);
    echo "Send heartbeat result: " . ($heartbeatResult ? 'Success' : 'Failed') . "\n";

    // Deregister service
    $deregisterResult = $nacosWithAuth->discovery()->deregisterInstance('test-service', '127.0.0.1', 8080);
    echo "Deregister instance result: " . ($deregisterResult ? 'Success' : 'Failed') . "\n";

    // Delete configuration
    $deleteResult = $nacosWithAuth->config()->deleteConfig('test', 'DEFAULT_GROUP');
    echo "\nDelete config result: " . ($deleteResult ? 'Success' : 'Failed') . "\n";

    echo "\nAll tests completed!\n";
} catch (Exception $e) {
    echo "   ⚠ 鉴权连接失败（可能服务器未启用鉴权）: " . $e->getMessage() . "\n\n";
}

