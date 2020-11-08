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


class DAV extends Base
{
    const CALDAV_TYPE = 'caldav';
    const CARDDAV_TYPE = 'carddav';

    public function __construct($dav_type)
    {
        $this->dav_type = $dav_type;
        parent::__construct();
    }

    public function init()
    {
        $config = self::getInstance();
        $domainConfig = $config->get($this['maildomain']);
        if ($domainConfig) {
            $config->merge($domainConfig);
        }

        if ($url = $config->get($this->dav_type . '_url')) {
            $this->url = $url;
        } else {
            $this->loadFromDns();
            if (!$this->url) {
                $this->loadFromDns(false);
            }
            if (!$this->url) {
                throw new \Maildiscover\Exception('failed to autodiscover '.$this->dav_type.' settings, try setting '.$this->dav_type .'_url manually in config');
            }
        }

    }

    public function SSL($asOnOff = false)
    {
        $ssl = $this->schema != 'http';
        return true == $asOnOff ? ($ssl ? 'on' : 'off'): $ssl;
    }


    protected function __setUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED & FILTER_FLAG_HOST_REQUIRED)) {
            throw new \Maildiscover\Exception('invalid URL `'.$url.'` for `'.$this->dav_type.'`');
        }
        $this->offsetSet('url', $url);
        $parts = parse_url($url);
        foreach ($parts as $key => $val) {
            $this->$key = $val;
        }
        $this->port = $this->schema != 'http' ? 443 : 80;
    }

    public function __setDav_type($type)
    {
        if ($type == self::CALDAV_TYPE || $type == self::CARDDAV_TYPE) {
            $this->offsetSet('dav_type', $type);
        } else {
            throw new \Maildiscover\Exception("Unkown DAV type `{$type}`.", 500);
        }
    }

    protected function loadFromDns($secure = true)
    {
        $type = $this->dav_type . ($secure?'s':'');
        $dns_srv = $this->dns_get_record($type, DNS_SRV, false);
        if ($dns_srv) {
            if (false === $this->allow_dns_lookup()) {
                $this->logger->notice("DNS lookup TXT records refused by config (allow_dns_lookup)");
                return false;
            }
            $hostname = '_'.$type.'._tcp.'.$this->maildomain;
            $dns_txt = \dns_get_record($hostname, DNS_TXT);
            if ($dns_txt) {
                $txt = $dns_txt[0];
                unset($txt['entries']);
                $this->logger->debug("DNS " . implode('  ', $txt));
                if (preg_match('/^path=(.+)$/', $dns_txt[0]['txt'], $match)) {
                    $path = $match[1];
                    $this->logger->debug("using {$this->dav_type} path `{$path}`");
                    $this->url = 'http' . ($dns_srv['port'] == 443 ? 's' : ''). '://' . $dns_srv['target'] . $path;
                } else {
                    $this->logger->notice("unable to get path from DNS TXT record {$hostname}");
                }
            }
        }

    }

}