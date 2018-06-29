<?php

namespace Curia\Framework\Http;

use Zend\Diactoros\Response as ZendResponse;

class Response extends ZendResponse
{
    public function json()
    {
        return $this->withHeader('accept', 'application/json');
    }

    /**
     * 发送响应
     */
    public function send()
    {
        if (!headers_sent()) {
            // Status
            header(sprintf('HTTP/%s %s %s',
                $this->getProtocolVersion(),
                $this->getStatusCode(),
                $this->getReasonPhrase()
            ), true, $this->getStatusCode());

            // Headers
            foreach ($this->getHeaders() as $header => $values) {
                foreach ($values as $value) {
                    header($header.': '.$value, false);
                }
            }
        }

        $stream = $this->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        while (!$stream->eof()) {
            echo $stream->read(1024 * 8);
        }
    }
}