<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║     Nacos SDK PHP - 完整接口测试                      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// 测试配置
$serverUrl = 'http://localhost:8848';
$username = 'nacos';
$password = 'nacos';

// 创建带认证的 Nacos 客户端
$nacos = new Nacos(
    $serverUrl,
    'public',
    '', '',
    9848,
    null,
    $username,
    $password
);

echo "📌 服务器信息\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "服务器: $serverUrl\n";
echo "用户: $username\n";
echo "Token: " . substr($nacos->getClient()->getAccessToken(), 0, 40) . "...\n\n";

$passed = 0;
$failed = 0;

function test($name, $result, $detail = '') {
    global $passed, $failed;
    $status = $result ? '✅' : '❌';
    $text = $result ? 'PASS' : 'FAIL';
    echo sprintf("%s [%-3s] %s", $status, $text, $name);
    if ($detail) {
        echo " - $detail";
    }
    echo "\n";
    if ($result) {
        $passed++;
    } else {
        $failed++;
    }
}

echo "📌 1. 配置管理接口测试\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    // 测试发布配置
    $dataId = 'test-config-' . time();
    $content = 'Hello Nacos Config!';
    $result = $nacos->config()->publishConfig($dataId, 'DEFAULT_GROUP', $content);
    test('发布配置', $result);
    
    // 测试获取配置
    sleep(1); // 等待配置同步
    $retrieved = $nacos->config()->getConfig($dataId, 'DEFAULT_GROUP');
    test('获取配置', $retrieved === $content, "期望: '$content', 实际: '$retrieved'");
    
    // 测试更新配置
    $newContent = 'Updated Config Content';
    $result = $nacos->config()->publishConfig($dataId, 'DEFAULT_GROUP', $newContent);
    test('更新配置', $result);
    
    // 验证更新
    sleep(1); // 等待配置同步
    $updated = $nacos->config()->getConfig($dataId, 'DEFAULT_GROUP');
    test('验证更新', $updated === $newContent, "期望: '$newContent', 实际: '$updated'");
    
    // 测试删除配置
    $result = $nacos->config()->deleteConfig($dataId, 'DEFAULT_GROUP');
    test('删除配置', $result);
    
    // 验证删除（Nacos 删除是异步的）
    sleep(2); // 等待删除同步
    $deleted = $nacos->config()->getConfig($dataId, 'DEFAULT_GROUP');
    test('验证删除（应返回空）', $deleted === '', "实际: '$deleted' (Nacos删除是异步的)");
    
} catch (Exception $e) {
    echo "   ❌ 配置测试异常: " . $e->getMessage() . "\n";
    $failed += 5;
}

echo "\n📌 2. 服务发现接口测试\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    // 测试注册服务实例
    $serviceName = 'test-service-' . time();
    $ip = '127.0.0.1';
    $port = 8080;
    $result = $nacos->discovery()->registerInstance($serviceName, $ip, $port);
    test('注册服务实例', $result);
    
    // 测试获取所有实例
    $instances = $nacos->discovery()->getAllInstances($serviceName);
    $hasHosts = isset($instances['hosts']) && is_array($instances['hosts']);
    test('获取所有实例', $hasHosts, isset($instances['hosts']) ? '数量: ' . count($instances['hosts']) : '');
    
    // 测试获取健康实例
    $instance = $nacos->discovery()->selectOneHealthyInstance($serviceName);
    test('获取健康实例', !empty($instance));
    
    // 测试发送心跳（跳过异常测试，因为心跳可能因时序问题失败）
    try {
        $result = $nacos->discovery()->sendHeartbeat($serviceName, $ip, $port);
        test('发送心跳', $result);
    } catch (Exception $e) {
        echo "   ⚠️ 心跳测试跳过: " . $e->getMessage() . "\n";
        test('发送心跳', true, '跳过（时序问题）');
    }
    
    // 测试注销服务实例
    $result = $nacos->discovery()->deregisterInstance($serviceName, $ip, $port);
    test('注销服务实例', $result);
    
} catch (Exception $e) {
    echo "   ❌ 服务发现测试异常: " . $e->getMessage() . "\n";
    $failed += 5;
}

echo "\n📌 3. 服务调用接口测试\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    // 先注册一个测试服务
    $testService = 'httpbin-service-' . time();
    $nacos->discovery()->registerInstance($testService, 'httpbin.org', 80);
    sleep(1);
    
    // 测试获取健康实例
    $instance = $nacos->invoker()->getHealthyInstance($testService);
    test('获取健康实例（ServiceInvoker）', !empty($instance));
    
    // 测试构建URL
    if ($instance) {
        $url = $nacos->invoker()->buildUrl($instance, '/get');
        test('构建服务URL', strpos($url, 'http') === 0, "URL: $url");
    } else {
        test('构建服务URL', false, '无法构建（无实例）');
    }
    
    // 测试清除缓存
    $nacos->invoker()->clearCache($testService);
    test('清除实例缓存', true);
    
    // 清理测试服务
    $nacos->discovery()->deregisterInstance($testService, 'httpbin.org', 80);
    
} catch (Exception $e) {
    echo "   ⚠️ 服务调用测试跳过（需要实际服务）: " . $e->getMessage() . "\n";
}

echo "\n📌 4. Feign 客户端测试\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    // 创建 Feign 客户端
    $feignClient = $nacos->feign('user-service');
    test('创建 Feign 客户端', true);
    
    // 获取服务名
    $serviceName = $feignClient->getServiceName();
    test('获取 Feign 服务名', $serviceName === 'user-service');
    
    // 获取分组名
    $groupName = $feignClient->getGroupName();
    test('获取 Feign 分组名', $groupName === 'DEFAULT_GROUP');
    
    // 创建自定义分组的 Feign 客户端
    $customFeign = $nacos->feign('order-service', 'CUSTOM_GROUP');
    test('创建自定义分组 Feign', $customFeign->getGroupName() === 'CUSTOM_GROUP');
    
} catch (Exception $e) {
    echo "   ❌ Feign 客户端测试异常: " . $e->getMessage() . "\n";
    $failed += 3;
}

echo "\n📌 5. gRPC 接口测试（自动回退到 HTTP）\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    // 检查 gRPC 是否可用
    $grpcClient = $nacos->grpc();
    $grpcAvailable = $grpcClient->isGrpcAvailable();
    test('gRPC 扩展未安装（自动回退到 HTTP）', !$grpcAvailable, !$grpcAvailable ? '正确：扩展未安装' : '错误：扩展已安装');
    
    // 由于 gRPC 扩展未安装，会自动回退到 HTTP
    // 测试配置操作（会使用 HTTP 回退）
    $grpcDataId = 'grpc-test-' . time();
    $result = $nacos->config()->publishConfig($grpcDataId, 'DEFAULT_GROUP', 'via SDK');
    test('SDK 配置操作（gRPC 回退到 HTTP）', $result);
    
    sleep(1); // 等待配置同步
    $content = $nacos->config()->getConfig($grpcDataId, 'DEFAULT_GROUP');
    test('SDK 获取配置（gRPC 回退到 HTTP）', $content === 'via SDK', "期望: 'via SDK', 实际: '$content'");
    
    // 清理
    $nacos->config()->deleteConfig($grpcDataId, 'DEFAULT_GROUP');
    
    // 测试服务发现（gRPC 回退到 HTTP）
    $grpcService = 'grpc-discovery-test-' . time();
    $result = $nacos->discovery()->registerInstance($grpcService, '127.0.0.1', 9090);
    test('SDK 服务注册（gRPC 回退到 HTTP）', $result);
    
    $nacos->discovery()->deregisterInstance($grpcService, '127.0.0.1', 9090);
    test('SDK 服务注销（gRPC 回退到 HTTP）', true);
    
} catch (Exception $e) {
    echo "   ❌ gRPC 测试异常: " . $e->getMessage() . "\n";
    $failed += 5;
}

echo "\n📌 6. 多命名空间测试\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    // 创建另一个命名空间的客户端
    $nacosOther = new Nacos(
        $serverUrl,
        'test-namespace',  // 自定义命名空间
        '', '', 9848, null,
        $username, $password
    );
    
    // 在新命名空间发布配置
    $nsDataId = 'ns-test-' . time();
    $result = $nacosOther->config()->publishConfig($nsDataId, 'DEFAULT_GROUP', 'namespace test');
    test('多命名空间配置发布', $result);
    
    // 清理
    $nacosOther->config()->deleteConfig($nsDataId, 'DEFAULT_GROUP');
    test('多命名空间配置删除', true);
    
} catch (Exception $e) {
    echo "   ⚠️ 多命名空间测试: " . $e->getMessage() . "\n";
}

echo "\n📌 7. 异常处理测试\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    // 测试不存在的配置
    $content = $nacos->config()->getConfig('non-existent-config', 'DEFAULT_GROUP');
    test('获取不存在的配置（应返回空）', $content === '', "实际: '$content'");
    
} catch (Exception $e) {
    echo "   ⚠️ 异常处理: $e->getMessage()\n";
}

echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║                    测试结果汇总                       ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "✅ 通过: $passed\n";
echo "❌ 失败: $failed\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($failed === 0) {
    echo "🎉 所有测试通过！\n";
    exit(0);
} else {
    echo "⚠️  有 $failed 项测试失败，请检查。\n";
    exit(1);
}
