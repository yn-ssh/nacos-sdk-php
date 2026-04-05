<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;

echo "=== ServiceInvoker 功能演示 ===\n\n";

// 初始化Nacos客户端
$nacos = new Nacos('http://localhost:8848');

echo "1. 注册测试服务...\n";
try {
    $registerResult = $nacos->discovery()->registerInstance(
        'demo-service', 
        '127.0.0.1', 
        8081, 
        'DEFAULT_GROUP', 
        [
            'version' => '1.0.0',
            'description' => 'Demo service for testing',
            'secure' => 'false'
        ], 
        10,
        false
    );
    echo "   ✓ 服务注册成功\n\n";
    sleep(2);
} catch (Exception $e) {
    echo "   ✗ 服务注册失败: " . $e->getMessage() . "\n\n";
}

echo "2. 获取健康的服务实例...\n";
try {
    $instance = $nacos->invoker()->getHealthyInstance('demo-service');
    if ($instance) {
        echo "   ✓ 找到健康实例\n";
        echo "     IP: " . $instance['ip'] . "\n";
        echo "     端口: " . $instance['port'] . "\n";
        echo "     元数据: " . json_encode($instance['metadata'], JSON_UNESCAPED_UNICODE) . "\n\n";
    } else {
        echo "   ✗ 未找到健康实例\n\n";
    }
} catch (Exception $e) {
    echo "   ✗ 获取实例失败: " . $e->getMessage() . "\n\n";
}

echo "3. 构建服务URL...\n";
try {
    $instance = $nacos->invoker()->getHealthyInstance('demo-service');
    if ($instance) {
        $url = $nacos->invoker()->buildUrl($instance, '/api/users');
        echo "   构建的URL: " . $url . "\n\n";
    }
} catch (Exception $e) {
    echo "   ✗ 构建URL失败: " . $e->getMessage() . "\n\n";
}

echo "4. 演示服务调用（模拟）...\n";
echo "   注意：需要实际的服务运行才能看到真实的调用结果\n";
echo "   服务调用API使用方法：\n";
echo "   - GET请求: \$nacos->invoker()->get('demo-service', '/api/users', ['page' => 1]);\n";
echo "   - POST请求: \$nacos->invoker()->post('demo-service', '/api/users', ['name' => 'test']);\n";
echo "   - 通用请求: \$nacos->invoker()->request('PUT', 'demo-service', '/api/users/1', ['name' => 'updated']);\n\n";

echo "5. 清除服务实例缓存...\n";
$nacos->invoker()->clearCache('demo-service');
echo "   ✓ 缓存已清除\n\n";

echo "6. 注销测试服务...\n";
try {
    $deregisterResult = $nacos->discovery()->deregisterInstance('demo-service', '127.0.0.1', 8081);
    echo "   ✓ 服务注销成功\n\n";
} catch (Exception $e) {
    echo "   ✗ 服务注销失败: " . $e->getMessage() . "\n\n";
}

echo "=== ServiceInvoker 演示完成 ===\n";
echo "\n使用示例：\n";
echo "```php\n";
echo "// 初始化\n";
echo "\$nacos = new Nacos('http://localhost:8848');\n\n";
echo "// 调用服务 - GET请求\n";
echo "\$result = \$nacos->invoker()->get(\n";
echo "    'user-service',\n";
echo "    '/api/users',\n";
echo "    ['page' => 1, 'limit' => 10]\n";
echo ");\n\n";
echo "// 调用服务 - POST请求\n";
echo "\$result = \$nacos->invoker()->post(\n";
echo "    'user-service',\n";
echo "    '/api/users',\n";
echo "    ['name' => '张三', 'email' => 'zhangsan@example.com']\n";
echo ");\n\n";
echo "// 处理响应\n";
echo "if (\$result['success']) {\n";
echo "    echo '状态码: ' . \$result['status_code'] . PHP_EOL;\n";
echo "    echo '数据: ' . json_encode(\$result['data']) . PHP_EOL;\n";
echo "}\n";
echo "```\n";
