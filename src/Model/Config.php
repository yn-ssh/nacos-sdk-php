<?php

namespace Nacos\Model;

/**
 * 配置模型
 */
class Config
{
    /**
     * @var string 配置ID
     */
    private $dataId;

    /**
     * @var string 分组
     */
    private $group;

    /**
     * @var string 配置内容
     */
    private $content;

    /**
     * @var string 配置类型（text/json/yaml/properties等）
     */
    private $type;

    /**
     * @var string 命名空间ID
     */
    private $namespaceId;

    /**
     * @var string 配置的MD5值
     */
    private $md5;

    public function __construct(
        string $dataId = '',
        string $group = 'DEFAULT_GROUP',
        string $content = '',
        string $type = 'text',
        string $namespaceId = 'public',
        string $md5 = ''
    ) {
        $this->dataId = $dataId;
        $this->group = $group;
        $this->content = $content;
        $this->type = $type;
        $this->namespaceId = $namespaceId;
        $this->md5 = $md5;
    }

    /**
     * 从Nacos API响应创建配置对象
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['dataId'] ?? '',
            $data['group'] ?? 'DEFAULT_GROUP',
            $data['content'] ?? '',
            $data['type'] ?? 'text',
            $data['namespaceId'] ?? 'public',
            $data['md5'] ?? ''
        );
    }

    /**
     * 转换为Nacos API请求参数
     * @return array
     */
    public function toRequestParams(): array
    {
        $params = [
            'dataId' => $this->dataId,
            'group' => $this->group,
            'content' => $this->content,
            'namespaceId' => $this->namespaceId,
        ];

        if (!empty($this->type)) {
            $params['type'] = $this->type;
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
            'dataId' => $this->dataId,
            'group' => $this->group,
            'content' => $this->content,
            'type' => $this->type,
            'namespaceId' => $this->namespaceId,
            'md5' => $this->md5,
        ];
    }

    public function getDataId(): string
    {
        return $this->dataId;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getNamespaceId(): string
    {
        return $this->namespaceId;
    }

    public function getMd5(): string
    {
        return $this->md5;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        $this->md5 = md5($content);
        return $this;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * 尝试将配置内容解析为数组
     * @return array|null
     */
    public function parseContentAsArray(): ?array
    {
        switch ($this->type) {
            case 'json':
                $result = json_decode($this->content, true);
                return json_last_error() === JSON_ERROR_NONE ? $result : null;
            default:
                return null;
        }
    }
}
