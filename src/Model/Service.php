<?php

namespace Nacos\Model;

/**
 * 服务模型
 */
class Service
{
    /**
     * @var string 服务名
     */
    private $serviceName;

    /**
     * @var string 分组名
     */
    private $groupName;

    /**
     * @var string 命名空间ID
     */
    private $namespaceId;

    /**
     * @var array 保护阈值
     */
    private $protectThreshold;

    /**
     * @var array 元数据
     */
    private $metadata;

    /**
     * @var array 选择器
     */
    private $selector;

    public function __construct(
        string $serviceName = '',
        string $groupName = 'DEFAULT_GROUP',
        string $namespaceId = 'public',
        float $protectThreshold = 0.0,
        array $metadata = [],
        array $selector = []
    ) {
        $this->serviceName = $serviceName;
        $this->groupName = $groupName;
        $this->namespaceId = $namespaceId;
        $this->protectThreshold = $protectThreshold;
        $this->metadata = $metadata;
        $this->selector = $selector;
    }

    /**
     * 从Nacos API响应创建服务对象
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['serviceName'] ?? $data['name'] ?? '',
            $data['groupName'] ?? 'DEFAULT_GROUP',
            $data['namespaceId'] ?? 'public',
            $data['protectThreshold'] ?? 0.0,
            $data['metadata'] ?? [],
            $data['selector'] ?? []
        );
    }

    /**
     * 转换为数组
     * @return array
     */
    public function toArray(): array
    {
        return [
            'serviceName' => $this->serviceName,
            'groupName' => $this->groupName,
            'namespaceId' => $this->namespaceId,
            'protectThreshold' => $this->protectThreshold,
            'metadata' => $this->metadata,
            'selector' => $this->selector,
        ];
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function getNamespaceId(): string
    {
        return $this->namespaceId;
    }

    public function getProtectThreshold(): float
    {
        return $this->protectThreshold;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getSelector(): array
    {
        return $this->selector;
    }

    public function setProtectThreshold(float $protectThreshold): self
    {
        $this->protectThreshold = $protectThreshold;
        return $this;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
}
