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

class Maildiscover
{
    public function __construct()
    {

    }

    public function srv()
    {
        $logger = Logger::getInstance();
        try {

            $route = Route::getInstance();
            $model = new Model();

            if ($model->hasService(Model::SERVICE_EMAIL)) {
                $logger->debug("adding Mail (incoming/outgoing) service to model");
                $model->addMail();
            }

            if ($model->hasService(Model::SERVICE_CALDAV)) {
                $logger->debug("adding CalDAV service to model");
                $model->addCalDAV();
            }
             
            if ($model->hasService(Model::SERVICE_CARDDAV)) {
                $logger->debug("adding CardDAV service to model");
                $model->addCardDAV();;
            }

            $view = View::Factory($route, $model);

            $body = (string)$view;
            $view->sendHeaders();
            echo $body;
        } catch (Exception $e) {
            $e->toHTML();
            return;
        } catch (\Exception $e) {
            echo "FATAL error in {$e->getFile()} on line {$e->getLine()}:\n  {$e->getMessage()}";
            print_r($e->getTrace());
            exit;
        }
    }
}
