<?php

namespace VideoDB\Engine;

interface EngineInterface
{
    public function getError();

    public function getServerUrl();

    public function getSearchUrl($q);
    public function setSearchParameters($para);
    public function search($q);

    public function getDataUrl($id);
    public function getData($id);
}
