<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;
use Nacos\Exception\NacosException;

echo "=== Nacos SDK 分步测试 ===\n\n";

// 初始化Nacos客户端
echo "1. 初始化Nacos客户端...\n";
try {
    $nacos = new Nacos('http://localhost:8848');
    echo "   ✓ 成功初始化\n\n";
} catch (Exception $e) {
    echo "   ✗ 初始化失败: " . $e->getMessage() . "\n";
    exit(1);
}

// ==================== 配置管理测试 ====================
echo "=== 配置管理测试 ===\n\n";

// 1. 发布配置
echo "步骤 1: 发布配置\n";
try {
    $publishResult = $nacos->config()->publishConfig('test-config', 'DEFAULT_GROUP', 'Hello Nacos! This is test content.');
    echo "   结果: " . ($publishResult ? '✓ 成功' : '✗ 失败') . "\n\n";
    sleep(1);
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

// 2. 获取配置
echo "步骤 2: 获取配置\n";
try {
    $content = $nacos->config()->getConfig('test-config', 'DEFAULT_GROUP');
    echo "   内容: " . $content . "\n";
    echo "   ✓ 成功获取配置\n\n";
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

// 3. 删除配置
echo "步骤 3: 删除配置\n";
try {
    $deleteResult = $nacos->config()->deleteConfig('test-config', 'DEFAULT_GROUP');
    echo "   结果: " . ($deleteResult ? '✓ 成功' : '✗ 失败') . "\n\n";
    sleep(1);
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

// ==================== 服务发现测试 ====================
echo "=== 服务发现测试 ===\n\n";

// 清理旧服务
echo "清理旧服务实例...\n";
try {
    $nacos->discovery()->deregisterInstance('test-service', '127.0.0.1', 8080, 'DEFAULT_GROUP');
    sleep(1);
    echo "   ✓ 清理完成\n\n";
} catch (Exception $e) {
    echo "   (服务可能不存在)\n\n";
}

// 1. 注册服务实例（持久化）
echo "步骤 1: 注册服务实例（持久化）\n";
try {
    $registerResult = $nacos->discovery()->registerInstance('test-service', '127.0.0.1', 8080, 'DEFAULT_GROUP', ['version' => '1.0.0'], 10, false);
    echo "   结果: " . ($registerResult ? '✓ 成功' : '✗ 失败') . "\n\n";
    sleep(2);
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

// 2. 获取所有服务实例
echo "步骤 2: 获取所有服务实例\n";
try {
    $instances = $nacos->discovery()->getAllInstances('test-service', 'DEFAULT_GROUP', false);
    echo "   响应: " . json_encode($instances, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    if (isset($instances['hosts'])) {
        echo "   实例数量: " . count($instances['hosts']) . "\n";
    }
    echo "   ✓ 成功获取实例列表\n\n";
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

// 3. 获取单个健康实例
echo "步骤 3: 获取单个健康实例\n";
try {
    $instance = $nacos->discovery()->selectOneHealthyInstance('test-service', 'DEFAULT_GROUP');
    if ($instance) {
        echo "   实例信息: " . json_encode($instance, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        echo "   ✓ 成功获取健康实例\n\n";
    } else {
        echo "   ✗ 未找到健康实例\n\n";
    }
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

// 4. 注销服务实例
echo "步骤 4: 注销服务实例\n";
try {
    $deregisterResult = $nacos->discovery()->deregisterInstance('test-service', '127.0.0.1', 8080, 'DEFAULT_GROUP');
    echo "   结果: " . ($deregisterResult ? '✓ 成功' : '✗ 失败') . "\n\n";
} catch (NacosException $e) {
    echo "   ✗ 失败: " . $e->getMessage() . "\n\n";
}

echo "=== 所有测试步骤完成 ===\n";
