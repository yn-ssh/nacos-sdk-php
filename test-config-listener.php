<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;
use Nacos\Exception\NacosException;

echo "=== 配置监听测试 ===\n\n";

// 初始化Nacos客户端
$nacos = new Nacos('http://localhost:8848');

try {
    // 先发布一个配置
    $dataId = 'listener-test';
    $group = 'DEFAULT_GROUP';
    $initialContent = 'Initial content';
    
    echo "1. 发布初始配置...\n";
    $nacos->config()->publishConfig($dataId, $group, $initialContent);
    echo "   ✓ 已发布配置: $dataId @ $group\n";
    echo "   初始内容: $initialContent\n";
    sleep(2);
    echo "   已等待2秒让配置同步\n\n";
    
    // 获取配置
    echo "2. 获取当前配置...\n";
    $currentContent = $nacos->config()->getConfig($dataId, $group);
    echo "   当前内容: $currentContent\n\n";
    
    // 开始监听
    echo "3. 开始监听配置变更...\n";
    echo "   请在Nacos控制台修改 '$dataId' 配置来测试监听功能\n";
    echo "   按 Ctrl+C 停止监听\n\n";
    
    $nacos->config()->listenConfig($dataId, $group, function($data) {
        echo "\n✓ 配置变更被监听到!\n";
        echo "  变更数据: " . $data . "\n\n";
    }, 10);
    
} catch (NacosException $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n\n";
} finally {
    // 清理
    try {
        echo "\n清理测试配置...\n";
        $nacos->config()->deleteConfig($dataId, $group);
        echo "✓ 已清理测试配置\n";
    } catch (Exception $e) {
        // 忽略清理错误
    }
}

echo "=== 配置监听测试完成 ===\n";
