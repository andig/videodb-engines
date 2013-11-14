<?php

namespace VideoDB\Engine\Http;

use Guzzle\Http;

class GuzzleAdapter extends AbstractHttpClient
{
    protected $guzzle;

    protected $path;
    protected $response;

    public function __construct(\Guzzle\Http\ClientInterface $guzzle)
    {
        $this->guzzle = $guzzle;
    }

    protected function parseUrl($url)
    {
        if ($parseUrl = parse_url($url)) {
            $baseUrl = (isset($parseUrl['scheme'])) ? $parseUrl['scheme'] . '://' : 'http://';
            $baseUrl .= (isset($parseUrl['host'])) ? $parseUrl['host'] : '';
            $this->guzzle->setBaseUrl($baseUrl);

            $this->path = (isset($parseUrl['path'])) ? $parseUrl['path'] : '';
            $this->path .= (isset($parseUrl['query'])) ? '?' . $parseUrl['query'] : '';

            return true;
        } else {
            throw(new \Exception('Invalid Url: ' . $url));
        }
    }

    public function get($url)
    {
        if ($this->parseUrl($url)) {
            $request = $this->guzzle->get($this->path);
            if ($this->response = $request->send()) {
                return ($this->response->getBody(true));
            } else {
                return false;
            }
        }
    }

    public function getEffectiveUrl()
    {
        return (isset($this->response)) ? $this->response->getEffectiveUrl() : false;
    }
}
