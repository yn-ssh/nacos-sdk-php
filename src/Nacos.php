<?php

namespace Nacos;

use Nacos\Client\NacosClient;
use Nacos\Client\NacosGrpcClient;
use Nacos\Config\ConfigClient;
use Nacos\Discovery\DiscoveryClient;
use Nacos\Utils\ServiceInvoker;
use Nacos\Utils\FeignClient;
use Psr\Log\LoggerInterface;

class Nacos
{
    /**
     * @var NacosClient
     */
    private $client;

    /**
     * @var NacosGrpcClient|null
     */
    private $grpcClient;

    /**
     * @var ConfigClient
     */
    private $configClient;

    /**
     * @var DiscoveryClient
     */
    private $discoveryClient;

    /**
     * @var ServiceInvoker
     */
    private $serviceInvoker;

    /**
     * @var FeignClient[]
     */
    private $feignClients = [];

    /**
     * Nacos constructor.
     * @param string $serverUrl
     * @param string $namespaceId
     * @param string $accessKey
     * @param string $secretKey
     * @param int $grpcPort gRPC端口，设为0则禁用gRPC仅使用HTTP
     * @param LoggerInterface|null $logger
     * @param string $username
     * @param string $password
     */
    public function __construct(
        string $serverUrl,
        string $namespaceId = 'public',
        string $accessKey = '',
        string $secretKey = '',
        int $grpcPort = 9848,
        ?LoggerInterface $logger = null,
        string $username = '',
        string $password = ''
    ) {
        $this->client = new NacosClient(
            $serverUrl, 
            $namespaceId, 
            $accessKey, 
            $secretKey, 
            $logger,
            $username,
            $password
        );

        // grpcPort=0 表示禁用gRPC，仅使用HTTP
        $this->grpcClient = $grpcPort > 0
            ? new NacosGrpcClient($serverUrl, $grpcPort, $namespaceId, $accessKey, $secretKey, $logger, $this->client)
            : null;

        $this->configClient = new ConfigClient($this->client, $this->grpcClient);
        $this->discoveryClient = new DiscoveryClient($this->client, $this->grpcClient);
        $this->serviceInvoker = new ServiceInvoker($this->discoveryClient, $logger);
    }

    /**
     * @return ConfigClient
     */
    public function config(): ConfigClient
    {
        return $this->configClient;
    }

    /**
     * @return DiscoveryClient
     */
    public function discovery(): DiscoveryClient
    {
        return $this->discoveryClient;
    }

    /**
     * @return ServiceInvoker
     */
    public function invoker(): ServiceInvoker
    {
        return $this->serviceInvoker;
    }

    /**
     * @return NacosGrpcClient|null
     */
    public function grpc(): ?NacosGrpcClient
    {
        return $this->grpcClient;
    }

    /**
     * 创建Feign客户端
     * @param string $serviceName
     * @param string $groupName
     * @return FeignClient
     */
    public function feign(string $serviceName, string $groupName = 'DEFAULT_GROUP'): FeignClient
    {
        $cacheKey = $serviceName . '_' . $groupName;
        
        if (!isset($this->feignClients[$cacheKey])) {
            $this->feignClients[$cacheKey] = new FeignClient(
                $this->serviceInvoker,
                $serviceName,
                $groupName,
                $this->client->getLogger()
            );
        }
        
        return $this->feignClients[$cacheKey];
    }

    /**
     * @return NacosClient
     */
    public function getClient(): NacosClient
    {
        return $this->client;
    }

    /**
     * @return NacosGrpcClient|null
     */
    public function getGrpcClient(): ?NacosGrpcClient
    {
        return $this->grpcClient;
    }
}