<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;

echo "=== 鉴权功能测试 ===\n\n";

// 测试无鉴权连接
echo "1. 测试无鉴权连接...\n";
try {
    $nacos = new Nacos('http://localhost:8848');
    echo "   ✓ 成功连接（无鉴权）\n";
    echo "   服务器版本: " . $nacos->getClient()->getServerVersion() . "\n\n";
} catch (Exception $e) {
    echo "   ✗ 连接失败: " . $e->getMessage() . "\n\n";
}

// 测试带鉴权连接（如果服务器启用了鉴权）
echo "2. 测试带鉴权连接...\n";
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
} catch (Exception $e) {
    echo "   ⚠ 鉴权连接失败（可能服务器未启用鉴权）: " . $e->getMessage() . "\n\n";
}

echo "=== 鉴权功能测试完成 ===\n";
