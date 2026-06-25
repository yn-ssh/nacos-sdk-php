<?php

namespace Nacos\Client;

use Nacos\Exception\NacosException;
use Nacos\Grpc\Proto\Body;
use Nacos\Grpc\Proto\Metadata;
use Nacos\Grpc\Proto\Payload;
use Nacos\Grpc\Proto\RequestClient;
use Google\Protobuf\Any;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NacosGrpcClient
{
    private string $serverUrl;
    private int $grpcPort;
    private string $namespaceId;
    private string $accessKey;
    private string $secretKey;
    private ?NacosClient $httpClient;
    private LoggerInterface $logger;
    private ?bool $availabilityCache = null;
    private ?RequestClient $grpcClient = null;
    private bool $connectionRegistered = false;
    private string $connectionId = '';
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

    public function getGrpcServerAddress(): string
    {
        $host = parse_url($this->serverUrl, PHP_URL_HOST) ?: 'localhost';
        return $host . ':' . $this->grpcPort;
    }

    public function isGrpcAvailable(): bool
    {
        if ($this->grpcDisabled) {
            return false;
        }

        if ($this->availabilityCache !== null) {
            return $this->availabilityCache;
        }

        if (!extension_loaded('grpc')) {
            $this->logger->info('gRPC extension is not installed');
            $this->availabilityCache = false;
            return false;
        }

        if (!extension_loaded('protobuf')) {
            $this->logger->info('Protobuf extension is not installed');
            $this->availabilityCache = false;
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

            if (!$result) {
                $this->logger->info('gRPC port is not reachable', ['address' => $address]);
                $this->availabilityCache = false;
                return false;
            }

            $this->logger->info('gRPC service is available', ['address' => $address]);
            $this->availabilityCache = true;
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('gRPC service is not available', ['exception' => $e->getMessage()]);
            $this->availabilityCache = false;
            return false;
        }
    }

    public function resetAvailabilityCache(): void
    {
        $this->availabilityCache = null;
        $this->grpcDisabled = false;
        $this->connectionRegistered = false;
        $this->connectionId = '';
    }

    private function ensureAvailable(): void
    {
        if (!extension_loaded('grpc')) {
            throw new NacosException('gRPC extension is not installed.');
        }
        if (!extension_loaded('protobuf')) {
            throw new NacosException('Protobuf extension is not installed.');
        }
        if (!$this->isGrpcAvailable()) {
            throw new NacosException('gRPC service is not available at ' . $this->getGrpcServerAddress());
        }
    }

    private function getGrpcClient(): RequestClient
    {
        if ($this->grpcClient === null) {
            $address = $this->getGrpcServerAddress();
            $this->grpcClient = new RequestClient($address, [
                'credentials' => \Grpc\ChannelCredentials::createInsecure(),
            ]);
            $this->connectionRegistered = false;
        }
        return $this->grpcClient;
    }

    private function ensureConnectionRegistered(): void
    {
        if ($this->connectionRegistered) {
            return;
        }

        // Nacos gRPC 需要先发送 ServerCheckRequest 注册连接
        $metadata = new Metadata();
        $metadata->setType('ServerCheckRequest');
        $metadata->setClientIp($this->getLocalIp());
        $metadata->setHeaders(['clientVersion' => 'Nacos-PHP-SDK/2.0']);

        $any = new Any();
        $any->setValue('{}');

        $payload = new Payload();
        $payload->setMetadata($metadata);
        $payload->setBody($any);

        $client = $this->getGrpcClient();
        $call = $client->request($payload);
        [$response, $status] = $call->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            throw new NacosException('gRPC ServerCheckRequest failed: ' . ($status->details ?? 'Unknown error'));
        }

        $responseAny = $response->getBody();
        $checkResult = json_decode($responseAny ? $responseAny->getValue() : '{}', true);
        $this->connectionId = $checkResult['connectionId'] ?? '';
        $this->connectionRegistered = true;
        $this->logger->info('gRPC connection registered', ['connectionId' => $this->connectionId]);
    }

    private function sendGrpcRequest(string $type, array $requestData): array
    {
        $this->ensureAvailable();

        if ($this->httpClient !== null) {
            $this->httpClient->ensureTokenValid();
        }

        try {
            $this->ensureConnectionRegistered();

            $metadata = new Metadata();
            $metadata->setType($type);
            $metadata->setClientIp($this->getLocalIp());

            $headers = $this->buildHeaders();
            $metadata->setHeaders($headers);

            // Nacos gRPC: Any.value 直接放 JSON 字符串
            $any = new Any();
            $any->setValue(json_encode($requestData));

            $payload = new Payload();
            $payload->setMetadata($metadata);
            $payload->setBody($any);

            $this->logger->debug('gRPC request', ['type' => $type, 'params' => $requestData]);

            $client = $this->getGrpcClient();
            $call = $client->request($payload);

            [$response, $status] = $call->wait();

            if ($status->code !== \Grpc\STATUS_OK) {
                throw new NacosException('gRPC request failed: ' . ($status->details ?? 'Unknown error'), $status->code);
            }

            if (!$response instanceof Payload) {
                throw new NacosException('gRPC response is not a Payload');
            }

            // 响应: Any.value 也是原始 JSON 字符串
            $responseAny = $response->getBody();
            if ($responseAny === null) {
                throw new NacosException('gRPC response body is null');
            }

            $jsonString = $responseAny->getValue();
            if (empty($jsonString)) {
                throw new NacosException('gRPC response value is empty');
            }

            $result = json_decode($jsonString, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NacosException('gRPC response JSON decode failed: ' . json_last_error_msg());
            }

            $resultCode = $result['resultCode'] ?? $result['code'] ?? 200;
            if ($resultCode !== 200 && $resultCode !== 0) {
                $errorCode = $result['errorCode'] ?? $resultCode;
                $errorMsg = $result['message'] ?? 'Unknown error';

                // 301 = Connection is unregistered: Nacos gRPC 服务端需要双向流维持连接
                // 一元请求无法保持连接注册，自动禁用 gRPC 并回退到 HTTP
                if ($errorCode === 301 || $errorCode === '301') {
                    $this->grpcDisabled = true;
                    $this->connectionRegistered = false;
                    $this->logger->warning(
                        'gRPC unary requests not supported by Nacos server, disabling gRPC and using HTTP fallback. ' .
                        'Full gRPC support requires bidirectional streaming.'
                    );
                }

                throw new NacosException('Nacos gRPC error: ' . $errorMsg, (int)$resultCode);
            }

            $this->logger->debug('gRPC response', ['type' => $type, 'result' => $result]);
            return $result;
        } catch (NacosException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('gRPC request failed', ['exception' => $e->getMessage()]);
            throw new NacosException('gRPC request failed: ' . $e->getMessage());
        }
    }

    private function getLocalIp(): string
    {
        $ip = @gethostbyname(gethostname());
        return $ip ?: '127.0.0.1';
    }

    private function buildHeaders(): array
    {
        $headers = [
            'namespaceId' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'clientVersion' => 'Nacos-PHP-SDK/2.0',
        ];

        if (!empty($this->connectionId)) {
            $headers['connectionId'] = $this->connectionId;
        }

        if ($this->httpClient !== null) {
            $token = $this->httpClient->getAccessToken();
            if (!empty($token)) {
                $headers['accessToken'] = $token;
            }
        }

        if (!empty($this->accessKey)) {
            $headers['accessKey'] = $this->accessKey;
        }
        if (!empty($this->secretKey)) {
            $headers['secretKey'] = $this->secretKey;
        }

        return $headers;
    }

    public function getConfig(string $dataId, string $group = 'DEFAULT_GROUP'): array
    {
        return $this->sendGrpcRequest('ConfigQueryRequest', [
            'dataId' => $dataId,
            'group' => $group,
            'tenant' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
        ]);
    }

    public function publishConfig(string $dataId, string $group, string $content, string $type = 'text'): bool
    {
        $result = $this->sendGrpcRequest('ConfigPublishRequest', [
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
        $result = $this->sendGrpcRequest('ConfigRemoveRequest', [
            'dataId' => $dataId,
            'group' => $group,
            'tenant' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
        ]);
        return ($result['resultCode'] ?? $result['code'] ?? 0) === 200
            || ($result['resultCode'] ?? $result['code'] ?? 0) === 0;
    }

    public function listenConfig(array $listeners, callable $callback): void
    {
        throw new NacosException('gRPC listenConfig requires bidirectional streaming, please use HTTP fallback');
    }

    public function registerInstance(
        string $serviceName, string $ip, int $port,
        string $group = 'DEFAULT_GROUP', array $metadata = [],
        int $weight = 1, bool $ephemeral = true
    ): bool {
        $instance = [
            'ip' => $ip,
            'port' => $port,
            'weight' => $weight,
            'enabled' => true,
            'healthy' => true,
            'ephemeral' => $ephemeral,
            'metadata' => $metadata,
        ];

        $result = $this->sendGrpcRequest('InstanceRequest', [
            'type' => 'registerInstance',
            'namespace' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'groupName' => $group,
            'serviceName' => $serviceName,
            'instance' => $instance,
        ]);

        return ($result['resultCode'] ?? $result['code'] ?? 0) === 200
            || ($result['resultCode'] ?? $result['code'] ?? 0) === 0;
    }

    public function deregisterInstance(
        string $serviceName, string $ip, int $port,
        string $group = 'DEFAULT_GROUP', bool $ephemeral = true
    ): bool {
        $instance = [
            'ip' => $ip,
            'port' => $port,
            'ephemeral' => $ephemeral,
        ];

        $result = $this->sendGrpcRequest('InstanceRequest', [
            'type' => 'deregisterInstance',
            'namespace' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'groupName' => $group,
            'serviceName' => $serviceName,
            'instance' => $instance,
        ]);

        return ($result['resultCode'] ?? $result['code'] ?? 0) === 200
            || ($result['resultCode'] ?? $result['code'] ?? 0) === 0;
    }

    public function getAllInstances(
        string $serviceName, string $group = 'DEFAULT_GROUP', bool $healthyOnly = true
    ): array {
        $result = $this->sendGrpcRequest('ServiceQueryRequest', [
            'namespace' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'groupName' => $group,
            'serviceName' => $serviceName,
            'healthyOnly' => $healthyOnly,
        ]);

        $serviceInfo = $result['serviceInfo'] ?? [];
        $hosts = $serviceInfo['hosts'] ?? [];

        return [
            'name' => $serviceInfo['name'] ?? $serviceName,
            'groupName' => $serviceInfo['groupName'] ?? $group,
            'hosts' => $hosts,
        ];
    }

    public function selectOneHealthyInstance(
        string $serviceName, string $group = 'DEFAULT_GROUP'
    ): ?array {
        $result = $this->getAllInstances($serviceName, $group, true);
        $hosts = $result['hosts'] ?? [];

        if (empty($hosts)) {
            return null;
        }

        return $hosts[array_rand($hosts)];
    }

    public function sendHeartbeat(
        string $serviceName, string $ip, int $port,
        string $group = 'DEFAULT_GROUP', bool $ephemeral = true
    ): bool {
        $instance = [
            'ip' => $ip,
            'port' => $port,
            'ephemeral' => $ephemeral,
        ];

        $result = $this->sendGrpcRequest('InstanceRequest', [
            'type' => 'beatInstance',
            'namespace' => $this->namespaceId === 'public' ? '' : $this->namespaceId,
            'groupName' => $group,
            'serviceName' => $serviceName,
            'instance' => $instance,
        ]);

        return ($result['resultCode'] ?? $result['code'] ?? 0) === 200
            || ($result['resultCode'] ?? $result['code'] ?? 0) === 0;
    }

    public function getServerUrl(): string
    {
        return $this->serverUrl;
    }

    public function getGrpcPort(): int
    {
        return $this->grpcPort;
    }

    public function getNamespaceId(): string
    {
        return $this->namespaceId;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getHttpClient(): ?NacosClient
    {
        return $this->httpClient;
    }
}
