<?php

namespace VideoDB\Engine;

class IMDB extends AbstractEngine
{
    public $serverUrl = 'http://www.imdb.com';

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
                    if ($ary[1] and $ary[2]) {
                        $info           = array();
                        $info['id']     = $ary[1];
                        $info['title']  = $ary[2];
                        $info['year']   = $ary[3];
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
        $body = $this->httpClient->get($url);
        // if (!$resp['success']) $CLIENTERROR .= $resp['error']."\n";

        // Check if it is a TV series episode
        if (preg_match('/<div id="titleTVEpisodes"/i', $body)) {
            $data['istv'] = 1;

            # find id of Series
            preg_match('/<a href="\/title\/tt(\d+)\/episodes.*?" title="Full Episode List.*"/i', $body, $ary);
            $data['tvseries_id'] = trim($ary[1]);
        }

        // Titles and Year
        if (isset($data['istv'])) {
            preg_match('/<meta name="title" content="&quot;(.*?)&quot;\s+(.*?)(.TV episode .*?)?( - IMDB)?"/si', $body, $ary);
            $data['title'] = trim($ary[1]);
            $data['subtitle'] = trim($ary[2]);

            if (preg_match('/<h1 class="header".*?>.*?<span class="nobr">\(.*?(\d\d\d\d)\)</si', $body, $ary)) {
                $data['year'] = $ary[1];
            }
        } else {
            preg_match('/<meta name="title" content="(IMDb - )?(.*?) \(.*?(\d\d\d\d).*?\)( - IMDb)?" \/>/si', $body, $ary);
            $data['year'] = trim($ary[3]);
            # split title - subtitle
            $data['title'] = trim($ary[2]);
            if (strpos($data['title'], ' - ')) {
                list($t, $s) = explode(' - ', $data['title'], 2);
                $data['title'] = trim($t);
                $data['subtitle'] = trim($s);
            }
        }
        # orig. title
        if (preg_match('/<span class="title-extra".+?>\s*"?(.*?)"?\s*<i>\(original title\)<\/i>\s*</si', $body, $ary)) {
            $data['origtitle'] = trim($ary[1]);
        }

        // Cover URL
        $data['coverurl'] = $this->matchCoverURL($body);

        // MPAA Rating
        if (preg_match('/<span\s?itemprop="contentRating">(.*?)</is', $body, $ary)) {
            $data['mpaa'] = trim($ary[1]);
        }

        // UK BBFC Rating
        # no longer appears on main page
        #preg_match('/>\s*UK:(.*?)<\/a>\s+/s', $body, $ary);
        #$data['bbfc'] = trim($ary[1]);

        // Runtime
        // many but not all yet have new <time itemprop="duration"> tag
        preg_match('/itemprop="duration".*?>(\d+)\s+min<\//si', $body, $ary);
        if (!count($ary)) {
            preg_match('/Runtime:?<\/h4>.*?>(\d+)\s+min/si', $body, $ary);
        }
        if (count($ary)) {
            $data['runtime']  = preg_replace('/,/', '', trim($ary[1]));
        }

        // Director
        if (preg_match('/Directors?:\s*<\/h4>(.+?)<\/div>/si', $body, $ary)) {
            if (preg_match_all('/<a.*?href="\/name\/nm.+?".*?>(.+?)<\/a>/si', $ary[1], $ary, PREG_PATTERN_ORDER)) {
                // TODO: Update templates to use multiple directors
                $data['director']  = trim(join(', ', $ary[1]));
            }
        }

        // Rating
        preg_match('/<span .*? itemprop="ratingValue">([\d\.]+)<\/span>/si', $body, $ary);
        $data['rating'] = trim($ary[1]);

        // Countries
        preg_match('/Country:\s*<\/h4>(.+?)<\/div>/si', $body, $ary);
        preg_match_all('/<a.*?href="\/country\/.+?".*?>(.+?)<\/a>/si', $ary[1], $ary, PREG_PATTERN_ORDER);
        $data['country'] = trim(join(', ', $ary[1]));

        // Languages
        preg_match('/Languages?:\s*<\/h4>(.+?)<\/div>/si', $body, $ary);
        preg_match_all('/<a.*?href="\/language\/.+?".*?>(.+?)<\/a>/si', $ary[1], $ary, PREG_PATTERN_ORDER);
        $data['language'] = trim(strtolower(join(', ', $ary[1])));

        // Genres (as Array)
        preg_match('/Genres:\s*<\/h4>(.+?)<\/div>/si', $body, $ary);
        preg_match_all('/<a.*?href="\/genres?\/.+?".*?>(.+?)<\/a>/si', $ary[1], $ary, PREG_PATTERN_ORDER);
        foreach ($ary[1] as $genre) {
            $data['genres'][] = trim($genre);
        }

        // for Episodes - try to get some missing stuff from the main series page
        if (isset($data['istv']) && (!$data['runtime'] or !$data['country'] or !$data['language'] or !$data['coverurl'])) {
            $sresp = $this->httpClient->get($this->serverUrl.'/title/tt'.$data['tvseries_id'].'/');
            if (!$sresp['success']) $CLIENTERROR .= $resp['error']."\n";

            # runtime
            if (!$data['runtime']) {
                preg_match('/itemprop="duration".*?>(\d+)\s+min<\//si', $sresp['data'], $ary);
                if (!$ary) {
                    preg_match('/Runtime:?<\/h4>.*?>(\d+)\s+min/si', $body, $ary);
                }
                $data['runtime']  = preg_replace('/,/', '', trim($ary[1]));
            }

            # country
            if (!$data['country']) {
                preg_match('/Country:\s*<\/h4>(.+?)<\/div>/si', $sresp['data'], $ary);
                preg_match_all('/<a.*?href="\/country\/.+?".*?>(.+?)<\/a>/si', $ary[1], $ary, PREG_PATTERN_ORDER);
                $data['country'] = trim(join(', ', $ary[1]));
            }

            # language
            if (!$data['language']) {
                preg_match('/Languages?:\s*<\/h4>(.+?)<\/div>/si', $sresp['data'], $ary);
                preg_match_all('/<a.*?href="\/language\/.+?".*?>(.+?)<\/a>/si', $ary[1], $ary, PREG_PATTERN_ORDER);
                $data['language'] = trim(strtolower(join(', ', $ary[1])));
            }

            # cover
            if (!$data['coverurl']) {
                $data['coverurl'] = $this->matchCoverURL($sresp['data']);
            }
        }

        // Plot
        preg_match('/<h2>Storyline<\/h2>.*?<p>(.*?)</si', $body, $ary);
        $data['plot'] = $ary[1];

        // Fetch credits
        $url = $this->getDataUrl($id) . 'fullcredits';
        $body = $this->httpClient->get($url);
        // if (!$resp['success']) $CLIENTERROR .= $resp['error']."\n";

        // Cast
        if (preg_match('#<table class="cast_list">(.*)#si', $body, $match)) {
            // no idea why it does not always work with (.*?)</table
            // could be some maximum length of .*?
            // anyways, I'm cutting it here
            $cast = '';
            $casthtml = substr($match[1],0,strpos( $match[1],'</table'));
            if (preg_match_all('#<td .*? itemprop="actor".*?>\s+<a href="/name/(nm\d+)/?.*?".*?>(.*?)</a>.*?<td class="character">(.*?)</td>#si', $casthtml, $ary, PREG_PATTERN_ORDER)) {
                for ($i=0; $i < sizeof($ary[0]); $i++) {
                    $actorid    = trim(strip_tags($ary[1][$i]));
                    $actor      = trim(strip_tags($ary[2][$i]));
                    $character  = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/&nbsp;/', ' ', $ary[3][$i]))));
                    $cast      .= "$actor::$character::$actorid\n";
                }
            }

            // remove html entities and replace &nbsp; with simple space
            // $data['cast'] = html_clean_utf8($cast);
            $data['cast'] = $cast;

            // sometimes appearing in series (e.g. Scrubs)
            $data['cast'] = preg_replace('#/ ... #', '', $data['cast']);
        }

        // Fetch plot
        $url = $this->getDataUrl($id) . 'plotsummary';
        $body = $this->httpClient->get($url);
        // if (!$resp['success']) $CLIENTERROR .= $resp['error']."\n";

        // Plot
        if (preg_match('/<P CLASS="plotpar">(.+?)<\/P>/is', $body, $ary)) {
            if ($ary[1]) {
                $data['plot'] = trim($ary[1]);
                $data['plot'] = preg_replace('/&#34;/', '"', $data['plot']);     //Replace HTML " with "
                //Begin removal of 'Written by' section
                $data['plot'] = preg_replace('/<a href="\/SearchPlotWriters.*?<\/a>/', '', $data['plot']);
                $data['plot'] = preg_replace('/Written by/', '', $data['plot']);
                $data['plot'] = preg_replace('/<i>\s+<\/i>/', ' ', $data['plot']);
                //End of removal of 'Written by' section
                $data['plot'] = preg_replace('/\s+/s', ' ', $data['plot']);
            }
            $data['plot'] = html_clean($data['plot']);
            #dump($data['plot']);
        }

        return $data;
    }

    public function matchCoverURL($data)
    {
        $url = '';

        # link to big-image-page?
        if (preg_match('/<td .*?id="img_primary".*?<a.*?href="(\/media\/rm.*?)".*?<\/td>/si', $data, $ary) ) {
            // Fetch the image page
            $body = $this->httpClient->get($this->serverUrl.$ary[1]);
            // if (!$resp['success']) $CLIENTERROR .= $resp['error']."\n";

            if (preg_match('/<img id="primary-img".*?src="(http:.+?)"/si', $body, $ary)) {
                $url = trim($ary[1]);
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
