<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nacos\Nacos;

echo "=== FeignClient 声明式客户端演示 ===\n\n";

// 初始化Nacos客户端
$nacos = new Nacos('http://localhost:8848');

echo "1. 注册测试服务...\n";
try {
    $registerResult = $nacos->discovery()->registerInstance(
        'user-service', 
        '127.0.0.1', 
        8082, 
        'DEFAULT_GROUP', 
        [
            'version' => '1.0.0',
            'description' => 'User service for Feign demo'
        ], 
        10,
        false
    );
    echo "   ✓ 服务注册成功\n\n";
    sleep(2);
} catch (Exception $e) {
    echo "   ✗ 服务注册失败: " . $e->getMessage() . "\n\n";
}

echo "2. 创建Feign客户端...\n";
try {
    $userClient = $nacos->feign('user-service');
    echo "   ✓ Feign客户端创建成功\n";
    echo "   服务名: " . $userClient->getServiceName() . "\n";
    echo "   分组: " . $userClient->getGroupName() . "\n\n";
} catch (Exception $e) {
    echo "   ✗ Feign客户端创建失败: " . $e->getMessage() . "\n\n";
}

echo "3. 演示Feign API使用方法...\n";
echo "   注意：需要实际的服务运行才能看到真实的调用结果\n\n";
echo "   API使用示例：\n";
echo "   ```php\n";
echo "   // GET请求 - 获取用户列表\n";
echo "   \$result = \$userClient->get('/api/users', ['page' => 1, 'limit' => 10]);\n";
echo "   if (\$result['success']) {\n";
echo "       echo '用户列表: ' . json_encode(\$result['data']);\n";
echo "   }\n";
echo "   ```\n\n";

echo "   ```php\n";
echo "   // POST请求 - 创建用户\n";
echo "   \$result = \$userClient->post('/api/users', [\n";
echo "       'name' => '张三',\n";
echo "       'email' => 'zhangsan@example.com',\n";
echo "       'age' => 25\n";
echo "   ]);\n";
echo "   if (\$result['success']) {\n";
echo "       echo '创建用户成功，ID: ' . \$result['data']['id'];\n";
echo "   }\n";
echo "   ```\n\n";

echo "   ```php\n";
echo "   // PUT请求 - 更新用户\n";
echo "   \$result = \$userClient->put('/api/users/1', [\n";
echo "       'name' => '更新后的名字'\n";
echo "   ]);\n";
echo "   ```\n\n";

echo "   ```php\n";
echo "   // DELETE请求 - 删除用户\n";
echo "   \$result = \$userClient->delete('/api/users/1');\n";
echo "   ```\n\n";

echo "4. 创建多个Feign客户端（演示）...\n";
try {
    $orderClient = $nacos->feign('order-service');
    $productClient = $nacos->feign('product-service');
    
    echo "   ✓ 多个Feign客户端创建成功\n";
    echo "   - 用户服务客户端: " . $userClient->getServiceName() . "\n";
    echo "   - 订单服务客户端: " . $orderClient->getServiceName() . "\n";
    echo "   - 产品服务客户端: " . $productClient->getServiceName() . "\n\n";
} catch (Exception $e) {
    echo "   ✗ 创建失败: " . $e->getMessage() . "\n\n";
}

echo "5. 注销测试服务...\n";
try {
    $deregisterResult = $nacos->discovery()->deregisterInstance('user-service', '127.0.0.1', 8082);
    echo "   ✓ 服务注销成功\n\n";
} catch (Exception $e) {
    echo "   ✗ 服务注销失败: " . $e->getMessage() . "\n\n";
}

echo "=== FeignClient 演示完成 ===\n";
echo "\n完整使用示例：\n";
echo "```php\n";
echo "use Nacos\\Nacos;\n\n";
echo "// 初始化\n";
echo "\$nacos = new Nacos('http://localhost:8848');\n\n";
echo "// 创建Feign客户端（声明式）\n";
echo "\$userClient = \$nacos->feign('user-service');\n\n";
echo "// 直接调用服务，自动处理服务发现、负载均衡、重试等\n";
echo "\$result = \$userClient->get('/api/users', ['page' => 1]);\n\n";
echo "// 处理响应\n";
echo "if (\$result['success']) {\n";
echo "    echo '状态码: ' . \$result['status_code'] . PHP_EOL;\n";
echo "    echo '数据: ' . json_encode(\$result['data']) . PHP_EOL;\n";
echo "}\n";
echo "```\n";
