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

class Outgoing extends Base
{
    const SOCKET_TYPE_SSL = 'SSL';
    const SOCKET_TYPE_TLS = 'TLS'; //same protocol as SSL
    const SOCKET_TYPE_STARTTLS = 'STARTTLS';
    const SOCKET_TYPE_PLAIN = 'PLAIN';

    const OUTGOING_MAIL_PROTOCOL = 'smtp';

    protected $_ports = [
        self::SOCKET_TYPE_SSL => 465,
        self::SOCKET_TYPE_TLS => 465,
        self::SOCKET_TYPE_STARTTLS => 587,
        self::SOCKET_TYPE_PLAIN => 25
    ];

    protected $_dns;

    protected  function init() 
    {
        $config = self::getInstance();
        $domainConfig = $config->get($this['maildomain']);
        if ($domainConfig) {
            $config->merge($domainConfig);
        }
        $this->protocol = self::OUTGOING_MAIL_PROTOCOL;
        
        // Set some generic defaults:
        $this->authentication = $config->get('outgoing_mail_authentication', 'password-encrypted');

        $this->logger->debug("setting up outgoing emailservice (smtp)");

        if (isset($config['outgoing_mail_socketType']) && isset($config['outgoing_mail_port'])) {
            $this->port  = (int)$config['outgoing_mail_port'];
            $this->socketType = $config['outgoing_mail_socketType'];
        } elseif (!isset($config['outgoing_mail_socketType']) && !isset($config['outgoing_mail_port'])) {
            $this->logger->debug("no socketType and port defined for SMTP, using DNS to guess settings");
            $dns = $this->dns_get_record('submission', DNS_SRV, false);
            if (!$dns) {
                //second lookup for SMTP over SSL:
                $dns =  $this->dns_get_record('smtps', DNS_SRV, false);
            }
            if ($dns) {
                //we need this later for outgoing server address:
                $this->_dns = $dns;
                $this->port = $dns->port;
                $socketType = $this->guessSocketType($this->port);
                if (!$socketType) {
                    throw new \Maildiscover\Exception("Failed to guess socketType for port {$this['port']}, set it manually please.", 500);
                }
                $this->socketType = $socketType;
                $this->logger->debug("guessed socketType `{$socketType}` from DNS defined port {$this->port}");
            } else {
                $this->socketType = self::SOCKET_TYPE_STARTTLS;
                $this->port =       $this->_ports[self::SOCKET_TYPE_STARTTLS];
                $this->logger->debug("no DNS found, using default socketType and port ({$this->outgoing_mail_socketType}:{$this->outgoing_mail_port})");
            }
            
        } elseif (isset($config['outgoing_mail_socketType'])) {
            $this->socketType = $config['outgoing_mail_socketType'];
            if (!isset($this->_ports[$this->outgoing_mail_socketType])) {
                $validSocketTypes = implode('|', array_keys($this->_ports));
                throw new \Maildiscover\Exception("Invalid socketType `{$this->outgoing_mail_socketType}` for outgoing mailserver (pick one of `{$validSocketTypes}`).", 500);
            }
            $this->port = $this->_ports[$this->outgoing_mail_socketType];
            $this->logger->debug("guessed port `{$this->outgoing_mail_port}` for socketType `{$this->outgoing_mail_socketType}`");
        } elseif (isset($config['outgoing_mail_port'])) {
            $this->port = (int)$config->outgoing_mail_port;
            $socketType = $this->guessSocketType($this->port);
            if (!$socketType) {
                throw new \Maildiscover\Exception("Failed to guess socketType for port {$this['outgoing_mail_port']}, set it manually please.", 500);
            }
            $this->socketType = $socketType;
        }
        $this->setOutgoingMailserver($config);
    }

    public function SSL($asOnOff = false)
    {
        switch ($this->socketType) {
            case self::SOCKET_TYPE_SSL:
            case self::SOCKET_TYPE_TLS:
                return true == $asOnOff ? 'on': true;
            default:
                return  true == $asOnOff ? 'off': false;
        }
    }

    protected function setOutgoingMailserver(\Maildiscover\Config $config)
    {
        $outgoing_mailserver = $this['maildomain'];
        $this->logger->debug("setting `outgoing_mailserver` to `{$outgoing_mailserver}` from emailaddress");

        if (!$config->get('outgoing_mail_server') && (!$config->get($this->maildomain) || !$config->get($this->maildomain)->get('outgoing_mail_server')) ) {
            if (false == $this->allow_dns_lookup()) {
                $this->logger->notice('no outgoing_mailserver and allow_dns_lookup=false, sticking to '.$outgoing_mailserver);
            } else {
                if (!$this->_dns) {
                    $dns = $this->dns_get_record('submission', DNS_SRV, false);
                    if (!$dns) {
                        //second lookup for SMTP over SSL:
                        $dns =  $this->dns_get_record('smtps', DNS_SRV, false);
                    }
                } else {
                    $dns = $this->_dns;
                }

                if ($dns) {
                    $outgoing_mailserver = $dns['target'];
                } else {
                    $mx = $this->getmxrr($outgoing_mailserver);
                    if ($mx) {
                       $outgoing_mailserver = $mx;
                    }
                }
            }
        }


        if ($config->get('outgoing_mail_server')) {
            $outgoing_mailserver = $config->get('outgoing_mail_server');
            $this->logger->debug("changing `outgoing_mailserver` to `{$outgoing_mailserver}` from general outgoing_mail_server config");
        }

        if (isset($config[$this->maildomain]) && $config[$this->maildomain]->get('outgoing_mail_server')) {
            $outgoing_mailserver =$config[$this->maildomain]['outgoing_mail_server'];
            $this->logger->debug("changing `outgoing_mailserver` to `{$outgoing_mailserver}` from specific domain config [{$this->maildomain}]");
        }

        $this->hostname = $outgoing_mailserver;
    }

    protected function __setHostname($hostname)
    {
        if (!filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new \Maildiscover\Exception('invalid hostname `'.$hostname.'`');
        }
        $this->offsetSet('hostname', $hostname);
    }

    protected function guessSocketType($port)
    {
        foreach($this->_ports as $socketType => $_port) {
            if ($_port == (int)$port) {
                return $socketType;
            }
        }
    }

}