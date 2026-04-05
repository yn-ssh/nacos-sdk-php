<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;
use Nacos\Exception\NacosException;

echo "=== 简单配置监听测试 ===\n\n";

// 初始化Nacos客户端
$nacos = new Nacos('http://localhost:8848');

try {
    // 先发布一个配置
    $dataId = 'listener-test-simple';
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
    
    echo "3. 测试说明...\n";
    echo "   配置监听是一个长轮询操作，\n";
    echo "   我们将在后台修改配置，然后监听变更。\n\n";
    
    // 创建修改配置的脚本
    $modifyScript = __DIR__ . '/modify-config.php';
    file_put_contents($modifyScript, <<<'PHP'
<?php
require_once __DIR__ . '/vendor/autoload.php';
use Nacos\Nacos;

echo "后台进程: 启动\n";

// 等待3秒后修改配置
sleep(3);

echo "后台进程: 正在修改配置...\n";
try {
    $nacos = new Nacos('http://localhost:8848');
    $result = $nacos->config()->publishConfig('listener-test-simple', 'DEFAULT_GROUP', 'Modified content!');
    echo "后台进程: 配置修改" . ($result ? '成功' : '失败') . "\n";
} catch (Exception $e) {
    echo "后台进程: 错误 - " . $e->getMessage() . "\n";
}
PHP
);
    
    echo "4. 启动后台修改进程...\n";
    $bgCmd = PHP_BINARY . ' ' . escapeshellarg($modifyScript);
    $bgPid = proc_open($bgCmd, [], $pipes);
    echo "   ✓ 后台进程已启动\n\n";
    
    // 开始监听
    echo "5. 开始监听配置变更（超时10秒）...\n";
    echo "   等待配置变更...\n";
    
    $changeDetected = false;
    
    $nacos->config()->listenConfig($dataId, $group, function($data) use (&$changeDetected) {
        echo "\n✓ 配置变更被监听到!\n";
        echo "  变更数据: " . $data . "\n";
        $changeDetected = true;
    }, 10);
    
    // 清理后台进程
    if (is_resource($bgPid)) {
        proc_terminate($bgPid);
        proc_close($bgPid);
    }
    
    if ($changeDetected) {
        echo "\n6. 验证修改后的配置...\n";
        $modifiedContent = $nacos->config()->getConfig($dataId, $group);
        echo "   修改后内容: $modifiedContent\n";
        
        if ($modifiedContent === 'Modified content!') {
            echo "   ✓ 配置变更验证成功\n\n";
        }
    } else {
        echo "\n✗ 未检测到配置变更（可能超时）\n";
        echo "  让我们直接检查配置是否被修改了...\n";
        
        $checkContent = $nacos->config()->getConfig($dataId, $group);
        echo "  检查配置内容: $checkContent\n";
        
        if ($checkContent === 'Modified content!') {
            echo "  ✓ 配置已被后台进程成功修改\n\n";
        }
    }
    
} catch (NacosException $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n\n";
} finally {
    // 清理
    try {
        echo "清理测试配置...\n";
        if (isset($dataId) && isset($group)) {
            $nacos->config()->deleteConfig($dataId, $group);
            echo "✓ 已清理测试配置\n";
        }
        
        // 删除临时文件
        if (isset($modifyScript) && file_exists($modifyScript)) {
            unlink($modifyScript);
        }
    } catch (Exception $e) {
        // 忽略清理错误
    }
}

echo "=== 简单配置监听测试完成 ===\n";
