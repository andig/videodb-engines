<?php

namespace VideoDB\Engine;

use VideoDB\Html\HtmlParser;
use VideoDB\Html\Encoding;

class IMDB extends AbstractEngine
{
    public $serverUrl = 'http://www.imdb.com';

    private function fetchAndParse($url)
    {
        $body = $this->httpClient->get($url);
        $this->parser = HtmlParser::from_string($body);
        return $body;
    }

    private function addArray($q, $parser = null)
    {
        $res = array();
        $parser = ($parser) ?: $this->parser;
        foreach ($parser->find($q) as $r) {
            $res[] = trim($r->text);
        }
        return $res;
    }

    private function addArrayString($q, $parser = null)
    {
        return join(', ', $this->addArray($q, $parser));
    }

    // @todo fix searching from node
    private function matchDetailString($sectionSelector, $match, $childSelector)
    {
        foreach ($this->parser->find($sectionSelector . ' h4') as $r) {
            if (preg_match('/' . $match . '/', $r->text)) {
                $parser = HtmlParser::from_string($r->parent()->html);
                return $this->addArrayString($childSelector, $parser);
            }
        }
        return null;
    }

    // @todo fix searching from node
    private function matchDetailArray($sectionSelector, $match, $childSelector)
    {
        foreach ($this->parser->find($sectionSelector . ' h4') as $r) {
            if (preg_match('/' . $match . '/', $r->text)) {
                $parser = HtmlParser::from_string($r->parent()->html);
                return $this->addArray($childSelector, $parser);
            }
        }
        return null;
    }

    public function getSearchUrl($q)
    {
        $url = $this->serverUrl.'/find?s=all&amp&q='.urlencode($q);
        if (isset($this->searchParameter['aka'])) {
            $url .= ';s=tt;site=aka';
        }

        return $url;
    }

    public function search($q)
    {
        $data = array();

        $url = $this->getSearchUrl($q);
        $body = $this->httpClient->get($url);
        // if (!$resp['success']) $CLIENTERROR .= $resp['error']."\n";
// echo($body);die;

        // match type
        if (preg_match(
            '/^'.preg_quote($this->serverUrl,'/').'\/[Tt]itle(\?|\/tt)([0-9?]+)\/?/',
            $this->httpClient->getEffectiveUrl(),
            $single)
        ) {
            // direct match (redirecting to individual title)
            $info       = array();
            $info['id'] = $single[2];

            // Title
            if (preg_match('/<title>(.*?) \([1-2][0-9][0-9][0-9].*?\)<\/title>/i', $body, $m)) {
                list($t, $s)        = explode(' - ', trim($m[1]), 2);
                $info['title']      = trim($t);
                $info['subtitle']   = trim($s);
            }

            $data[]     = $info;
        } elseif (preg_match_all(
            '/<tr class="findResult.*?">(.*?)<\/tr>/i',
            $body,
            $multi,
            PREG_SET_ORDER)
        ) {
            // multiple matches
            foreach ($multi as $row) {
                if (preg_match('/<td class="result_text">\s*<a href="\/title\/tt(\d+).*?" >(.*?)<\/a>\s?\(?(\d+)?\)?/i', $row[1], $ary)) {
                    if (count($ary) > 2) {
                        $info           = array();
                        $info['id']     = $ary[1];
                        $info['title']  = $ary[2];
                        if (count($ary) > 3) {
                            $info['year']   = $ary[3];
                        }
                        $data[]         = $info;
                    }
                }
    #           dump($info);
            }
        }

        return $data;
    }

    public function getDataUrl($id)
    {
        // added trailing / to avoid redirect
        return $this->serverUrl.'/title/tt'.$id.'/';
    }

    public function getData($id)
    {
        $data= array(); // result
        $ary = array(); // temp

        // fetch mainpage
        $url = $this->getDataUrl($id);
        $body = $this->fetchAndParse($url);

        // Check if it is a TV series episode
        if ($a = $this->parser->find('a[title~=episode]', 0)) {
            $data['istv'] = 1;
            if (preg_match('/title\/tt(\d+)\/episodes/i', $a->href, $ary)) {
                $data['tvseries_id'] = trim($ary[1]);
            }
        } else {
            $data['istv'] = false;
        }

        // Title
        if ($n = $this->parser->find('h1 span[itemprop=name]', 0)) {
            if (count($l = explode(' - ', $n->text, 2))) {
                $data['title'] = trim($l[0]);
                $data['subtitle'] = (isset($l[1])) ? trim($l[1]) : '';
            } else {
                $data['title'] = $n->text;
                $data['subtitle'] = false;
            }
        }

        // Year
        if ($n = $this->parser->find('h1 span.nobr', 0)) {
            if (preg_match('/(\d\d\d\d)\)/si', $n->text, $ary)) {
                $data['year'] = $ary[1];
            }
        }

        // Original title
        if (preg_match('/<span class="title-extra".+?>\s*"?(.*?)"?\s*<i>\(original title\)<\/i>\s*</si', $body, $ary)) {
            $data['origtitle'] = trim($ary[1]);
        }

        // Cover URL
        $data['coverurl'] = $this->matchCoverURL($body);

        // MPAA Rating
        if (preg_match('/<span\s?itemprop="contentRating">(.*?)</is', $body, $ary)) {
            $data['mpaa'] = trim($ary[1]);
        }

        // Runtime
        $data['runtime'] = preg_replace('/\s*min/', '', $this->matchDetailString('#titleDetails', 'Runtime', 'time'));

        // Director
        $data['director'] = $this->addArrayString('div[itemprop=director] span');

        // Rating
        $data['rating'] = $this->addArrayString('span[itemprop=ratingValue]');

        // Countries
        $data['country'] = $this->matchDetailString('#titleDetails', 'Country', 'a');

        // Languages
        $data['language'] = $this->matchDetailString('#titleDetails', 'Language', 'a');

        // Genres (as Array)
        $data['genres'] = $this->matchDetailArray('#titleStoryLine', 'Genre', 'a');

        // Plot
        if ($n = $this->parser->find('div[itemprop=description]', 0)) {
            $data['plot'] = $n->text;
        } else {
            $data['plot'] = false;
        }

        // Fetch credits
        $url = $this->getDataUrl($id) . 'fullcredits';
        $body = $this->fetchAndParse($url);

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
            // $data['cast'] = html_clean_utf8($cast);

            // sometimes appearing in series (e.g. Scrubs)
            $data['cast'] = preg_replace('#/ ... #', '', $cast);
        }
        $data['cast'] = $cast;

        // Fetch plot
        $url = $this->getDataUrl($id) . 'plotsummary';
        $body = $this->fetchAndParse($url);

        // Plot
        // @todo support multiple
        if ($n = $this->parser->find('p.plotSummary', 0)) {
            $data['plot'] = $n->text;
            //Replace HTML " with "
            // $data['plot'] = preg_replace('/&#34;/', '"', $data['plot']);
            $data['plot'] = preg_replace('/\s+/s', ' ', $data['plot']);
            $data['plot'] = Encoding::html_clean($data['plot']);
        }

        // for Episodes - try to get some missing stuff from the main series page
        if ($data['istv'] && (!$data['runtime'] or !$data['country'] or !$data['language'] or !$data['coverurl'])) {
            $body = $this->httpClient->get($this->serverUrl.'/title/tt'.$data['tvseries_id'].'/');
            $this->parser = HtmlParser::from_string($body);
            // if (!$resp['success']) $CLIENTERROR .= $resp['error']."\n";

            # runtime
            if (!$data['runtime']) {
                preg_match('/itemprop="duration".*?>(\d+)\s+min<\//si', $body, $ary);
                if (!$ary) {
                    preg_match('/Runtime:?<\/h4>.*?>(\d+)\s+min/si', $body, $ary);
                }
                $data['runtime']  = preg_replace('/,/', '', trim($ary[1]));
            }

            # country
            if (!$data['country']) {
                preg_match('/Country:\s*<\/h4>(.+?)<\/div>/si', $body, $ary);
                if (preg_match_all('/<a.*?href="\/country\/.+?".*?>(.+?)<\/a>/si', $ary[1], $ary, PREG_PATTERN_ORDER)) {
                    $data['country'] = trim(join(', ', $ary[1]));
                }
            }

            # language
            if (!$data['language']) {
                preg_match('/Languages?:\s*<\/h4>(.+?)<\/div>/si', $body, $ary);
                if (preg_match_all('/<a.*?href="\/language\/.+?".*?>(.+?)<\/a>/si', $ary[1], $ary, PREG_PATTERN_ORDER)) {
                    $data['language'] = trim(strtolower(join(', ', $ary[1])));
                }
            }

            # cover
            if (!$data['coverurl']) {
                $data['coverurl'] = $this->matchCoverURL($body);
            }
        }

        return $data;
    }

    public function matchCoverURL($body)
    {
        $url = false;

        // embedded img
        if ($n = $this->parser->find('#img_primary img', 0)) {
            $url = $this->serverUrl . $n->src;
        }
        // full-size img
        if ($n = $this->parser->find('#img_primary a', 0)) {
            $body = $this->httpClient->get($this->serverUrl . $n->href);
            $parser = HtmlParser::from_string($body);

            if ($n = $parser->find('#primary-img', 0)) {
                $url = $this->serverUrl . $n->src;
            }
        }

        return $url;
    }

    public function imdbActorUrl($name, $id)
    {
        $path = ($id) ? 'name/'.urlencode($id).'/' : 'Name?'.urlencode(html_entity_decode_all($name));

        return $this->serverUrl.'/'.$path;
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
    public function imdbActor($name, $actorid)
    {
        // search directly by id or via name?
        $resp   = $this->httpClient->get(imdbActorUrl($name, $actorid));

        $ary    = array();

        // if not direct match load best match
        if (preg_match('#<b>Popular Names</b>.+?<a\s+href="(.*?)">#i', $body, $m) ||
            preg_match('#<b>Names \(Exact Matches\)</b>.+?<a\s+href="(.*?)">#i', $body, $m) ||
            preg_match('#<b>Names \(Approx Matches\)</b>.+?<a\s+href="(.*?)">#i', $body, $m))
        {
            if (!preg_match('/http/i', $m[1])) $m[1] = $this->serverUrl.$m[1];
            $resp = $this->httpClient->get($m[1], true);
        }

        // now we should have loaded the best match

        // only search in img_primary <td> - or we get far to many useless images
        if (preg_match('/<td.*?id="img_primary".*?>(.*?)<\/td>/si',$body, $match)) {
            if (preg_match('/.*?<a.*?href="(.+?)"\s*?>\s*<img\s+.*?src="(.*?)"/si', $match[1], $m)) {
                $ary[0][0] = $m[1];
                $ary[0][1] = $m[2];
            }
        }

        return $ary;
    }
}
