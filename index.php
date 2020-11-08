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

define('LOGGER_START', microtime(true));
ini_set('display_errors', 1);
header('Content-Type: text/plain');

define('BASE_PATH', realpath(dirname(__FILE__)));
spl_autoload_register(function ($className) {
    $filename = BASE_PATH . '/lib/' . str_replace('\\', '/', $className) . '.php';
    include($filename);
});

if (file_exists(__DIR__ . '/config/config.ini')) {
    \Maildiscover\Config::getInstance()->load(__DIR__. '/config/config.ini');
}

\Maildiscover\Logger::getInstance()->start();
$automailDiscover = new \Maildiscover\Maildiscover();
$automailDiscover->srv();
\Maildiscover\Logger::getInstance()->stop();
