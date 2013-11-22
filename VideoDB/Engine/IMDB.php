<?php

namespace VideoDB\Engine;

use VideoDB\Html\Parser\HtmlParser;
use VideoDB\Html\Encoding;

class IMDB extends AbstractEngine
{
    public $serverUrl = 'http://www.imdb.com';

    protected $parser;

    protected function fetchAndParse($url)
    {
        $response = parent::fetch($url);
// echo($url.": ".((empty($response)) ? '<empty>' : '<set>')."\n");
        if ($response) {
            $response = preg_replace('#<head.*?/head>#si', '', $response); // fix messy header
// echo("resp: ".((empty($response)) ? '<empty>' : '<set>')."\n");
            $this->parser = HtmlParser::from_string($response);
        }
        return $response;
    }

    /**
     * Return parser node attributes gracefully handling missing match
     */
    private function match($selector, $attr = 'text', $parser = null)
    {
        $parser = ($parser) ?: $this->parser;
        if ($n = $parser->find($selector, 0)) {
            return trim($n->$attr);
        }
        return null;
    }

    private function matchText($selector, $parser = null)
    {
        return $this->match($selector, 'text', $parser);
    }

    private function matchList($selector, $parser = null)
    {
        $res = array();
        $parser = ($parser) ?: $this->parser;
        foreach ($parser->find($selector) as $r) {
            $res[] = trim($r->text);
        }
        return $res;
    }

    private function matchListAsString($selector, $parser = null)
    {
        return join(', ', $this->matchList($selector, $parser));
    }

    private function matchTitle($str, &$data)
    {
        $list             = explode(' - ', trim($str), 2);
        $data['title']    = trim($list[0]);
        $data['subtitle'] = (isset($list[1])) ? trim($list[1]) : false;
    }

    public function getSearchUrl($q)
    {
        $url = $this->serverUrl . '/find?s=all&amp&q='.urlencode($q);
        if (isset($this->searchParameter['aka'])) {
            $url .= ';s=tt;site=aka';
        }

        return $url;
    }

    public function search($q)
    {
        $url = $this->getSearchUrl($q);
        if (($body = $this->fetchAndParse($url)) === false) {
            return false;
        }

        $data = array();

        // match type
        if (preg_match('/^'.preg_quote($this->serverUrl, '/') . '\/[Tt]itle(\?|\/tt)([0-9?]+)\/?/', $this->httpClient->getEffectiveUrl(), $ary)) {
            // direct match (redirecting to individual title)
            $info = array();
            $info['id'] = $ary[2];

            // Title
            if (preg_match('/<title>(.*?) \([1-2][0-9][0-9][0-9].*?\)<\/title>/i', $body, $m)) {
                $this->matchTitle($m[1], $info);
            }
            $data[] = $info;
        } else {
            // multiple matches
            foreach ($this->parser->find('tr.findResult td.result_text') as $n) {
                if (preg_match('/<a href="\/title\/tt(\d+).*?" >(.*?)<\/a>\s?\(?(\d+)?\)?/i', $n->html, $ary)) {
                    if (count($ary) > 2) {
                        $info = array();
                        $info['id'] = $ary[1];
                        $info['title']  = $ary[2];
                        if (count($ary) > 3) {
                            $info['year']   = $ary[3];
                        }
                        $data[] = $info;
                    }
                }
            }
        }

        return $data;
    }

    public function getDataUrl($id)
    {
        // added trailing / to avoid redirect
        return $this->serverUrl . '/title/tt'.$id.'/';
    }

    public function getData($id)
    {
        $url = $this->getDataUrl($id);
        if (($body = $this->fetchAndParse($url)) === false) {
            return false;
        }

        $data = array();

        // Check if it is a TV series episode
        if ($a = $this->parser->find('a[title~=episode]', 0)) {
            $data['istv'] = 1;
            $data['tvseries_id'] = false;
            if (preg_match('/title\/tt(\d+)\/episodes/i', $a->href, $ary)) {
                $data['tvseries_id'] = $ary[1];
            }
        } else {
            $data['istv'] = false;
            $data['tvseries_id'] = false;
        }

        // Title
        if ($n = $this->parser->find('h1 span[itemprop=name]', 0)) {
            $this->matchTitle($n->text, $data);
        }

        // Year
        if (preg_match('/(\d\d\d\d)\)/si', $this->parser->find('h1 span.nobr', 0)->text, $ary)) {
            $data['year'] = $ary[1];
        } else {
            $data['year'] = false;
        }

        // Original title
        if (preg_match('/\s*"?(.*?)"?\s*<i>\(original title\)<\/i>/si', $this->matchText('span.title-extra'), $ary)) {
            $data['origtitle'] = trim($ary[1]);
        } else {
            $data['origtitle'] = false;
        }

        // // MPAA Rating
        // if ($n = $this->parser->find('span[itemprop=contentRating]', 0)) {
        //     $data['mpaa'] = $n->title;
        // } else {
        //     $data['mpaa'] = false;
        // }

        // Runtime
        $data['runtime'] = preg_replace('/\s*min/', '', $this->matchText('#titleDetails h4:contains(Runtime) + time'));

        // Director
        $data['director'] = $this->matchListAsString('div[itemprop=director] span');

        // Rating
        $data['rating'] = $this->matchText('span[itemprop=ratingValue]');

        // Countries
        $data['country'] = $this->matchListAsString('#titleDetails h4:contains(Country) + a');

        // Languages
        $data['language'] = $this->matchListAsString('#titleDetails h4:contains(Language) + a');

        // Genres (as Array)
        $data['genres'] = $this->matchListAsString('#titleStoryLine h4:contains(Genre) + a');

        // Plot
        $data['plot'] = $this->matchText('div[itemprop=description]');

        // Cover URL
        $data['coverurl'] = $this->matchCoverURL();

        // Credits
        $body = $this->fetchAndParse($this->getDataUrl($id) . 'fullcredits');

        // Cast
        $cast = '';
        foreach ($this->parser->find('.cast_list tr[class]') as $n) {
            if (preg_match_all('#<td .*? itemprop="actor".*?>\s+<a href="/name/(nm\d+)/?.*?".*?>(.*?)</a>.*?<td class="character">(.*?)</td>#si', $n->html, $ary, PREG_PATTERN_ORDER)) {
                for ($i=0; $i < sizeof($ary[0]); $i++) {
                    $actorid    = trim(strip_tags($ary[1][$i]));
                    $actor      = trim(strip_tags($ary[2][$i]));
                    $character  = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/&nbsp;/', ' ', $ary[3][$i]))));
                    $cast      .= "$actor::$character::$actorid\n";
                }
            }

            // cleanup
            $cast = Encoding::html_clean($cast);
            $data['cast'] = preg_replace('#/ ... #', '', $cast);
        }
        $data['cast'] = $cast;

        // Plot
        // @todo support multiple
        $body = $this->fetchAndParse($this->getDataUrl($id) . 'plotsummary');
        $data['plot'] = $this->matchText('p.plotSummary');
        $data['plot'] = Encoding::html_clean($data['plot']);

        // MPAA Rating
        $body = $this->fetchAndParse($this->getDataUrl($id) . 'parentalguide');
        $data['mpaa'] = $this->matchText('h5>a:contains(MPAA):parent + div.info-content');
        // if ($n = $this->parser->find('h5 a:contains(MPAA)', 0)) {
        //     $data['mpaa'] = $this->matchText('div.info-content', $n->parent()->parent());
        // } else {
        //     $data['mpaa'] = false;
        // }

        // for Episodes - try to get some missing stuff from the main series page
        if ($data['istv'] && $data['tvseries_id']) {
            $body = $this->fetchAndParse($this->getDataUrl($data['tvseries_id']));

            if (!$data['runtime']) {
                $data['runtime'] = preg_replace('/\s*min/', '', $this->parser->find('#titleDetails h4:contains(Runtime) + time', 0)->text);
            }

            if (!$data['director']) {
                $data['director'] = $this->matchListAsString('div[itemprop=director] span');
            }

            if (!$data['rating']) {
                $data['rating'] = $this->parser->find('span[itemprop=ratingValue]', 0)->text;
            }

            if (!$data['country']) {
                $data['country'] = $this->matchListAsString('#titleDetails h4:contains(Country) + a');
            }

            if (!$data['language']) {
                $data['language'] = $this->matchListAsString('#titleDetails h4:contains(Language) + a');
            }

            if (!$data['coverurl']) {
                $data['coverurl'] = $this->matchCoverURL();
            }
        }

        return $data;
    }

    /**
     * @todo  fix modifying parser
     */
    public function matchCoverURL()
    {
        $url = false;

        if ($n = $this->parser->find('#img_primary img', 0)) {
            // embedded img
            $url = $this->serverUrl . $n->src;

            // full-size img
            if ($this->fetchAndParse($this->serverUrl . $this->parser->find('#img_primary a', 0)->href)) {
                if ($n = $this->parser->find('#primary-img', 0)) {
                    $url = $this->serverUrl . $n->src;
                }
            }
        }

        return $url;
    }

    public function getActorUrl($name, $id = null)
    {
        $path = ($id) ? 'name/' . urlencode($id) . '/' : 'Name?' . urlencode(Encoding::html_entity_decode_all($name, true));

        return $this->serverUrl . '/' . $path;
    }

    /**
     * Parses Actor-Details
     *
     * Find image and detail URL for actor, not sure if this can be made
     * a one-step process?
     *
     * @author                Andreas Goetz <cpuidle@gmx.de>
     * @param  string $name Name of the Actor
     * @return array  array with Actor-URL and Thumbnail
     */
    public function searchActor($name, $id = null)
    {
        // search directly by id or via name?
        $url = $this->getActorUrl($name, $id);
        if (($body = $this->fetchAndParse($url)) === false) {
            return false;
        }

        $data = array();

        // if not direct match load best match
        if (preg_match('#<b>Popular Names</b>.+?<a\s+href="(.*?)">#i', $body, $m) ||
            preg_match('#<b>Names \(Exact Matches\)</b>.+?<a\s+href="(.*?)">#i', $body, $m) ||
            preg_match('#<b>Names \(Approx Matches\)</b>.+?<a\s+href="(.*?)">#i', $body, $m))
        {
            if (!preg_match('/http/i', $m[1])) $m[1] = $this->serverUrl . $m[1];
            $body = $this->fetchAndParse($m[1]);
        }

        // only search in img_primary <td> - or we get far to many useless images
        if ($n = $this->parser->find('#img_primary', 0)) {
            $data[] = array(
                $this->match('a', 'href', $n),
                $this->match('img', 'src', $n)
                // $n->find('a', 0)->href,
                // $n->find('img', 0)->src
            );
        }

        return $data;
    }
}
