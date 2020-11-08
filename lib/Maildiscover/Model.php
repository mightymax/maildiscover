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

class Model
{
    const SERVICE_EMAIL   = 'email';
    const SERVICE_EMAIL_INCOMING   = 'incoming';
    const SERVICE_EMAIL_OUTGOING   = 'outgoing';
    const SERVICE_CALDAV  = 'caldav';
    const SERVICE_CARDDAV = 'carddav';



    protected $serviceIds = [
        self::SERVICE_EMAIL,
        self::SERVICE_CALDAV,
        self::SERVICE_CARDDAV
    ];

    /**
     * @var \Maildiscover\Route $route
     */
    protected $route;

    protected $requested_services = [];

    protected $services = [];

    public function __construct()
    {
        $this->services = new Config();
        $this->route = Route::getInstance();
    }

    public function hasService($serviceId)
    {
        if ($this->route->isStatic()) {
            return false;
        }
        
        $logger = Logger::getInstance();
        if (!$this->requested_services) {
            if (isset(Config::getInstance()['services'])) {
                $services =Config::getInstance()->get('services');
                $logger->debug("found `services` setting in config");
                $matches = [];
                \preg_match_all('/('.implode('|', $this->serviceIds).')/', $services, $matches);
                if (!count($matches[0])) {
                    throw new Exception("Configuration error: no valid services found in `{$services}`", 500);
                }
                $services = $matches[0];
            } else {
                $logger->debug("no `services` setting in config, using all available service");
                $services = $this->serviceIds;
            }
            $logger->debug("services requested: " . implode('|', $services));
            $this->requested_services = $services;
        }
        return in_array($serviceId, $this->requested_services);
    }

    /**
     * @return Model\Base
     */
    public function getService($serviceId)
    {
        return isset($this->services[$serviceId]) ? $this->services[$serviceId] : null;
    }

    public function getServices()
    {
        return $this->services;
    }

    /**
     * @return Route
     */
    public function getRoute()
    {
        return $this->route;
    }

    public function addMail()
    {
        $incoming = new Model\Incoming();
        $outgoing = new Model\Outgoing();

        $this->services[self::SERVICE_EMAIL] = new Config([
            self::SERVICE_EMAIL_INCOMING => $incoming,
            self::SERVICE_EMAIL_OUTGOING => $outgoing,
        ]);

        return $this;
    }

    public function addCalDAV() 
    {
        $dav = new Model\DAV(self::SERVICE_CALDAV);
        $this->services[self::SERVICE_CALDAV] = $dav;
    }

    public function addCardDAV() 
    {
        $dav = new Model\DAV(self::SERVICE_CARDDAV);
        $this->services[self::SERVICE_CARDDAV] = $dav;
    }
    

}
