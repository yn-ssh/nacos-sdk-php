<?php

namespace Nacos\Client;

use Nacos\Exception\NacosException;
use Nacos\Grpc\Proto\Metadata;
use Nacos\Grpc\Proto\Payload;
use Google\Protobuf\Any;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Swoole HTTP/2 gRPC 客户端 — 支持真正的双向流连接注册
 *
 * 使用 Swoole 协程 HTTP/2 客户端替代 PHP gRPC 扩展，
 * 通过 pipeline + usePipelineRead 实现 Nacos BiRequestStream 双向流，
 * 完成 ConnectionSetupRequest → SetupAckRequest 连接注册，
 * 后续业务请求通过同一 HTTP/2 连接的 unary 方式发送。
 *
 * 要求: Swoole >= 5.0 (含 Coroutine\Http2\Client)
 */
class SwooleGrpcClient
{
    private string $serverUrl;
    private int $grpcPort;
    private string $namespaceId;
    private string $accessKey;
    private string $secretKey;
    private ?NacosClient $httpClient;
    private LoggerInterface $logger;

    private ?\Swoole\Coroutine\Http2\Client $h2Client = null;
    private int $biStreamId = 0;
    private string $connectionId = '';
    private bool $connectionRegistered = false;
    private bool $grpcDisabled = false;

    public function __construct(
        string $serverUrl,
        int $grpcPort = 9848,
        string $namespaceId = 'public',
        string $accessKey = '',
        string $secretKey = '',
        ?LoggerInterface $logger = null,
        ?NacosClient $httpClient = null
    ) {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->grpcPort = $grpcPort;
        $this->namespaceId = $namespaceId;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->logger = $logger ?? new NullLogger();
        $this->httpClient = $httpClient;
    }

    /**
     * 检测 Swoole 扩展是否可用且支持 HTTP/2 客户端
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('swoole')
            && class_exists(\Swoole\Coroutine\Http2\Client::class)
            && class_exists(\Swoole\Http2\Request::class);
    }

    public function isGrpcAvailable(): bool
    {
        if ($this->grpcDisabled) {
            return false;
        }
        if (!self::isAvailable()) {
            return false;
        }
        try {
            $address = $this->getGrpcServerAddress();
            $parts = explode(':', $address);
            $host = $parts[0];
            $port = (int)$parts[1];
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 2, 'usec' => 0]);
            $result = socket_connect($socket, $host, $port);
            socket_close($socket);
            return (bool)$result;
        } catch (\Exception $e) {
            $this->logger->warning('[gRPC/Swoole] Port not reachable', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public function getGrpcServerAddress(): string
    {
        $host = parse_url($this->serverUrl, PHP_URL_HOST) ?: 'localhost';
        return $host . ':' . $this->grpcPort;
    }

    /**
     * 确保 HTTP/2 连接和 BiStream 注册完成
     * 在非协程环境中自动包装为协程执行
     */
    private function ensureConnected(): void
    {
        if ($this->connectionRegistered && $this->h2Client !== null) {
            return;
        }

        if (\Swoole\Coroutine::getCid() > 0) {
            // 已在协程中
            $this->doConnect();
        } else {
            // 非协程环境，用 Coroutine\Run 包装
            \Swoole\Coroutine\Run(function () {
                $this->doConnect();
            });
        }
    }

    /**
     * 执行连接和注册（必须在协程上下文中）
     */
    private function doConnect(): void
    {
        if ($this->connectionRegistered && $this->h2Client !== null) {
            return;
        }

        $host = parse_url($this->serverUrl, PHP_URL_HOST) ?: 'localhost';

        $this->h2Client = new \Swoole\Coroutine\Http2\Client($host, $this->grpcPort, false);
        $this->h2Client->set(['timeout' => 10, 'keep_alive' => true]);

        if (!$this->h2Client->connect()) {
            throw new NacosException('[gRPC/Swoole] HTTP/2 connect failed: ' . ($this->h2Client->errMsg ?? 'unknown'));
        }
        $this->logger->debug('[gRPC/Swoole] HTTP/2 connected');

        if ($this->httpClient !== null) {
            $this->httpClient->ensureTokenValid();
        }

        // 1. ServerCheckRequest → connectionId
        $this->connectionId = $this->doServerCheck();
        $this->logger->debug('[gRPC/Swoole] ServerCheck completed', ['connectionId' => $this->connectionId]);

        // 2. BiStream 连接注册
        $this->doBiStreamRegistration();
        $this->connectionRegistered = true;
        $this->logger->debug('[gRPC/Swoole] BiStream registration completed');
    }

    private function doServerCheck(): string
    {
        $payload = $this->buildPayload('ServerCheckRequest', [
            'requestId' => $this->generateRequestId(),
        ], [
            'clientVersion' => 'Nacos-PHP-SDK/2.0',
        ]);

        $req = new \Swoole\Http2\Request();
        $req->path = '/Request/request';
        $req->method = 'POST';
        $req->headers = ['content-type' => 'application/grpc', 'te' => 'trailers'];
        $req->data = $this->encodeGrpcFrame($payload->serializeToString());
        $req->pipeline = false;

        $this->h2Client->send($req);
        $resp = $this->h2Client->recv(5.0);

        if (!$resp || empty($resp->data)) {
            throw new NacosException('[gRPC/Swoole] ServerCheckRequest failed: no response');
        }

        $frame = $this->decodeGrpcFrame($resp->data);
        if (!$frame) {
            throw new NacosException('[gRPC/Swoole] ServerCheckRequest failed: invalid gRPC frame');
        }

        $respPayload = new Payload();
        $respPayload->mergeFromString($frame['message']);
        $result = $this->parsePayload($respPayload);

        return $result['connectionId'] ?? '';
    }

    private function doBiStreamRegistration(): void
    {
        $setupPayload = $this->buildPayload('ConnectionSetupRequest', [
            '@type' => 'com.alibaba.nacos.api.remote.request.ConnectionSetupRequest',
            'requestId' => $this->generateRequestId(),
            'clientVersion' => 'Nacos-PHP-SDK/2.0',
            'tenant' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'labels' => (object)['source' => 'sdk', 'module' => 'naming'],
            'abilityTable' => (object)[],
        ]);

        $req = new \Swoole\Http2\Request();
        $req->path = '/BiRequestStream/requestBiStream';
        $req->method = 'POST';
        $req->headers = [
            'content-type' => 'application/grpc',
            'te' => 'trailers',
            'grpc-accept-encoding' => 'identity',
            'grpc-encoding' => 'identity',
        ];
        $req->data = $this->encodeGrpcFrame($setupPayload->serializeToString());
        $req->pipeline = true;
        $req->usePipelineRead = true;

        $this->biStreamId = $this->h2Client->send($req);

        if ($this->biStreamId === false) {
            throw new NacosException('[gRPC/Swoole] BiStream open failed');
        }

        // 读取 SetupAckRequest
        $resp = $this->h2Client->read(5.0);
        if (!$resp || empty($resp->data)) {
            throw new NacosException('[gRPC/Swoole] SetupAckRequest not received');
        }

        $frame = $this->decodeGrpcFrame($resp->data);
        if (!$frame) {
            throw new NacosException('[gRPC/Swoole] Invalid SetupAckRequest frame');
        }

        $ackPayload = new Payload();
        $ackPayload->mergeFromString($frame['message']);
        $ackBody = $this->parsePayload($ackPayload);
        $ackType = $ackPayload->getMetadata() ? $ackPayload->getMetadata()->getType() : '';

        if ($ackType !== 'SetupAckRequest') {
            throw new NacosException('[gRPC/Swoole] Expected SetupAckRequest, got: ' . $ackType);
        }

        $this->logger->debug('[gRPC/Swoole] SetupAckRequest received', [
            'abilityTable' => $ackBody['abilityTable'] ?? [],
        ]);

        // 回复 SetupAckResponse
        $respPayload = $this->buildPayload('SetupAckResponse', [
            '@type' => 'com.alibaba.nacos.api.remote.response.SetupAckResponse',
            'requestId' => $ackBody['requestId'] ?? $this->generateRequestId(),
            'success' => true,
        ]);
        $respFrame = $this->encodeGrpcFrame($respPayload->serializeToString());
        $this->h2Client->write($this->biStreamId, $respFrame, false);
    }

    /**
     * 发送 gRPC unary 请求（必须在协程上下文中）
     */
    private function sendUnaryRequest(string $type, array $requestData): array
    {
        $this->ensureConnected();

        // 确保认证 token 有效
        if ($this->httpClient !== null) {
            $this->httpClient->ensureTokenValid();
        }

        $headers = [
            'connectionId' => $this->connectionId,
            'clientVersion' => 'Nacos-PHP-SDK/2.0',
            'namespaceId' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
        ];

        // 添加认证 token
        if ($this->httpClient !== null) {
            $token = $this->httpClient->getAccessToken();
            if (!empty($token)) {
                $headers['accessToken'] = $token;
            }
        }
        // AK/SK 认证
        if (!empty($this->accessKey)) {
            $headers['accessKey'] = $this->accessKey;
        }
        if (!empty($this->secretKey)) {
            $headers['secretKey'] = $this->secretKey;
        }

        $payload = $this->buildPayload($type, $requestData, $headers);

        $req = new \Swoole\Http2\Request();
        $req->path = '/Request/request';
        $req->method = 'POST';
        $req->headers = ['content-type' => 'application/grpc', 'te' => 'trailers'];
        $req->data = $this->encodeGrpcFrame($payload->serializeToString());
        $req->pipeline = false;

        $this->logger->debug('[gRPC/Swoole] Sending unary request', ['type' => $type]);

        if (\Swoole\Coroutine::getCid() > 0) {
            return $this->doSendUnary($req, $type);
        } else {
            $result = null;
            $exception = null;
            \Swoole\Coroutine\Run(function () use ($req, $type, &$result, &$exception) {
                try {
                    // 确保连接仍然有效
                    if (!$this->connectionRegistered) {
                        $this->doConnect();
                    }
                    $result = $this->doSendUnary($req, $type);
                } catch (\Throwable $e) {
                    $exception = $e;
                }
            });
            // 协程内的异常不会自动传播，需要在外部重新抛出
            if ($exception !== null) {
                throw $exception;
            }
            return $result;
        }
    }

    private function doSendUnary(\Swoole\Http2\Request $req, string $type): array
    {
        $this->h2Client->send($req);
        $resp = $this->h2Client->recv(10.0);

        if (!$resp || empty($resp->data)) {
            throw new NacosException('[gRPC/Swoole] Request failed: no response for ' . $type);
        }

        $frame = $this->decodeGrpcFrame($resp->data);
        if (!$frame) {
            throw new NacosException('[gRPC/Swoole] Request failed: invalid gRPC frame');
        }

        $respPayload = new Payload();
        $respPayload->mergeFromString($frame['message']);
        $result = $this->parsePayload($respPayload);

        $resultCode = $result['resultCode'] ?? $result['code'] ?? 200;
        if ($resultCode !== 200 && $resultCode !== 0) {
            $errorCode = $result['errorCode'] ?? $resultCode;
            $errorMsg = $result['message'] ?? 'Unknown error';

            if ($errorCode === 301 || $errorCode === '301') {
                $this->grpcDisabled = true;
                $this->connectionRegistered = false;
                $this->logger->warning(
                    '[gRPC/Swoole] Connection unregistered (301), disabling gRPC and using HTTP fallback'
                );
            }

            throw new NacosException('[gRPC/Swoole] ' . $errorMsg, (int)$resultCode);
        }

        $this->logger->debug('[gRPC/Swoole] Response received', ['type' => $type]);
        return $result;
    }

    // ========== gRPC 帧工具 ==========

    private function encodeGrpcFrame(string $protobufData): string
    {
        return pack('CN', 0, strlen($protobufData)) . $protobufData;
    }

    private function decodeGrpcFrame(string $data): ?array
    {
        if (strlen($data) < 5) return null;
        $header = unpack('Ccompressed/Nlength', $data);
        if (strlen($data) < 5 + $header['length']) return null;
        return [
            'compressed' => $header['compressed'],
            'length' => $header['length'],
            'message' => substr($data, 5, $header['length']),
        ];
    }

    private function buildPayload(string $type, array $bodyData, array $headers = []): Payload
    {
        $metadata = new Metadata();
        $metadata->setType($type);
        $metadata->setClientIp($this->getLocalIp());
        if (!empty($headers)) {
            $metadata->setHeaders($headers);
        }

        $any = new Any();
        $any->setValue(json_encode($bodyData));

        $payload = new Payload();
        $payload->setMetadata($metadata);
        $payload->setBody($any);

        return $payload;
    }

    private function parsePayload(Payload $payload): array
    {
        $body = $payload->getBody();
        if (!$body) return [];
        return json_decode($body->getValue(), true) ?: [];
    }

    private function generateRequestId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function getLocalIp(): string
    {
        return gethostbyname(gethostname()) ?: '127.0.0.1';
    }

    public function resetConnection(): void
    {
        if ($this->h2Client) {
            $this->h2Client->close();
            $this->h2Client = null;
        }
        $this->connectionRegistered = false;
        $this->connectionId = '';
        $this->biStreamId = 0;
    }

    // ========== 业务接口 ==========

    /**
     * Nacos 请求类型到 Java 全限定类名的映射
     */
    private const TYPE_MAP = [
        'ServerCheckRequest'     => 'com.alibaba.nacos.api.remote.request.ServerCheckRequest',
        'ConnectionSetupRequest' => 'com.alibaba.nacos.api.remote.request.ConnectionSetupRequest',
        'ConfigQueryRequest'     => 'com.alibaba.nacos.api.config.request.ConfigQueryRequest',
        'ConfigPublishRequest'   => 'com.alibaba.nacos.api.config.request.ConfigPublishRequest',
        'ConfigRemoveRequest'    => 'com.alibaba.nacos.api.config.request.ConfigRemoveRequest',
        'ConfigChangeNotifyRequest' => 'com.alibaba.nacos.api.config.request.ConfigChangeNotifyRequest',
        'InstanceRequest'        => 'com.alibaba.nacos.api.naming.request.InstanceRequest',
        'ServiceQueryRequest'    => 'com.alibaba.nacos.api.naming.request.ServiceQueryRequest',
        'ServiceListRequest'     => 'com.alibaba.nacos.api.naming.request.ServiceListRequest',
        'NotifySubscriberRequest' => 'com.alibaba.nacos.api.naming.request.NotifySubscriberRequest',
    ];

    /**
     * 通用 gRPC 请求入口（由 NacosGrpcClient 委托调用）
     */
    public function sendGrpcRequest(string $type, array $requestData): array
    {
        // 自动添加 @type 字段（如果未包含）
        if (!isset($requestData['@type']) && isset(self::TYPE_MAP[$type])) {
            $requestData['@type'] = self::TYPE_MAP[$type];
        }
        if (!isset($requestData['requestId'])) {
            $requestData['requestId'] = $this->generateRequestId();
        }
        return $this->sendUnaryRequest($type, $requestData);
    }

    /**
     * 连接是否被禁用（301 后）
     */
    public function isConnectionDisabled(): bool
    {
        return $this->grpcDisabled;
    }

    public function getConfig(string $dataId, string $group = 'DEFAULT_GROUP'): array
    {
        return $this->sendUnaryRequest('ConfigQueryRequest', [
            '@type' => 'com.alibaba.nacos.api.config.request.ConfigQueryRequest',
            'requestId' => $this->generateRequestId(),
            'dataId' => $dataId,
            'group' => $group,
            'tenant' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
        ]);
    }

    public function publishConfig(string $dataId, string $group, string $content, string $type = 'text'): bool
    {
        $result = $this->sendUnaryRequest('ConfigPublishRequest', [
            '@type' => 'com.alibaba.nacos.api.config.request.ConfigPublishRequest',
            'requestId' => $this->generateRequestId(),
            'dataId' => $dataId,
            'group' => $group,
            'content' => $content,
            'tenant' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'type' => $type,
        ]);
        return ($result['resultCode'] ?? $result['code'] ?? 0) === 200
            || ($result['resultCode'] ?? $result['code'] ?? 0) === 0;
    }

    public function deleteConfig(string $dataId, string $group = 'DEFAULT_GROUP'): bool
    {
        $result = $this->sendUnaryRequest('ConfigRemoveRequest', [
            '@type' => 'com.alibaba.nacos.api.config.request.ConfigRemoveRequest',
            'requestId' => $this->generateRequestId(),
            'dataId' => $dataId,
            'group' => $group,
            'tenant' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
        ]);
        return ($result['resultCode'] ?? $result['code'] ?? 0) === 200
            || ($result['resultCode'] ?? $result['code'] ?? 0) === 0;
    }

    public function registerInstance(
        string $serviceName, string $ip, int $port,
        string $group = 'DEFAULT_GROUP', array $metadata = [],
        int $weight = 1, bool $ephemeral = true
    ): bool {
        $result = $this->sendUnaryRequest('InstanceRequest', [
            '@type' => 'com.alibaba.nacos.api.naming.request.InstanceRequest',
            'requestId' => $this->generateRequestId(),
            'type' => 'registerInstance',
            'namespace' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'groupName' => $group,
            'serviceName' => $serviceName,
            'instance' => [
                'ip' => $ip, 'port' => $port, 'weight' => $weight,
                'enabled' => true, 'healthy' => true,
                'ephemeral' => $ephemeral, 'metadata' => $metadata,
            ],
        ]);
        return ($result['resultCode'] ?? $result['code'] ?? 0) === 200
            || ($result['resultCode'] ?? $result['code'] ?? 0) === 0;
    }

    public function deregisterInstance(
        string $serviceName, string $ip, int $port,
        string $group = 'DEFAULT_GROUP', bool $ephemeral = true
    ): bool {
        $result = $this->sendUnaryRequest('InstanceRequest', [
            '@type' => 'com.alibaba.nacos.api.naming.request.InstanceRequest',
            'requestId' => $this->generateRequestId(),
            'type' => 'deregisterInstance',
            'namespace' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'groupName' => $group,
            'serviceName' => $serviceName,
            'instance' => ['ip' => $ip, 'port' => $port, 'ephemeral' => $ephemeral],
        ]);
        return ($result['resultCode'] ?? $result['code'] ?? 0) === 200
            || ($result['resultCode'] ?? $result['code'] ?? 0) === 0;
    }

    public function getAllInstances(
        string $serviceName, string $group = 'DEFAULT_GROUP', bool $healthyOnly = true
    ): array {
        $result = $this->sendUnaryRequest('ServiceQueryRequest', [
            '@type' => 'com.alibaba.nacos.api.naming.request.ServiceQueryRequest',
            'requestId' => $this->generateRequestId(),
            'namespace' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'groupName' => $group,
            'serviceName' => $serviceName,
            'healthyOnly' => $healthyOnly,
        ]);
        $serviceInfo = $result['serviceInfo'] ?? [];
        return [
            'name' => $serviceInfo['name'] ?? $serviceName,
            'groupName' => $serviceInfo['groupName'] ?? $group,
            'hosts' => $serviceInfo['hosts'] ?? [],
        ];
    }

    public function selectOneHealthyInstance(
        string $serviceName, string $group = 'DEFAULT_GROUP'
    ): ?array {
        $result = $this->getAllInstances($serviceName, $group, true);
        $hosts = $result['hosts'] ?? [];
        return empty($hosts) ? null : $hosts[array_rand($hosts)];
    }

    public function sendHeartbeat(
        string $serviceName, string $ip, int $port,
        string $group = 'DEFAULT_GROUP', bool $ephemeral = true
    ): bool {
        $result = $this->sendUnaryRequest('InstanceRequest', [
            '@type' => 'com.alibaba.nacos.api.naming.request.InstanceRequest',
            'requestId' => $this->generateRequestId(),
            'type' => 'beatInstance',
            'namespace' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'groupName' => $group,
            'serviceName' => $serviceName,
            'instance' => ['ip' => $ip, 'port' => $port, 'ephemeral' => $ephemeral],
        ]);
        return ($result['resultCode'] ?? $result['code'] ?? 0) === 200
            || ($result['resultCode'] ?? $result['code'] ?? 0) === 0;
    }

    public function listenConfig(array $listeners, callable $callback): void
    {
        throw new NacosException('gRPC listenConfig requires long-running coroutine, please use HTTP fallback');
    }

    // ========== Getters ==========

    public function getServerUrl(): string { return $this->serverUrl; }
    public function getGrpcPort(): int { return $this->grpcPort; }
    public function getNamespaceId(): string { return $this->namespaceId; }
    public function getLogger(): LoggerInterface { return $this->logger; }
    public function getHttpClient(): ?NacosClient { return $this->httpClient; }
    public function getConnectionId(): string { return $this->connectionId; }
    public function isConnectionRegistered(): bool { return $this->connectionRegistered; }
}
