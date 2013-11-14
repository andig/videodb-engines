<?php

namespace VideoDB\Engine\Http;

abstract class AbstractHttpClient implements HttpClientInterface
{
    public function post($url, $body = null)
    {
        throw(new \Exception('Not implemented'));
    }
}
