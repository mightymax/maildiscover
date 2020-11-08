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

namespace Maildiscover\View;

class Microsoft extends Base
{
    const xmlns = 'http://schemas.microsoft.com/exchange/autodiscover/outlook/requestschema/2006';
    protected $xpath;

    public function render()
    {
        $service = $this->model->getService(\Maildiscover\Model::SERVICE_EMAIL);
        if (!$service || !$service->get('incoming') || !$service->get('outgoing')) {
            throw new \Maildiscover\Exception('This view needs incoming and outgoing services');
        }
        $config = $this->getConfig('microsoft', $service->incoming->maildomain);

        $doc = new \Maildiscover\DOMDocument();
        $Account = $doc->appendFromPath('Autodiscover/Response/Account', null, [
            'Autodiscover' => [
                'xmlns' => 'http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006'
            ],
            'Response' => [
                'xmlns' => 'http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a'
            ]
        ]);

        $doc->appendFromPath('AccountType[email]', $Account);
        $doc->appendFromPath('Action[setting]', $Account);

        // SPA: (Secure Password Authentication): map from mozilla's password-encrypted value
        $SPA = $config->getOnOff('SPA', $service->incoming->authentication == 'password-encrypted' ? 'on' : 'off');

        $Protocol = $doc->appendFromPath('Protocol', $Account);

        $protocol = strtoupper($service->incoming->protocol);
        $doc->appendChildren([
            "Type[{$protocol}]",
            "Server[{$service->incoming->hostname}]",
            "Port[{$service->incoming->port}]",
            'DomainRequired['.$config->getOnOff('DomainRequired', 'off').']',
            'AuthRequired['.$config->getOnOff('AuthRequired', 'on').']',
            "SPA[{$SPA}]",
            "SSL[{$service->incoming->SSL(true)}]",
        ], $Protocol);

        
        $protocol = strtoupper($service->outgoing->protocol);
        $Protocol = $doc->appendFromPath('Protocol', $Account);
        $doc->appendChildren([
            "Type[{$protocol}]",
            "Server[{$service->outgoing->hostname}]",
            "Port[{$service->outgoing->port}]",
            'DomainRequired['.$config->getOnOff('DomainRequired', 'off').']',
            'AuthRequired['.$config->getOnOff('AuthRequired', 'on').']',
            "SPA[{$SPA}]",
            "SSL[{$service->outgoing->SSL(true)}]",
            "SMTPLast[".$config->getOnOff('SMTPLast', 'on')."]"
        ], $Protocol);

        if ($service->incoming->protocol == \Maildiscover\Model\Incoming::MAIL_TYPE_POP3) {
            $doc->appendFromPath('UsePOPAuth['.$config->getOnOff('UsePOPAuth', 'on').']', $Protocol);
        }
        return $doc;
    }
}