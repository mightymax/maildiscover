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

namespace Maildiscover;

class Template
{
    protected $html = '';

    protected $params = [];

    protected $raw = false;

    public function __construct($tmpl, $params = [], $raw = false)
    {
        $this->raw = $raw;
        $tmpl = BASE_PATH . '/templates/' . $tmpl . '.html';
        if (!file_exists($tmpl)) {
            throw new Exception("Template `{$tmpl}` not found", 500);
        }
        $this->html = \file_get_contents($tmpl);
        foreach ($params as $key => $val) {
            $this->html = str_replace('{{' . $key . '}}', $val, $this->html);
        }
    }

    public static function factory($tmpl, Array $params = [], $raw = false)
    {
        return new self($tmpl, $params, $raw);
    }

    /**
     * @returns \DOMDocument;
     */
    public function toDOMDocument()
    {
        $doc = new \DOMDocument();
        if (!@$doc->loadXML((string)$this)) {
            throw new \Maildiscover\Exception("Template parsing resulted in an invalid XML document.", 500);
        }
        return $doc;
    }

    public function __toString()
    {
        if ($this->raw) return $this->html;
        $html = new self('main', ['tmpl' => $this->html], true);
        return (string)$html;
    }
}