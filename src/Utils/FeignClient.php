<?php

namespace Nacos\Utils;

use Nacos\Exception\NacosException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FeignClient
{
    /**
     * @var ServiceInvoker
     */
    private $serviceInvoker;

    /**
     * @var string
     */
    private $serviceName;

    /**
     * @var string
     */
    private $groupName;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * FeignClient constructor.
     * @param ServiceInvoker $serviceInvoker
     * @param string $serviceName
     * @param string $groupName
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        ServiceInvoker $serviceInvoker, 
        string $serviceName, 
        string $groupName = 'DEFAULT_GROUP', 
        ?LoggerInterface $logger = null
    ) {
        $this->serviceInvoker = $serviceInvoker;
        $this->serviceName = $serviceName;
        $this->groupName = $groupName;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 声明式GET请求
     * @param string $path
     * @param array $params
     * @param int $retryCount
     * @return array
     * @throws NacosException
     */
    public function get(string $path, array $params = [], int $retryCount = 3): array
    {
        $this->logger->info('Feign GET request', [
            'service' => $this->serviceName,
            'path' => $path,
            'params' => $params,
        ]);

        return $this->serviceInvoker->get(
            $this->serviceName,
            $path,
            $params,
            $this->groupName,
            $retryCount
        );
    }

    /**
     * 声明式POST请求
     * @param string $path
     * @param array $data
     * @param int $retryCount
     * @return array
     * @throws NacosException
     */
    public function post(string $path, array $data = [], int $retryCount = 3): array
    {
        $this->logger->info('Feign POST request', [
            'service' => $this->serviceName,
            'path' => $path,
            'data' => $data,
        ]);

        return $this->serviceInvoker->post(
            $this->serviceName,
            $path,
            $data,
            $this->groupName,
            $retryCount
        );
    }

    /**
     * 声明式PUT请求
     * @param string $path
     * @param array $data
     * @param int $retryCount
     * @return array
     * @throws NacosException
     */
    public function put(string $path, array $data = [], int $retryCount = 3): array
    {
        $this->logger->info('Feign PUT request', [
            'service' => $this->serviceName,
            'path' => $path,
            'data' => $data,
        ]);

        return $this->serviceInvoker->request(
            'PUT',
            $this->serviceName,
            $path,
            $data,
            $this->groupName,
            $retryCount
        );
    }

    /**
     * 声明式DELETE请求
     * @param string $path
     * @param array $params
     * @param int $retryCount
     * @return array
     * @throws NacosException
     */
    public function delete(string $path, array $params = [], int $retryCount = 3): array
    {
        $this->logger->info('Feign DELETE request', [
            'service' => $this->serviceName,
            'path' => $path,
            'params' => $params,
        ]);

        return $this->serviceInvoker->request(
            'DELETE',
            $this->serviceName,
            $path,
            $params,
            $this->groupName,
            $retryCount
        );
    }

    /**
     * 声明式通用请求
     * @param string $method
     * @param string $path
     * @param array $data
     * @param int $retryCount
     * @return array
     * @throws NacosException
     */
    public function request(string $method, string $path, array $data = [], int $retryCount = 3): array
    {
        $this->logger->info('Feign request', [
            'method' => $method,
            'service' => $this->serviceName,
            'path' => $path,
            'data' => $data,
        ]);

        return $this->serviceInvoker->request(
            $method,
            $this->serviceName,
            $path,
            $data,
            $this->groupName,
            $retryCount
        );
    }

    /**
     * 获取服务名
     * @return string
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * 获取分组名
     * @return string
     */
    public function getGroupName(): string
    {
        return $this->groupName;
    }
}
