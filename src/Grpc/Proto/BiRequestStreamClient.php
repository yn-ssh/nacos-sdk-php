<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Nacos\Grpc\Proto;

/**
 */
class BiRequestStreamClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function requestBiStream($metadata = [], $options = []) {
        return $this->_bidiRequest('/BiRequestStream/requestBiStream',
        ['\Nacos\Grpc\Proto\Payload','decode'],
        $metadata, $options);
    }

}
