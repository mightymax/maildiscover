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

namespace Maildiscover ;

class View
{
    /**
     * @return Maildiscover\View\Base
     */
    public static function Factory(Route $route, Model $model)
    {
        $logger = Logger::getInstance();

        if ($route->isStatic()) {
            $logger->debug("using the HTML static renderer");
            return new View\HTML($model);
        } elseif ($route->isMicrosoft()) {
            $logger->debug("using the MICROSOFT renderer");
            return new View\Microsoft($model);
        } elseif ($route->isMozilla()) {
            $logger->debug("using the MOZILLA renderer");
            return new View\Mozilla($model);
        } elseif ($route->isApple()) {
            $logger->debug("using the APPLE renderer");
            return new View\Apple($model);
        }
        throw new Exception("Unkown Service for route `{$route->path}`", 500);

    }

}