<?php

namespace IXR;

use Typecho\Http\Client as HttpClient;

#[\AllowDynamicProperties]
class Client
{
    /** 默认客户端 */
    private const DEFAULT_USERAGENT = 'Typecho XML-RPC PHP Library';

    private string $url;

    private Message $message;

    private ?string $prefix;

    private Error $error;

    public function __construct(
        string $url,
        ?string $prefix = null
    ) {
        $this->url = $url;
        $this->prefix = $prefix;
    }

    private function rpcCall(string $method, array $args): bool
    {
        $request = new Request($method, $args);
        $xml = $request->getXml();

        $client = HttpClient::get();
        if (!$client) {
            $this->error = new Error(-32300, 'transport error - could not open socket');
            return false;
        }

        try {
            $client->setHeader('Content-Type', 'text/xml')
                ->setHeader('User-Agent', self::DEFAULT_USERAGENT)
                ->setData($xml)
                ->send($this->url);
        } catch (HttpClient\Exception $e) {
            $this->error = new Error(-32700, $e->getMessage());
            return false;
        }

        $contents = $client->getResponseBody();

        // Now parse what we've got back
        $this->message = new Message($contents);
        if (!$this->message->parse()) {
            // XML error
            $this->error = new Error(-32700, 'parse error. not well formed');
            return false;
        }

        if ($this->message->messageType == 'fault') {
            $this->error = new Error($this->message->faultCode, $this->message->faultString);
            return false;
        }

        return true;
    }

    /**
     * 增加前缀
     * <code>
     * $rpc->metaWeblog->newPost();
     * </code>
     */
    public function __get(string $prefix): Client
    {
        return new self($this->url, $this->prefix . $prefix . '.');
    }

    public function __call($method, $args)
    {
        $return = $this->rpcCall($this->prefix . $method, $args);

        if ($return) {
            return $this->getResponse();
        } else {
            throw new Exception($this->getErrorMessage(), $this->getErrorCode());
        }
    }

    public function getResponse()
    {
        // methodResponses can only have one param - return that
        return $this->message->params[0] ?? null;
    }

    public function isError(): bool
    {
        return isset($this->error);
    }

    private function getErrorCode(): int
    {
        return $this->error->code;
    }

    private function getErrorMessage(): string
    {
        return $this->error->message;
    }
}
