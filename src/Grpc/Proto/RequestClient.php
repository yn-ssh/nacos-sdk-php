<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Nacos\Grpc\Proto;

/**
 */
class RequestClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Nacos\Grpc\Proto\Payload $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Nacos\Grpc\Proto\Payload>
     */
    public function request(\Nacos\Grpc\Proto\Payload $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Request/request',
        $argument,
        ['\Nacos\Grpc\Proto\Payload', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function requestBiStream($metadata = [], $options = []) {
        return $this->_bidiRequest('/Request/requestBiStream',
        ['\Nacos\Grpc\Proto\Payload','decode'],
        $metadata, $options);
    }

}
