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

class Exception extends \Exception {
    
    public function __construct($message = "", $code = 500, \Throwable $previous = null)
    {   
        Logger::getInstance()->error($message, $code);
        Logger::getInstance()->error("__FILE__ ".$this->getFile(). " __LINE__" . $this->getLine());
        parent::__construct($message, $code, $previous);
    }

    function toHTML()
    {
        http_response_code($this->getCode());
        header('Content-Type: text/html; charset=utf8');
        $params = [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];

        if (Logger::hasLevel(Logger::DEBUG)) {
            if (\defined('BASE_PATH')) {
                $base_path = rtrim(BASE_PATH, '/') .'/';
            } else {
                $basepath = '';
            }
            $params['trace'] = (string)Template::factory('stacktrace', [
                'trace' => str_replace($base_path, '', $this->getTraceAsString()),
                'file' => str_replace($base_path, '', $this->getFile()),
                'line' => $this->getLine()
            ], true);
        } else {
            $params['trace'] = '';
        }
        $html = new Template('error', $params);
        echo $html;
    }
}