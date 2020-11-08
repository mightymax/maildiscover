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

class Route implements \ArrayAccess
{
    const HTML_STATIC_ROUTE   = '/';
    const APPLE_CONFIG_ROUTE   = '/mobileconfig/';
    const MOZILLA_CONFIG_ROUTE = '/mail/config-v1.1.xml';
    const MICROSOFT_CONFIG_ROUTE   = '/autodiscover/autodiscover.xml';

    const REQUEST_METHODS = [
        '/' => ['GET'],
        '/mobileconfig/' => ['POST', 'GET'],
        '/mail/config-v1.1.xml' => ['GET'],
        '/autodiscover/autodiscover.xml' => ['POST']
    ];

    protected static $instance;

    protected $route = null;
    protected $query;
    protected $postdata;
    protected $method;

    /**
     * @var \Maildiscover\Logger
     */
    protected $logger;

   /**
     * @return \Maildiscover\Route
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct()
    {
        $this->logger = \Maildiscover\Logger::getInstance();
        $this->query = new Config();
        $config = Config::getInstance();
        $url = parse_url('http://foo.bar/' . ltrim($_SERVER['REQUEST_URI'], '/'));
        $path = \strtolower($url['path']);
        switch($path) {
            case self::APPLE_CONFIG_ROUTE:
            case self::MOZILLA_CONFIG_ROUTE:
            case self::MICROSOFT_CONFIG_ROUTE:
            case self::HTML_STATIC_ROUTE:
                $this->path = $path;
                break;
            case '/favicon.ico':
                \http_response_code(404);
                $this->logger->debug("404 for favicon.ico requests");
                exit();
            default:
                if (true === (bool)$config->get('allow_all_urls', false)) {
                    $this->logger->notice("route `{$path}` is not valid, but servic static HTML anyway (allow_all_urls=true)");
                    $this->path = self::HTML_STATIC_ROUTE;
                } else {
                    throw new Exception("Route `{$url['path']}` not found.", 404);
                }
        }
        $this->logger->debug("processing incoming route `{$this->path}`");

        $this->method = $_SERVER['REQUEST_METHOD'];
        if (!\in_array($this->method, self::REQUEST_METHODS[$this->path])) {
            if (isset($_GET['view']) && isset($_GET['emailaddress'])) {
                $this->logger->notice("method {$this->method} not allowed for route `{$this->path}`");
            } else {
                throw new Exception("Method `{$this->method}` not allowed for this URL.", 405);
            }
        }

        if ($this->isStatic()) {
            $this->logger->debug("static HTML page requested");
            return;
        }
        
        if ($this->method == 'GET' && isset($url['query'])) {
            $params = [];
            parse_str($url['query'], $params);
            $this->query = new Config($params);
        } else {
            $this->query = new Config($_POST);
            $this->postdata = file_get_contents('php://input');
        }

        if ($this->isMicrosoft() && $this->method == 'POST') {
            $this['emailaddress'] = $this->getEmailFromMicrosoftRequest($this->postdata);
        }

        if (!isset($this['emailaddress'])) {
            throw new Exception("After routing the app, no emailaddress is known.", 405);
        }

        if (!filter_var($this['emailaddress'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid emailaddress provided: `{$this['emailaddress']}`", 412);
        }

        list(,$hostname) = explode('@', $this['emailaddress']);
        if (!isset($config[$hostname])) {
            if (false === (bool)$config->get('allow_all_domains', false)) {
                $this->logger->notice("unkown hostname `{$hostname}` (allow_all_domains=false)");
                throw new \Maildiscover\Exception('Domain `'.$hostname.'` is not allowed for this service', 500);
            } else {
                $this->logger->notice("allowing service to undefined hostname `{$hostname}` (allow_all_domains=true)");
            }
        }

        $this->logger->debug("using `{$this['emailaddress']}` as emailaddress");
    }

    public function isApple()
    {
        return $this->path == self::APPLE_CONFIG_ROUTE;
    }

    public function isMozilla()
    {
        return $this->path == self::MOZILLA_CONFIG_ROUTE;
    }

    public function isMicrosoft()
    {
        return $this->path == self::MICROSOFT_CONFIG_ROUTE;
    }

    public function isStatic()
    {
        return $this->path == self::HTML_STATIC_ROUTE;
    }

    public function getEmailFromMicrosoftRequest($XMLString)
    {
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($XMLString)) {
            throw new Exception("No valid XML recieved", 400);
        }
        $xpath = new \DOMXPath($doc);
        $ns = 'http://schemas.microsoft.com/exchange/autodiscover/outlook/requestschema/2006';
        $xpath->registerNamespace("ms", $ns);
        $EMailAddress = $xpath->query('/ms:Autodiscover/ms:Request/ms:EMailAddress')->item(0);
        if (!$EMailAddress) {
            throw new Exception("Failed to look up <EmailAdress> (could be a namespace issue)", 400);
        }
        $this->logger->debug("successfully parsed Microsoft Request XML docment");
        return $EMailAddress->nodeValue;
    }

    public function __get($key)
    {
        switch($key) {
            case 'path':
            case 'method':
            case 'postdata':
            case 'query':
                return $this->$key;
        }
        return $this->query->get($key);
    }

    public function offsetSet($key, $val) {
        $this->query[$key] = $val;
    }

    public function offsetExists($key) {
        return isset($this->query[$key]);
    }

    public function offsetUnset($key) {
        unset($this->query[$key]);
    }

    public function offsetGet($key) 
    {
        if(isset($this->query[$key])) return $this->query[$key];
    }

}