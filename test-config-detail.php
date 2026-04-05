<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;
use Nacos\Exception\NacosException;

echo "=== 配置管理详细测试 ===\n\n";

// 初始化Nacos客户端
$nacos = new Nacos('http://localhost:8848');

echo "1. 测试发布配置...\n";
try {
    $publishResult = $nacos->config()->publishConfig('test-config-detail', 'DEFAULT_GROUP', 'Hello Nacos! This is detailed test content.');
    echo "   结果: " . ($publishResult ? '✓ 成功' : '✗ 失败') . "\n";
    // 等待配置同步
    sleep(1);
    echo "   已等待1秒让配置同步\n\n";
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

echo "2. 测试获取配置...\n";
try {
    $content = $nacos->config()->getConfig('test-config-detail', 'DEFAULT_GROUP');
    echo "   配置内容: " . $content . "\n";
    echo "   长度: " . strlen($content) . " 字符\n";
    echo "   ✓ 成功获取配置\n\n";
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

echo "3. 测试配置是否存在...\n";
try {
    $content = $nacos->config()->getConfig('test-config-detail', 'DEFAULT_GROUP');
    if (!empty($content)) {
        echo "   ✓ 配置存在\n";
        echo "   内容: " . $content . "\n\n";
    } else {
        echo "   ✗ 配置不存在或内容为空\n\n";
    }
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

echo "4. 测试更新配置...\n";
try {
    $updateResult = $nacos->config()->publishConfig('test-config-detail', 'DEFAULT_GROUP', 'Updated content: Hello Nacos!');
    echo "   结果: " . ($updateResult ? '✓ 成功' : '✗ 失败') . "\n";
    // 等待配置同步
    sleep(1);
    
    // 验证更新
    $updatedContent = $nacos->config()->getConfig('test-config-detail', 'DEFAULT_GROUP');
    echo "   更新后内容: " . $updatedContent . "\n";
    echo "   ✓ 配置已更新\n\n";
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

echo "5. 测试删除配置...\n";
try {
    $deleteResult = $nacos->config()->deleteConfig('test-config-detail', 'DEFAULT_GROUP');
    echo "   结果: " . ($deleteResult ? '✓ 成功' : '✗ 失败') . "\n";
    // 等待配置同步
    sleep(1);
    echo "   已等待1秒让配置同步\n\n";
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

echo "6. 测试获取已删除的配置...\n";
try {
    $content = $nacos->config()->getConfig('test-config-detail', 'DEFAULT_GROUP');
    if (empty($content)) {
        echo "   ✓ 配置已被删除（返回空内容）\n\n";
    } else {
        echo "   ✗ 配置未被删除，内容: " . $content . "\n\n";
    }
} catch (NacosException $e) {
    echo "   ✓ 配置已被删除（抛出异常）: " . $e->getMessage() . "\n\n";
}

echo "7. 测试使用不同的group...\n";
try {
    // 发布到不同的group
    $nacos->config()->publishConfig('test-config-detail', 'TEST_GROUP', 'Content for test group');
    echo "   ✓ 成功发布到TEST_GROUP\n";
    // 等待配置同步
    sleep(1);
    
    // 获取不同group的配置
    $content = $nacos->config()->getConfig('test-config-detail', 'TEST_GROUP');
    echo "   TEST_GROUP配置: " . $content . "\n";
    
    // 删除
    $nacos->config()->deleteConfig('test-config-detail', 'TEST_GROUP');
    echo "   ✓ 成功删除TEST_GROUP配置\n\n";
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

echo "8. 测试配置监听...\n";
echo "   注意：配置监听是长轮询，会阻塞执行\n";
echo "   按 Ctrl+C 停止\n\n";

try {
    // 先发布一个配置
    $nacos->config()->publishConfig('listen-test', 'DEFAULT_GROUP', 'Initial content');
    echo "   ✓ 已发布初始配置\n";
    
    // 开始监听
    echo "   开始监听配置变更...\n";
    echo "   请在Nacos控制台修改 'listen-test' 配置来测试\n";
    
    $nacos->config()->listenConfig('listen-test', 'DEFAULT_GROUP', function($data) {
        echo "\n✓ 配置变更被监听到!\n";
        echo "  变更数据: " . $data . "\n";
    }, 5); // 5秒超时
    
} catch (NacosException $e) {
    echo "   ✗ 监听失败: " . $e->getMessage() . "\n\n";
} finally {
    // 清理
    try {
        $nacos->config()->deleteConfig('listen-test', 'DEFAULT_GROUP');
        echo "   ✓ 已清理测试配置\n";
    } catch (Exception $e) {
        // 忽略清理错误
    }
}

echo "=== 配置管理详细测试完成 ===\n";
