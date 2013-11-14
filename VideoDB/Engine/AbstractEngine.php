<?php

namespace VideoDB\Engine;

use VideoDB\Engine\Http\HttpClientInterface;

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
