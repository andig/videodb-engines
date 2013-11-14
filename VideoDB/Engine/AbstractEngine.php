<?php

namespace VideoDB\Engine;

use VideoDB\Engine\Http\HttpClientInterface;

// @todo fix non-strict errors
error_reporting(E_ALL | E_STRICT);

abstract class AbstractEngine implements EngineInterface
{
    protected $httpClient;

    protected $serverUrl;
    protected $searchParameters;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getServerUrl()
    {
        return $this->serverUrl;
    }

    public function setSearchParameters($para)
    {
        $this->searchParameters = $para;
    }
}
