<?php

namespace VideoDB\Engine;

static class Encoding
{
    protected $httpClient;

    protected $serverUrl;
    protected $searchParameters;

    public static function html_entity_decode_all($string)
    {
        // replace numeric entities
        $string = preg_replace_callback('~&#x([0-9a-f]+);~i', create_function('$c', 'return chr(hexdec($c));'), $string);
        $string = preg_replace_callback('~&#([0-9]+);~', create_function('$c', 'return chr($c);'), $string);

        // replace literal entities
        $trans_tbl = get_html_translation_table(HTML_ENTITIES);
        $trans_tbl = array_flip($trans_tbl);

        return strtr($string, $trans_tbl);
    }

    public static function html_clean($str)
    {
        return trim(str_replace(chr(160), ' ', self::html_entity_decode_all($str)));
    }
}
