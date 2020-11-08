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

namespace Maildiscover\Model;

abstract class Base extends \Maildiscover\Config
{
    abstract protected function init();

    /**
     * @var \Maildiscover\Logger
     */
    protected $logger;

    public function __construct()
    {
        $this->logger = \Maildiscover\Logger::getInstance();
        $config = \Maildiscover\Config::getInstance();
        $this['emailaddress'] = \Maildiscover\Route::getInstance()->emailaddress;
        list($this['username'], $this['maildomain']) = explode('@', $this['emailaddress']);

        $this->init();
    }

    protected function __validateFromClassConstants($key, $val, $prefix)
    {
        $reflect = new \ReflectionClass(get_class($this));
        $constants = $reflect->getConstants();
        $valid = [];
        array_walk($constants, function($val, $key) use (&$valid, $prefix) {
            if (0 === strpos($key, $prefix . '_')) {
                $valid[] = $val;
            }
        });
        if (!in_array($val, $valid)) {
            throw new \Maildiscover\Exception('Invalid value for `'.$key.': `'.$val.'` (valid values are '.implode('|', $valid).')', 500);
        }
    }

    protected function allow_dns_lookup()
    {
        return (bool)\Maildiscover\Config::getInstance()->get('allow_dns_lookup', true);
    }

    public function getmxrr($hostname)
    {
        if (false === $this->allow_dns_lookup()) {
            $this->logger->notice("getmxrr refused by config (allow_dns_lookup)");
            return false;
        }

        $hosts = [];
        $weight = [];
        getmxrr($hostname, $hosts, $weight);
        if (!count($hosts)) {
            throw new \Maildiscover\Exception("Failed to get MX records for domain '{$hostname}'", 500);
        }
        $this->logger->debug("found ".count($hosts). " MX records for {$hostname}");
        $mxhosts = array_combine($weight, $hosts);
        ksort($mxhosts);
        $host = array_shift($mxhosts);
        $this->logger->debug("Found MX record `{$host}` for hostname `{$hostname}`");
        return $host;
    }

    protected function dns_get_record($type, $dns_type = DNS_SRV, $autoSSLLookup = true)
    {
        if (false === $this->allow_dns_lookup()) {
            $this->logger->notice("dns_get_record refused by config (allow_dns_lookup)");
            return false;
        }
        $hostname = "_{$type}._tcp.{$this->maildomain}";
        $result = \dns_get_record($hostname, $dns_type);
        if (!count($result) && $autoSSLLookup) {
            $hostname = "_{$type}s._tcp.{$this->maildomain}";
            $result = \dns_get_record($hostname, $dns_type);
            if (!count($result)) return;
        }
        $this->logger->debug("found ".count($result). " {$dns_type} entries for {$hostname}");
        if (!count($result)) return;

        $pri = 1000000;
        $config = null;
        foreach ($result as $srv) {
            if ($srv['pri'] < $pri) {
                $pri = $srv['pri'];
                $config = $srv;
            }
        }
        $this->logger->debug("DNS " . implode('  ', $config));
        return new \Maildiscover\Config($config);
    }

    protected function __setMxhostname($val)
    {
        if (!\filter_var($val, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new \Maildiscover\Exception("Invalid value for maildomain: `{$val}`", 500);
        }
        $this->offsetSet('maildomain', $val);
    }

}