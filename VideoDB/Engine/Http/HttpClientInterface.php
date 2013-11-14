<?php

namespace VideoDB\Engine\Http;

interface HttpClientInterface
{
    public function get($url);
    public function post($url, $body = null);
    public function getEffectiveUrl();
}
