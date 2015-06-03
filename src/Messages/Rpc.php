<?php

namespace WyriHaximus\React\ChildProcess\Messenger\Messages;

use React\Promise\Deferred;

class Rpc implements \JsonSerializable, ActionableMessageInterface
{
    /**
     * @var string
     */
    protected $target;

    /**
     * @var Payload
     */
    protected $payload;

    /**
     * @var string
     */
    protected $uniqid;

    /**
     * @param string $target
     * @param Payload $payload
     * @param string $uniqid
     */
    public function __construct($target, Payload $payload, $uniqid = '')
    {
        $this->target = $target;
        $this->payload = $payload;
        $this->uniqid = $uniqid;
    }

    /**
     * @return Payload
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @param $uniqid
     * @return static
     */
    public function setUniqid($uniqid)
    {
        return new static($this->target, $this->payload, $uniqid);
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return [
            'type' => 'rpc',
            'uniqid' => $this->uniqid,
            'target' => $this->target,
            'payload' => $this->payload,
        ];
    }

    /**
     * @param $bindTo
     * @param $source
     */
    public function handle($bindTo, $source)
    {
        $cb = function ($target, $payload, $uniqid) {
            if (!$this->hasRpc($target)) {
                $this->getStderr()->write($this->createLine(Factory::rpcError($uniqid, [
                    'message' => 'Target doesn\'t exist',
                ])));
                return;
            }

            $deferred = new Deferred();

            $deferred->promise()->then(
                function (array $payload) use ($uniqid) {
                    $this->getStdout()->write($this->createLine(Factory::rpcSuccess($uniqid, $payload)));
                },
                function ($payload) use ($uniqid) {
                    $this->getStderr()->write($this->createLine(Factory::rpcError($uniqid, $payload)));
                },
                function (array $payload) use ($uniqid) {
                    $this->getStdout()->write($this->createLine(Factory::rpcNotify($uniqid, $payload)));
                }
            );

            try {
                $this->callRpc($target, $payload, $deferred);
            } catch (\Exception $exception) {
                $deferred->reject($exception);
            }
        };
        $cb = $cb->bindTo($bindTo);
        $cb($this->target, $this->payload, $this->uniqid);
    }
}
