<?php
/**
 * @copyright Mark Lindeman <mark.lindeman@e.email>
 * @author Mark Lindeman
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Maildiscover ;

class DOMDocument extends \DOMDocument
{
    public function __construct($path = null) {
        parent::__construct('1.0', 'UTF-8');
        if ($path) {
            $this->appendFromPath($path);
        }
    }

    public function appendNodeName(\DOMElement $target, $nodeName, $nodeValue = null) 
    {
        return $target->appendChild($this->createElement($nodeName, $nodeValue));
    }

    //Apple plist:
    public function addDictEntry(\DOMElement $target, $keyValue, $nodeValue = null)
    {
        $target->appendChild($this->createElement('key', $keyValue));
        if (is_int($nodeValue)) $dataType = 'integer';
        elseif (null === $nodeValue || is_array($nodeValue)) $dataType = 'array';
        elseif (is_bool($nodeValue)) {
            $dataType = $nodeValue ? 'true' : 'false';
            $nodeValue = null;
        }
        else $dataType = 'string';
        return $target->appendChild($this->createElement($dataType, $nodeValue));
    }

    public function appendChildren(Array $children, \DOMElement $node)
    {
        foreach ($children as $path) {
            $this->appendFromPath($path, $node);
        }
        return $node;
    }

    public function appendFromPath($path, \DOMElement $node = null, $namedAttributes = [])
    {
        if (null === $node) {
            $node = $this;
        }

        if (!is_array($path)) $path = explode('/', $path);
        foreach ($path as $nodeName) {
            $match = [];
            $attrs= [];
            $nodeValue = null;
            if (!preg_match('/^([a-zA-Z_0-9]+)$/', $nodeName) && preg_match('/^([a-zA-Z_0-9]+)(?:\[(.+)\])?(?:@(.+))?$/', $nodeName, $match)) {
                if (count($match)==3) $match[] = '';
                list(, $nodeName, $nodeValue, $attrsTxt) = $match;
                if ($attrsTxt) {
                    foreach(explode(',', $attrsTxt) as $pair) {
                        list($key, $val) = explode('=', trim($pair));
                        $attrs[$key] = $val;
                    }
                }
            }
            $node = $node->appendChild($this->createElement($nodeName, $nodeValue));
            $this->setAttrs($node, $attrs);
            if (isset($namedAttributes[$nodeName])) {
                $this->setAttrs($node, $namedAttributes[$nodeName]);
            }
        }
        return $node;
    }

    public function setAttrs(\DOMElement $node, $attrs) {
        foreach ($attrs as $key => $val) {
            $node->setAttribute($key, $val);
        }
        return $node;
    }

    public function __toString()
    {
        return $this->saveXML();
    }
}