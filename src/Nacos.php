<?php

namespace Nacos;

use Nacos\Client\NacosClient;
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
     * @param LoggerInterface|null $logger
     * @param string $username
     * @param string $password
     */
    public function __construct(
        string $serverUrl,
        string $namespaceId = 'public',
        string $accessKey = '',
        string $secretKey = '',
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

        $this->configClient = new ConfigClient($this->client);
        $this->discoveryClient = new DiscoveryClient($this->client);
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
}
