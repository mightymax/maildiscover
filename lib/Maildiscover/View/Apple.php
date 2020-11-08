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

namespace Maildiscover\View ;

class Apple extends Base
{
    protected $content_type = 'application/x-apple-aspen-config; charset=utf-8';

    public function render()
    {
        $service = $this->model->getService(\Maildiscover\Model::SERVICE_EMAIL);
        if (!$service || !$service->get('incoming') || !$service->get('outgoing')) {
            throw new \Maildiscover\Exception('This view needs incoming and outgoing services');
        }
        $config = $this->getConfig('apple', $service->incoming->maildomain);

        if (isset($this->model->getRoute()->query['cn']) && $this->model->getRoute()->query['cn']) {
            $EmailAccountName = $this->model->getRoute()->query['cn'];
        } else {
            $EmailAccountName = $config->get('EmailAccountName', $service->incoming->emailaddress);
        }
        
        $MailServerUsername = $config->get('IncomingMailServerUsername', $service->incoming->emailaddress);
        $MailServerUsername = str_replace(
            ['{{hostname}}', '{{maildomain}}', '{{username}}'], 
            [$service->incoming->hostname, $service->incoming->maildomain, $service->incoming->username], 
            $MailServerUsername
        );

        $PayloadIdentifier = implode('.', array_reverse(explode('.', $service->incoming->username . '.autodiscover.' . $service->incoming->maildomain)));
        $PayloadIdentifier = $config->get('PayloadIdentifier', $PayloadIdentifier);
        $PayLoadOrganization = $config->get('PayloadOrganization', $service->incoming->maildomain);
        
        $doc = new \Maildiscover\DOMDocument();
        $implementation = new \DOMImplementation();
        $doc->appendChild($implementation->createDocumentType('plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd"'));
        $dict = $doc->appendFromPath("plist@version=1.0/dict");
        $PayloadContent = $doc->addDictEntry($dict, 'PayloadContent');
        $doc->addDictEntry($dict, 'PayloadDescription', $config->get('PayloadDescription', 'Server settings for '.$service->incoming->maildomain));
        $doc->addDictEntry($dict, 'PayloadDisplayName',$config->get('PayloadDisplayName', $EmailAccountName));
        $doc->addDictEntry($dict, 'PayloadIdentifier', $config->get('PayloadIdentifier', $PayloadIdentifier));
        $doc->addDictEntry($dict, 'PayLoadOrganization', $PayLoadOrganization);
        $doc->addDictEntry($dict, 'PayloadType', 'Configuration');
        $doc->addDictEntry($dict, 'PayloadUUID', $config->get('PayloadUUID', $this->uuid($service->incoming->emailaddress)));
        $doc->addDictEntry($dict, 'PayloadVersion', 1);
        
        //Mail:
        $dict = $doc->appendFromPath("dict", $PayloadContent);
        $doc->addDictEntry($dict, 'EmailAccountDescription', $config->get('EmailAccountDescription', 'Email settings for '.$service->incoming->maildomain));
        $doc->addDictEntry($dict, 'EmailAccountName',$config->get('EmailAccountName', $EmailAccountName));
        $doc->addDictEntry($dict, 'EmailAccountType', 'EmailType' . str_replace('3', '', strtoupper($service->incoming->protocol)));
        $doc->addDictEntry($dict, 'EmailAddress', $service->incoming->emailaddress);
        
        $doc->addDictEntry($dict, 'IncomingMailServerAuthentication', $config->get('IncomingMailServerAuthentication', 'EmailAuthPassword'));
        $doc->addDictEntry($dict, 'IncomingMailServerHostName', $service->incoming->hostname);
        $doc->addDictEntry($dict, 'IncomingMailServerPortNumber', $service->incoming->port);
        $doc->addDictEntry($dict, 'IncomingMailServerUseSSL', $service->incoming->SSL());
        $doc->addDictEntry($dict, 'IncomingMailServerUsername', $MailServerUsername);
        if ($this->model->getRoute()->password) {
            $doc->addDictEntry($dict, 'IncomingPassword', $this->model->getRoute()->password);
        }
        $MailServerUsername = $config->get('OutgoingMailServerUsername', $service->outgoing->emailaddress);
        $MailServerUsername = str_replace(
            ['{{hostname}}', '{{maildomain}}', '{{username}}'], 
            [$service->incoming->hostname, $service->incoming->maildomain, $service->outgoing->username], 
            $MailServerUsername
        );
        
        $doc->addDictEntry($dict, 'OutgoingMailServerAuthentication', $config->get('IncomingMailServerAuthentication', 'EmailAuthPassword'));
        $doc->addDictEntry($dict, 'OutgoingMailServerHostName', $service->outgoing->hostname);
        $doc->addDictEntry($dict, 'OutgoingMailServerPortNumber', $service->outgoing->port);
        $doc->addDictEntry($dict, 'OutgoingMailServerUseSSL', $service->outgoing->SSL());
        $doc->addDictEntry($dict, 'OutgoingMailServerUsername', $MailServerUsername);
        $doc->addDictEntry($dict, 'OutgoingPasswordSameAsIncomingPassword', true);
        
        $PayloadIdentifier = implode('.', array_reverse(explode('.', $service->incoming->username . '.email.' . $service->incoming->maildomain)));
        $doc->addDictEntry($dict, 'PayloadDescription', $config->get('PayloadDescription', 'Email settings for '.$service->incoming->maildomain));
        $doc->addDictEntry($dict, 'PayloadDisplayName',$config->get('PayloadDisplayName', "Mail Account ({$service->incoming->emailaddress})"));
        $doc->addDictEntry($dict, 'PayloadIdentifier', $config->get('PayloadIdentifier', $PayloadIdentifier));
        $doc->addDictEntry($dict, 'PayLoadOrganization', $PayLoadOrganization);
        $doc->addDictEntry($dict, 'PayloadType', 'com.apple.mail.managed');
        $doc->addDictEntry($dict, 'PayloadUUID', $config->get('PayloadUUID', $this->uuid($PayloadIdentifier)));
        $doc->addDictEntry($dict, 'PayloadVersion', 1);
        
        // CalDAV and CardDAV
        foreach([\Maildiscover\Model::SERVICE_CALDAV, \Maildiscover\Model::SERVICE_CARDDAV] as $dav) {
            if ($service = $this->model->getService($dav)) {
                $davID = preg_replace('/^c(al|ard)dav$/', 'C$1DAV', $dav);

                $Username = $config->get($davID.'Username', $service->emailaddress);
                $Username = str_replace(
                    ['{{hostname}}', '{{maildomain}}', '{{username}}'], 
                    [$service->hostname, $service->maildomain, $service->username], 
                    $Username
                );
                $PayloadIdentifier = implode('.', array_reverse(explode('.', $service->username . '.'.$dav.'.' . $service->maildomain)));
                $dict = $doc->appendFromPath("dict", $PayloadContent);
                $doc->addDictEntry($dict, $davID. 'AccountDescription', $config->get($davID . 'AccountDescription', $davID . ' settings for '.$service->maildomain));
                $doc->addDictEntry($dict, $davID. 'HostName', $service->host);
                $doc->addDictEntry($dict, $davID . 'Username', $Username);
                $doc->addDictEntry($dict, $davID . 'PrincipalURL', $service->path);
                if ($this->model->getRoute()->password) {
                    $doc->addDictEntry($dict, $davID . 'Password', $this->model->getRoute()->password);
                }
                $doc->addDictEntry($dict, $davID . 'UseSSL', $service->SSL());
                $doc->addDictEntry($dict, $davID . 'Port', $service->port);
                $doc->addDictEntry($dict, 'PayloadDescription', $config->get('PayloadDescription', $davID . ' settings for '.$service->host));
                $doc->addDictEntry($dict, 'PayloadDisplayName',$config->get('PayloadDisplayName', "{$davID} Account ({$service->emailaddress})"));
                $doc->addDictEntry($dict, 'PayloadIdentifier', $config->get('PayloadIdentifier', $PayloadIdentifier));
                $doc->addDictEntry($dict, 'PayLoadOrganization', $PayLoadOrganization);
                $doc->addDictEntry($dict, 'PayloadType', 'com.apple.'.$dav.'.account');
                $doc->addDictEntry($dict, 'PayloadUUID', $config->get('PayloadUUID', $this->uuid($PayloadIdentifier)));
                $doc->addDictEntry($dict, 'PayloadVersion', 1);
            }
        }
        
        
        return $doc;
    }

    protected function uuid($salt)
    {
        $hash = md5($salt);
        preg_match('/^([0-9a-f]{8})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{12})$/', md5($salt), $match);
        array_shift($match);
        return implode('-', $match);

    }

    public function sendHeaders() 
    {
        
        $content_disposition = 'attachment';

        if(null !== $this->model->getRoute()->view) {
            $this->content_type = 'text/xml; charset=UTF-8';
            $content_disposition = 'inline';
            $this->logger->notice("`view` parameter detected:");
            $this->logger->notice("  [*] setting content-type to `{$this->content_type}`");
            $this->logger->notice("  [*] setting content-disposition to `inline`");
        }

        $hostname = '';
        foreach ($this->model->getServices() as $service) {
            if ($service->get('maildomain')) {
                $hostname = $service->get('maildomain');
                break;
            }
        }
        $filename = 'mailconfig.mobileconfig';
        if ($hostname) {
            $filename = str_replace('.','_', $hostname) . '.mobileconfig';
            $config = $this->getConfig('apple', $hostname);
            $filename = $config->get('filename', $filename);
        }

        $this->logger->debug("using filename `{$filename}` for Apple profile");

        $header = "content-disposition: {$content_disposition}; filename=\"{$filename}\"";
        $this->logger->debug("sending header `{$header}`");
        header($header);
        return parent::sendHeaders();

    }
}