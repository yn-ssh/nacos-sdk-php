<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;
use Nacos\Exception\NacosException;

echo "=== 完整配置监听测试 ===\n\n";

// 初始化Nacos客户端
$nacos = new Nacos('http://localhost:8848');

try {
    // 先发布一个配置
    $dataId = 'listener-test-complete';
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
    
    // 在后台进程中修改配置
    echo "3. 启动后台进程修改配置...\n";
    
    // 创建后台脚本来修改配置
    $modifyScript = __DIR__ . '/modify-config-helper.php';
    file_put_contents($modifyScript, <<<'PHP'
<?php
require_once __DIR__ . '/vendor/autoload.php';
use Nacos\Nacos;

// 等待2秒后修改配置
sleep(3);

echo "后台进程: 正在修改配置...\n";
$nacos = new Nacos('http://localhost:8848');
$nacos->config()->publishConfig('listener-test-complete', 'DEFAULT_GROUP', 'Modified content!');
echo "后台进程: 配置已修改\n";
PHP
);
    
    // 启动后台进程
    $bgPid = pcntl_fork();
    if ($bgPid === 0) {
        // 子进程执行修改脚本
        exec("php " . escapeshellarg($modifyScript) . " > /dev/null 2>&1 &");
        exit(0);
    }
    
    echo "   ✓ 后台进程已启动\n\n";
    
    // 开始监听
    echo "4. 开始监听配置变更...\n";
    echo "   等待配置变更...\n";
    
    $changeDetected = false;
    
    $nacos->config()->listenConfig($dataId, $group, function($data) use (&$changeDetected) {
        echo "\n✓ 配置变更被监听到!\n";
        echo "  变更数据: " . $data . "\n";
        $changeDetected = true;
    }, 10);
    
    if ($changeDetected) {
        echo "\n5. 验证修改后的配置...\n";
        $modifiedContent = $nacos->config()->getConfig($dataId, $group);
        echo "   修改后内容: $modifiedContent\n";
        
        if ($modifiedContent === 'Modified content!') {
            echo "   ✓ 配置变更验证成功\n\n";
        }
    } else {
        echo "\n✗ 未检测到配置变更（可能超时）\n\n";
    }
    
} catch (NacosException $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n\n";
} finally {
    // 清理
    try {
        echo "清理测试配置...\n";
        $nacos->config()->deleteConfig($dataId, $group);
        echo "✓ 已清理测试配置\n";
        
        // 删除临时文件
        if (isset($modifyScript) && file_exists($modifyScript)) {
            unlink($modifyScript);
        }
    } catch (Exception $e) {
        // 忽略清理错误
    }
}

echo "=== 完整配置监听测试完成 ===\n";
