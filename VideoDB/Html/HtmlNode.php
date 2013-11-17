<?php

namespace VideoDB\Html;

/**
 * Author: Hitesh Kumar, IIT Delhi.
 * License: http://en.wikipedia.org/wiki/WTFPL
 */

class HtmlNode
{
    private $dom_xpath;
    private $node;

    public function __construct($node, $dom_xpath = null)
    {
        $this->node = $node;
        if ($dom_xpath) {
            $this->dom_xpath = $dom_xpath;
        }
    }

    // use $node->text for node's text.
    // use $node->html for node's inner HTML.
    // use $node->anything for node's attribute.
    public function __get($name)
    {
        if ($name == 'text' || $name == 'plaintext') {
            return $this->text();
        } elseif ($name == 'html') {
            return $this->html();
        } elseif ($this->node->hasAttribute($name)) {
            return $this->node->getAttribute($name);
        } else {
            return null;
        }
    }

    // finds nodes by css selector expression.
    // returns an array of nodes if $idx is not given, otherwise returns a single node at index $idx.
    // returns null if node not found or error in selector expression.
    // $idx can be negative (find from last).
    public function find($query, $idx = null)
    {
        $xpath = HtmlParser::css_to_xpath($query);

        return $this->xpath($xpath, $idx);
    }

    // // original code
    // public function find($query, $idx = null)
    // {
    //     $xpath = HtmlParser::css_to_xpath($query);

    //     return $this->xpath($xpath, $idx);
    // }

    // finds nodes by xpath expression.
    public function xpath($xpath, $idx = null)
    {
        $result = $this->dom_xpath->query($xpath, $this->node);
        if ($idx === null) {
            if (!$result) {
                return array();
            }
            return self::wrap_nodes($result, $this->dom_xpath);
        } elseif ($idx >= 0) {
            if (!$result) {
                return null;
            }
            return self::wrap_node($result->item($idx), $this->dom_xpath);
        } else {
            if (!$result) {
                return null;
            }
            return self::wrap_node($result->item($result->length + $idx), $this->dom_xpath);
        }
    }

    public function child($idx = null)
    {
        if (!$this->node->hasChildNodes()) {
            return array();
        }

        $nodes = array();
        foreach ($this->node->childNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $nodes[] = $node;
            }
        }

        if ($idx === null) {
            if (!$nodes) {
                return array();
            }

            return self::wrap_nodes($nodes, $this->dom_xpath);
        } elseif ($idx >= 0) {
            if (!$nodes) {
                return null;
            }

            return self::wrap_node($nodes[$idx], $this->dom_xpath);
        } else {
            if (!$nodes) {
                return null;
            }

            return self::wrap_node($nodes[count($nodes) + $idx], $this->dom_xpath);
        }
    }

    public function has_child()
    {
        if ($this->node->hasChildNodes()) {
            foreach ($this->node->childNodes as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE) {
                    return true;
                }
            }
        }

        return false;
    }

    public function first_child()
    {
        $node = $this->node->firstChild;
        while ($node && $node->nodeType !== XML_ELEMENT_NODE) {
            $node = $node->nextSibling;
        }

        return self::wrap_node($node, $this->dom_xpath);
    }

    public function last_child()
    {
        $node = $this->node->lastChild;
        while ($node && $node->nodeType !== XML_ELEMENT_NODE) {
            $node = $node->previousSibling;
        }

        return self::wrap_node($node, $this->dom_xpath);
    }

    public function parent()
    {
        $node = $this->node->parentNode;
        while ($node && $node->nodeType !== XML_ELEMENT_NODE) {
            $node = $node->parentNode;
        }

        return self::wrap_node($node, $this->dom_xpath);
    }

    public function next()
    {
        $node = $this->node->nextSibling;
        while ($node && $node->nodeType !== XML_ELEMENT_NODE) {
            $node = $node->nextSibling;
        }

        return self::wrap_node($node, $this->dom_xpath);
    }

    public function prev()
    {
        $node = $this->node->previousSibling;
        while ($node && $node->nodeType !== XML_ELEMENT_NODE) {
            $node = $node->previousSibling;
        }

        return self::wrap_node($node, $this->dom_xpath);
    }

    public function text()
    {
        return $this->node->nodeValue;
    }

    public function html()
    {
        $tag = $this->node_name();

        return preg_replace('@(^<' . $tag . '[^>]*>)|(</' . $tag . '>$)@', '', $this->outer_html());
    }

    public function inner_html()
    {
        return $this->html();
    }

    public function outer_html()
    {
        $doc = new \DOMDocument();
        $doc->appendChild($doc->importNode($this->node, true));
        $html = trim($doc->saveHTML());

        return $html;
    }

    public function node_name()
    {
        return $this->node->nodeName;
    }

    // Wrap given DOMNodes in HtmlNode and return an array of them.
    private static function wrap_nodes($nodes, $dom_xpath = null)
    {
        $wrapped = array();
        foreach ($nodes as $node) {
            $wrapped[] = new HtmlNode($node, $dom_xpath);
        }

        return $wrapped;
    }

    // Wrap a given DOMNode in HtmlNode.
    private static function wrap_node($node, $dom_xpath = null)
    {
        if ($node == null) {
            return null;
        }

        return new HtmlNode($node, $dom_xpath);
    }
}
