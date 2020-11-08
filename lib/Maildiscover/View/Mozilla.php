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

class Mozilla extends Base
{
    public function render()
    {
        $service = $this->model->getService(\Maildiscover\Model::SERVICE_EMAIL);
        if (!$service || !$service->get('incoming') || !$service->get('outgoing')) {
            throw new \Maildiscover\Exception('This view needs incoming and outgoing services');
        }
        $config = $this->getConfig('mozilla', $service->incoming->maildomain);

        $doc = new \Maildiscover\DOMDocument();
        $emailProvider = $doc->appendFromPath("clientConfig@version=1.1/emailProvider@id={$service->incoming->hostname}");

        $doc->appendChildren([
            "domain[{$service->incoming->hostname}]",
            'displayName[' . $config->get('displayName', $service->incoming->hostname) . ']',
            'displayShortName[' . $config->get('displayShortName', $service->incoming->hostname) . ']'
        ], $emailProvider);

        $outgoingServer = $doc->appendFromPath("outgoingServer@type={$service->outgoing->protocol}", $emailProvider);
        $children = [
            "hostname[{$service->outgoing->hostname}]",
            "port[{$service->outgoing->port}]",
            "socketType[{$service->outgoing->socketType}]",
            "authentication[{$service->outgoing->authentication}]",
            "username[{$service->outgoing->emailaddress}]",
            "useGlobalPreferredServer[yes]"
        ];
        $doc->appendChildren($children, $outgoingServer);

        $incomingServer = $doc->appendFromPath('incomingServer@type=' . $service->incoming->protocol, $emailProvider);
        $doc->appendChildren([
            "hostname[{$service->incoming->hostname}]",
            "port[{$service->incoming->port}]",
            "socketType[{$service->incoming->socketType}]",
            "authentication[{$service->incoming->authentication}]",
            "username[{$service->incoming->emailaddress}]",
        ], $incomingServer);

        if ($documentation = $config->get('documentation')) {
            if (\is_string($documentation)) {
                $documentation = new \Maildiscover\Config([$documentation ]);
            }

            foreach ($documentation as $doc_config) {
                $doc_config = explode('|', $doc_config);
                $url = array_shift($doc_config);
                $node = $doc->appendFromPath('documentation', $emailProvider);
                $node->setAttribute('url', $url);
                foreach ($doc_config as $desc) {
                    $a = explode('@', $desc, 2);
                    $descr = array_shift($a);
                    $lang = count($a) ? array_shift($a) : 'en';
                    $node->appendChild($doc->createElement('descr', $descr))->setAttribute('lang', $lang);
                }
            }
        }
        return $doc;

    }
}