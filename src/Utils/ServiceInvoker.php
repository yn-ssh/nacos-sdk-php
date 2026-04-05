<?php

namespace Nacos\Utils;

use GuzzleHttp\Client;
use Nacos\Discovery\DiscoveryClient;
use Nacos\Exception\NacosException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ServiceInvoker
{
    /**
     * @var DiscoveryClient
     */
    private $discoveryClient;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $instanceCache = [];

    /**
     * ServiceInvoker constructor.
     * @param DiscoveryClient $discoveryClient
     * @param LoggerInterface|null $logger
     */
    public function __construct(DiscoveryClient $discoveryClient, ?LoggerInterface $logger = null)
    {
        $this->discoveryClient = $discoveryClient;
        $this->logger = $logger ?? new NullLogger();
        $this->httpClient = new Client([
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * 获取健康的服务实例
     * @param string $serviceName
     * @param string $group
     * @return array|null
     * @throws NacosException
     */
    public function getHealthyInstance(string $serviceName, string $group = 'DEFAULT_GROUP'): ?array
    {
        $cacheKey = $serviceName . '_' . $group;
        $now = time();
        
        // 检查缓存是否有效（30秒缓存）
        if (isset($this->instanceCache[$cacheKey]) && 
            ($now - $this->instanceCache[$cacheKey]['timestamp'] < 30)) {
            return $this->instanceCache[$cacheKey]['instance'];
        }
        
        // 从Nacos获取健康实例
        $instance = $this->discoveryClient->selectOneHealthyInstance($serviceName, $group);
        
        if ($instance) {
            // 缓存实例
            $this->instanceCache[$cacheKey] = [
                'instance' => $instance,
                'timestamp' => $now,
            ];
            $this->logger->info('Selected healthy instance', [
                'service' => $serviceName,
                'ip' => $instance['ip'],
                'port' => $instance['port'],
            ]);
        }
        
        return $instance;
    }

    /**
     * 构建服务URL
     * @param array $instance
     * @param string $path
     * @return string
     */
    public function buildUrl(array $instance, string $path = '/'): string
    {
        $scheme = isset($instance['metadata']['secure']) && $instance['metadata']['secure'] === 'true' 
            ? 'https' 
            : 'http';
        
        $ip = $instance['ip'];
        $port = $instance['port'];
        $path = ltrim($path, '/');
        
        return "{$scheme}://{$ip}:{$port}/{$path}";
    }

    /**
     * 调用服务（GET方法）
     * @param string $serviceName
     * @param string $path
     * @param array $params
     * @param string $group
     * @param int $retryCount
     * @return array
     * @throws NacosException
     */
    public function get(
        string $serviceName, 
        string $path, 
        array $params = [], 
        string $group = 'DEFAULT_GROUP', 
        int $retryCount = 3
    ): array {
        return $this->request('GET', $serviceName, $path, $params, $group, $retryCount);
    }

    /**
     * 调用服务（POST方法）
     * @param string $serviceName
     * @param string $path
     * @param array $data
     * @param string $group
     * @param int $retryCount
     * @return array
     * @throws NacosException
     */
    public function post(
        string $serviceName, 
        string $path, 
        array $data = [], 
        string $group = 'DEFAULT_GROUP', 
        int $retryCount = 3
    ): array {
        return $this->request('POST', $serviceName, $path, $data, $group, $retryCount);
    }

    /**
     * 调用服务（通用方法）
     * @param string $method
     * @param string $serviceName
     * @param string $path
     * @param array $data
     * @param string $group
     * @param int $retryCount
     * @return array
     * @throws NacosException
     */
    public function request(
        string $method, 
        string $serviceName, 
        string $path, 
        array $data = [], 
        string $group = 'DEFAULT_GROUP', 
        int $retryCount = 3
    ): array {
        $lastException = null;
        
        for ($i = 0; $i < $retryCount; $i++) {
            try {
                // 获取健康的服务实例
                $instance = $this->getHealthyInstance($serviceName, $group);
                
                if (!$instance) {
                    throw new NacosException("No healthy instance found for service: {$serviceName}");
                }
                
                // 构建请求URL
                $url = $this->buildUrl($instance, $path);
                
                $this->logger->info('Calling service', [
                    'method' => $method,
                    'url' => $url,
                    'attempt' => $i + 1,
                ]);
                
                // 发送请求
                $options = [];
                if ($method === 'GET') {
                    $options['query'] = $data;
                } else {
                    $options['json'] = $data;
                }
                
                $response = $this->httpClient->request($method, $url, $options);
                $body = $response->getBody()->getContents();
                
                $this->logger->info('Service call successful', [
                    'url' => $url,
                    'status' => $response->getStatusCode(),
                ]);
                
                return [
                    'success' => true,
                    'status_code' => $response->getStatusCode(),
                    'data' => json_decode($body, true),
                    'raw' => $body,
                ];
            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->warning('Service call failed, retrying...', [
                    'error' => $e->getMessage(),
                    'attempt' => $i + 1,
                    'max_attempts' => $retryCount,
                ]);
                
                // 清除缓存，下次获取新的实例
                $cacheKey = $serviceName . '_' . $group;
                unset($this->instanceCache[$cacheKey]);
                
                // 如果不是最后一次重试，等待一下再试
                if ($i < $retryCount - 1) {
                    usleep(500000); // 0.5秒
                }
            }
        }
        
        // 所有重试都失败了
        $this->logger->error('All service call attempts failed', [
            'service' => $serviceName,
            'error' => $lastException->getMessage(),
        ]);
        
        throw new NacosException(
            "Failed to call service {$serviceName} after {$retryCount} attempts: " . $lastException->getMessage(),
            500,
            $lastException
        );
    }

    /**
     * 清除实例缓存
     * @param string|null $serviceName
     * @param string $group
     */
    public function clearCache(?string $serviceName = null, string $group = 'DEFAULT_GROUP'): void
    {
        if ($serviceName) {
            $cacheKey = $serviceName . '_' . $group;
            unset($this->instanceCache[$cacheKey]);
            $this->logger->info('Cleared cache for service', ['service' => $serviceName]);
        } else {
            $this->instanceCache = [];
            $this->logger->info('Cleared all service caches');
        }
    }
}
