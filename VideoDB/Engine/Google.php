<?php
/**
 * Google Image Parser
 *
 * Lookup cover images from Google
 *
 * @package Engines
 * @author  Andreas Götz    <cpuidle@gmx.de>
 *
 * @link    http://images.google.com  Google image search
 * @link    http://code.google.com/apis/ajaxsearch/documentation/   API doc
 */

namespace VideoDB\Engine;

use VideoDB\Html\Encoding;

class Google extends AbstractEngine
{
    public $serverUrl = 'http://www.google.com';

    // return array('name' => 'Google', 'stable' => 1, 'capabilities' => array('image'));
/*
    public function getError();

    public function getServerUrl();

    public function getSearchUrl($q);
    public function setSearchParameters($para);
    public function search($q);

    public function getDataUrl($id);
    public function getData($id);
*/
    /**
     * Search an image on Google
     *
     * Searches for a given title on the google and returns the found links in
     * an array
     *
     * @param   string    The search string
     * @return  array     Associative array with id and title
     */
    public function search($q)
    {
        $page = 1;
        $data = array();

        do {
            $url = "http://ajax.googleapis.com/ajax/services/search/images?v=1.0&rsz=large&q=".urlencode($q)."&start=".count($data);
            if (($body = $this->fetch($url)) === false) {
                return false;
            }
            if (($json = json_decode($body)) === false) {
                return false;
            }

            foreach ($json->responseData->results as $row) {
                $res = array();
                $res['title']   = $row->width.'x'.$row->height; // width x height
                $res['imgsmall']= $row->tbUrl;                  // small thumbnail url
                $res['coverurl']= $row->url;                    // resulting target url
                $data[] = $res;
            }
        } while ($page++ < 3);
        // Google does not return more than 4 pages of results. Limiting to 2 for performance

        return $data;
    }
}
