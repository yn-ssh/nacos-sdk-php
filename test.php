<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;

// Initialize Nacos client
$nacos = new Nacos('http://localhost:8848');

try {
    // Test configuration management
    echo "=== Testing Configuration Management ===\n";
    
    // Publish configuration
    $publishResult = $nacos->config()->publishConfig('test', 'DEFAULT_GROUP', 'Hello Nacos!');
    echo "Publish config result: " . ($publishResult ? 'Success' : 'Failed') . "\n";
    
    // Get configuration
    $content = $nacos->config()->getConfig('test', 'DEFAULT_GROUP');
    echo "Get config result: $content\n";
    
    // Test service discovery
    echo "\n=== Testing Service Discovery ===\n";
    
    // Register service
    $registerResult = $nacos->discovery()->registerInstance('test-service', '127.0.0.1', 8080);
    echo "Register instance result: " . ($registerResult ? 'Success' : 'Failed') . "\n";
    
    // Get all instances
    $instances = $nacos->discovery()->getAllInstances('test-service');
    echo "Get all instances: " . json_encode($instances) . "\n";
    
    // Get one healthy instance
    $instance = $nacos->discovery()->selectOneHealthyInstance('test-service');
    echo "Get one healthy instance: " . json_encode($instance) . "\n";
    
    // Send heartbeat
    $heartbeatResult = $nacos->discovery()->sendHeartbeat('test-service', '127.0.0.1', 8080);
    echo "Send heartbeat result: " . ($heartbeatResult ? 'Success' : 'Failed') . "\n";
    
    // Deregister service
    $deregisterResult = $nacos->discovery()->deregisterInstance('test-service', '127.0.0.1', 8080);
    echo "Deregister instance result: " . ($deregisterResult ? 'Success' : 'Failed') . "\n";
    
    // Delete configuration
    $deleteResult = $nacos->config()->deleteConfig('test', 'DEFAULT_GROUP');
    echo "\nDelete config result: " . ($deleteResult ? 'Success' : 'Failed') . "\n";
    
    echo "\nAll tests completed!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
