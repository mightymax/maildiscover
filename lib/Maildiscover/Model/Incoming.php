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

class Incoming extends Base
{

    const MAIL_TYPE_POP3 = 'pop3';
    const MAIL_TYPE_IMAP = 'imap';

    const SOCKET_TYPE_STLS = 'STLS';
    const SOCKET_TYPE_SSL = 'SSL';
    const SOCKET_TYPE_TLS = 'TLS'; //same protocol as SSL
    const SOCKET_TYPE_STARTTLS = 'STARTTLS';
    const SOCKET_TYPE_PLAIN = 'PLAIN';

    protected $_ports = [
        self::MAIL_TYPE_IMAP => [
            self::SOCKET_TYPE_SSL => 993,
            self::SOCKET_TYPE_TLS => 993,
            self::SOCKET_TYPE_STARTTLS => 143,
            self::SOCKET_TYPE_PLAIN => 143
        ],
        self::MAIL_TYPE_POP3 => [
            self::SOCKET_TYPE_STLS => 110,
            self::SOCKET_TYPE_PLAIN => 110,
            self::SOCKET_TYPE_SSL => 995,
            self::SOCKET_TYPE_TLS => 995,
        ]
    ];

   protected function init()
    {
        $config = self::getInstance();
        $domainConfig = $config->get($this['maildomain']);
        if ($domainConfig) {
            $config->merge($domainConfig);
        }

        // Set some generic defaults:
        $this->authentication = $config->get('incoming_mail_authentication', 'password-encrypted');

        
        $this->setIncomingMailserver($config);

        $this->protocol = $this->get('incoming_mail_type', self::MAIL_TYPE_IMAP);
        $this->logger->debug("setting up incoming emailservice for type `{$this->protocol}`");

        if (isset($config['incoming_mail_socketType']) && isset($config['incoming_mail_port'])) {
            $this['incoming_mail_socketType'] = $config['incoming_mail_socketType'];
            $this->port = $config['incoming_mail_port'];
        } elseif (!isset($config['incoming_mail_socketType']) && !isset($config['incoming_mail_port'])) {
            $this->logger->debug("no socketType and port defined, using DNS to guess settings");
            $dns = $this->dns_get_record($this->protocol);
            if ($dns) {
                $this->port = $dns->port;
                $socketType = $this->guessSocketType($this->port);
                if (!$socketType) {
                    throw new \Maildiscover\Exception("Failed to guess socketType for port {$this['port']}, set it manually please.", 500);
                }
                $this->socketType = $socketType;
                $this->logger->debug("guessed socketType `{$socketType}` from DNS defined port {$this->port}");
            } else {
                $this->socketType = $this->protocol == self::MAIL_TYPE_POP3 ? self::SOCKET_TYPE_STLS : self::SOCKET_TYPE_STARTTLS;
                $this->port =       $this->_ports[$this->protocol][$this->socketType];
                $this->logger->debug("no DNS found, using default socketType and port ({$this->socketType}:{$this->port})");
            }
            
        } elseif (isset($config['incoming_mail_socketType'])) {
            $this->socketType = $config['incoming_mail_socketType'];
            if (!isset($this->_ports[$this->protocol][$this->socketType])) {
                $validSocketTypes = implode('|', array_keys($this->_ports[$this->protocol]));
                throw new \Maildiscover\Exception("Invalid socketType `{$this->socketType}` for mailtype `{$this->incoming_mail_type}` (pick one of `{$validSocketTypes}`).", 500);
            }
            $this->port = $this->_ports[$this->protocol][$this->socketType];
            $this->logger->debug("guessed port `{$this->port}` for socketType `{$this->socketType}`");
        } elseif (isset($config['incoming_mail_port'])) {
            $this->port = (int)$config['incoming_mail_port'];
            $socketType = $this->guessSocketType($config->incoming_mail_port);
            if (!$socketType) {
                throw new \Maildiscover\Exception("Failed to guess socketType for port {$this->port}, set it manually please.", 500);
            }
            $this->socketType = $socketType;
        }
    }

    public function SSL($asOnOff = false)
    {
        switch ($this->socketType) {
            case self::SOCKET_TYPE_SSL:
            case self::SOCKET_TYPE_STLS:
            case self::SOCKET_TYPE_TLS:
                return true == $asOnOff ? 'on': true;
            default:
                return  true == $asOnOff ? 'off': false;
        }
    }

    protected function setIncomingMailserver(\Maildiscover\Config $config)
    {
        $incoming_mailserver = $this['maildomain'];
        $this->logger->debug("setting `incoming_mailserver` to `{$incoming_mailserver}` from emailaddress");

        if (!$config->get('incoming_mail_server') && (!$config->get($this->maildomain) || !$config->get($this->maildomain)->get('incoming_mail_server')) ) {
            if (false == $this->allow_dns_lookup()) {
                $this->logger->notice('no incoming_mailserver and allow_dns_lookup=false, sticking to '.$incoming_mailserver);
            } else {
                $mx = $this->getmxrr($incoming_mailserver);
                if ($mx) {
                   $incoming_mailserver = $mx;
                }
            }
        }

        if ($config->get('incoming_mail_server')) {
            $incoming_mailserver = $config->get('incoming_mail_server');
            $this->logger->debug("changing `incoming_mailserver` to `{$incoming_mailserver}` from general incoming_mail_server config");
        }

        if (isset($config[$this->maildomain]) && $config[$this->maildomain]->get('incoming_mail_server')) {
            $incoming_mailserver =$config[$this->maildomain]['incoming_mail_server'];
            $this->logger->debug("changing `incoming_mailserver` to `{$incoming_mailserver}` from specific domain config [{$this->maildomain}]");
        }

        $this->hostname = $incoming_mailserver;
    }

    protected function guessSocketType($port)
    {
        foreach($this->_ports[$this->protocol] as $socketType => $_port) {
            if ($_port == (int)$port) {
                return $socketType;
            }
        }
    }

  protected function __setSocketType($val)
    {
        $this->__validateFromClassConstants('socketType', $val, 'SOCKET_TYPE');
        parent::offsetSet('socketType', $val);
    }

    protected function __setProtocol($val)
    {
        $val = \strtolower($val);
        $this->__validateFromClassConstants('type', $val, 'MAIL_TYPE');
        parent::offsetSet('protocol', $val);
    }

    protected function __setHostname($hostname)
    {
        if (!filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new \Maildiscover\Exception('invalid hostname `'.$hostname.'`');
        }
        $this->offsetSet('hostname', $hostname);
    }




}