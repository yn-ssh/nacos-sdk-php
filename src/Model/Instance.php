<?php

namespace Nacos\Model;

/**
 * 服务实例模型
 */
class Instance
{
    /**
     * @var string 服务名
     */
    private $serviceName;

    /**
     * @var string IP地址
     */
    private $ip;

    /**
     * @var int 端口
     */
    private $port;

    /**
     * @var string 分组名
     */
    private $groupName;

    /**
     * @var array 元数据
     */
    private $metadata;

    /**
     * @var int 权重
     */
    private $weight;

    /**
     * @var bool 是否健康
     */
    private $healthy;

    /**
     * @var bool 是否为临时实例
     */
    private $ephemeral;

    /**
     * @var string 集群名
     */
    private $clusterName;

    /**
     * @var bool 是否启用
     */
    private $enabled;

    public function __construct(
        string $serviceName = '',
        string $ip = '',
        int $port = 0,
        string $groupName = 'DEFAULT_GROUP',
        array $metadata = [],
        int $weight = 1,
        bool $healthy = true,
        bool $ephemeral = true,
        string $clusterName = 'DEFAULT',
        bool $enabled = true
    ) {
        $this->serviceName = $serviceName;
        $this->ip = $ip;
        $this->port = $port;
        $this->groupName = $groupName;
        $this->metadata = $metadata;
        $this->weight = $weight;
        $this->healthy = $healthy;
        $this->ephemeral = $ephemeral;
        $this->clusterName = $clusterName;
        $this->enabled = $enabled;
    }

    /**
     * 从Nacos API响应数组创建实例
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['serviceName'] ?? '',
            $data['ip'] ?? '',
            $data['port'] ?? 0,
            $data['groupName'] ?? 'DEFAULT_GROUP',
            $data['metadata'] ?? [],
            $data['weight'] ?? 1,
            $data['healthy'] ?? true,
            $data['ephemeral'] ?? true,
            $data['clusterName'] ?? 'DEFAULT',
            $data['enabled'] ?? true
        );
    }

    /**
     * 转换为Nacos API请求参数数组
     * @return array
     */
    public function toRequestParams(): array
    {
        $params = [
            'serviceName' => $this->serviceName,
            'ip' => $this->ip,
            'port' => $this->port,
            'groupName' => $this->groupName,
            'weight' => $this->weight,
            'ephemeral' => $this->ephemeral ? 'true' : 'false',
            'clusterName' => $this->clusterName,
            'enabled' => $this->enabled ? 'true' : 'false',
        ];

        if (!empty($this->metadata)) {
            $params['metadata'] = json_encode($this->metadata);
        }

        return $params;
    }

    /**
     * 转换为数组
     * @return array
     */
    public function toArray(): array
    {
        return [
            'serviceName' => $this->serviceName,
            'ip' => $this->ip,
            'port' => $this->port,
            'groupName' => $this->groupName,
            'metadata' => $this->metadata,
            'weight' => $this->weight,
            'healthy' => $this->healthy,
            'ephemeral' => $this->ephemeral,
            'clusterName' => $this->clusterName,
            'enabled' => $this->enabled,
        ];
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function isEphemeral(): bool
    {
        return $this->ephemeral;
    }

    public function getClusterName(): string
    {
        return $this->clusterName;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setWeight(int $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    public function setHealthy(bool $healthy): self
    {
        $this->healthy = $healthy;
        return $this;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * 检查是否为HTTPS服务（通过元数据中的secure标记）
     * @return bool
     */
    public function isSecure(): bool
    {
        return isset($this->metadata['secure']) && $this->metadata['secure'] === 'true';
    }

    /**
     * 构建服务访问URL
     * @param string $path
     * @return string
     */
    public function buildUrl(string $path = '/'): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $path = ltrim($path, '/');
        return "{$scheme}://{$this->ip}:{$this->port}/{$path}";
    }
}
