<?php

namespace VideoDB\Html;

/**
 * Author: Hitesh Kumar, IIT Delhi.
 * License: http://en.wikipedia.org/wiki/WTFPL
 */

class HtmlParser
{
    public static function from_string($str, $xml = false)
    {
        libxml_use_internal_errors(true);
        $html = new \DOMDocument();

        if ($xml) {
            $html->loadXML($str);
        } else {
            $html->preserveWhiteSpace = true;
            $html->loadHTML($str);
        }

        libxml_clear_errors();

        $dom_xpath = new \DOMXPath($html);

        return new HtmlNode($html->documentElement, $dom_xpath);
    }

    public static function from_file($file, $xml = false)
    {
        $str = file_get_contents($file);

        return self::from_string($str, $xml);
    }

    // Converts a given CSS Selector expression to xpath expression.
    // This function is direct port of firebug's css to xpath convertor.
    public static function css_to_xpath($rule)
    {
        $reg_element = '/^([#.]?)([a-z0-9\\*_-]*)((\|)([a-z0-9\\*_-]*))?/i';
        $reg_attr1 = '/^\[([^\]]*)\]/i';
        $reg_attr2 = '/^\[\s*([^~=\s]+)\s*(~?=)\s*"([^"]+)"\s*\]/i';
        $reg_attr3 = '/^\[\s*([^~=\s]+)\s*(~?=)\s*\'([^\']+)\'\s*\]/i';
        $reg_attr4 = '/^\[\s*([^~=\s]+)\s*(~?=)\s*([^\]]+)\s*\]/i';
        $reg_pseudo = '/^:([a-z_-])+/i';
        $reg_combinator = '/^(\s*[>+\s])?/i';
        $reg_comma = '/^\s*,/i';

        $index = 1;
        $parts = array("//", "*");
        $last_rule = null;

        while ($rule && $rule !== $last_rule) {
            $last_rule = $rule;

            // Trim leading whitespace
            $rule = trim($rule);
            if (!$rule) {
                break;
            }

            // Match the element identifier
            preg_match($reg_element, $rule, $m);
            if ($m) {
                if (!$m[1]) {
                    // XXXjoe Namespace ignored for now
                    if (isset($m[5])) {
                        $parts[$index] = $m[5];
                    } else {
                        $parts[$index] = $m[2];
                    }
                } elseif ($m[1] == '#') {
                    $parts[] = "[@id='" . $m[2] . "']";
                } elseif ($m[1] == '.') {
                    $parts[] = "[contains(concat(' ',@class,' '), ' " . $m[2] . " ')]";
                }

                $rule = substr($rule, strlen($m[0]));
            }

            // Match attribute selectors
            preg_match($reg_attr4, $rule, $m);
            if (!$m) {
                preg_match($reg_attr3, $rule, $m);
            }
            if (!$m) {
                preg_match($reg_attr2, $rule, $m);
            }
            if ($m) {
                if ($m[2] == "~=") {
                    $parts[] = "[contains(concat(' ', @" . $m[1] . ", ' '), ' " . $m[3] . " ')]";
                } else {
                    $parts[] = "[@" . $m[1] . "='" . $m[3] . "']";
                }

                $rule = substr($rule, strlen($m[0]));
            } else {
                preg_match($reg_attr1, $rule, $m);
                if ($m) {
                    $parts[] = "[@" . $m[1] . "]";
                    $rule = substr($rule, strlen($m[0]));
                }
            }

            // Skip over pseudo-classes and pseudo-elements, which are of no use to us
            preg_match($reg_pseudo, $rule, $m);
            while ($m) {
                $rule = substr($rule, strlen($m[0]));
                preg_match($reg_pseudo, $rule, $m);
            }

            // Match combinators
            preg_match($reg_combinator, $rule, $m);
            if ($m && strlen($m[0])) {
                if (strpos($m[0], ">") !== false) {
                    $parts[] = "/";
                } elseif (strpos($m[0], "+") !== false) {
                    $parts[] = "/following-sibling::";
                } else {
                    $parts[] = "//";
                }

                $index = count($parts);
                $parts[] = "*";
                $rule = substr($rule, strlen($m[0]));
            }

            preg_match($reg_comma, $rule, $m);
            if ($m) {
                $parts[] = " | ";
                $parts[] = "//";
                $parts[] = "*";
                $index = count($parts) - 1;
                $rule = substr($rule, strlen($m[0]));
            }
        }

        $xpath = implode("", $parts);

        return $xpath;
    }
}
