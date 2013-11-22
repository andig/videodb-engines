<?php

namespace VideoDB\Html;

class Encoding
{
    protected $httpClient;

    protected $serverUrl;
    protected $searchParameters;

    /**
     * Like html_entity_decode() but also supports numeric entities.
     * Output encoding is ISO-8852-1.
     *
     * @author www.php.net
     * @param  string   $string  html entity loaded string
     * @return string            html entity free string
     */
    public static function html_entity_decode_all($str, $utf8 = false)
    {
        // replace numeric entities
        if ($utf8) {
            $str = preg_replace_callback('~&#x([0-9a-f]+);~i', create_function('$c', 'return VideoDB\Html\Encoding::code2utf(hexdec($c));'), $str);
            $str = preg_replace_callback('~&#([0-9]+);~', create_function('$c', 'return VideoDB\Html\Encoding::code2utf($c);'), $str);
        } else {
            $str = preg_replace_callback('~&#x([0-9a-f]+);~i', create_function('$c', 'return chr(hexdec($c));'), $str);
            $str = preg_replace_callback('~&#([0-9]+);~', create_function('$c', 'return chr($c);'), $str);
        }

        // replace literal entities
        if ($utf8) {
            foreach (get_html_translation_table(HTML_ENTITIES) as $val => $key) {
                $trans_tbl[$key] = utf8_encode($val);
            }
        } else {
            $trans_tbl = array_flip(get_html_translation_table(HTML_ENTITIES));
        }

        return strtr($str, $trans_tbl);
    }

    /**
     * Returns the utf-8 encoding corresponding to the unicode character value
     * @author  from php.net, courtesy - romans@void.lv
     */
    public static function code2utf($num)
    {
        if ($num < 128) return chr($num);
        if ($num < 2048) return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
        if ($num < 65536) return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        if ($num < 2097152) return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        return '';
    }

    /**
     * Clean HTML entities, tags and replace &nbsp; special spaces
     * Output encoding is UTF-8.
     *
     * @author Andreas Goetz    <cpuidle@gmx.de>
     * @param  string   $str    html entity loaded string
     * @return string           html entity free string
     */
    public static function html_clean($str)
    {
    #   this replacement breaks unicode enitity encoding as A0 might occor as part of any character
    #   $str    = str_replace(chr(160), ' ', $str);
        $str    = preg_replace('/\s+/s', ' ', $str);
        $str    = self::html_entity_decode_all(strip_tags($str), true);
        return trim($str);
    }
}
