<?php

namespace VideoDB\Engine;

use VideoDB\Engine\Http\HttpClientInterface;

// @todo fix non-strict errors
error_reporting(E_ALL | E_STRICT);

abstract class AbstractEngine implements EngineInterface
{
    protected $httpClient;
    protected $error;

    protected $serverUrl;
    protected $searchParameters;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    protected function fetch($url)
    {
        try {
            $response = $this->httpClient->get($url);
            $this->error = false;
        }
        catch (Exception $e) {
            $response = false;
            $this->error = $e;
        }
        return $response;
    }

    public function getServerUrl()
    {
        return $this->serverUrl;
    }

    public function setSearchParameters($para)
    {
        $this->searchParameters = $para;
    }

    public function getError()
    {
        return $this->error;
    }
}
