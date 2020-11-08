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

abstract class Base
{
    protected $models = [];

    protected $content_type = 'text/xml; charset=UTF-8';

    public abstract function render();

    /**
     * @var \Maildiscover\Model $model
     */
    protected $model;

   /**
     * @var \Maildiscover\Config $$config
     */
    protected $config;

    /**
     * @var \Maildiscover\Logger
     */
    protected $logger;


    public function __construct(\Maildiscover\Model $model)
    {
        $this->model = $model;
        $this->logger = \Maildiscover\Logger::getInstance();
    }

    public function sendHeaders() 
    {
        header('Content-Type: ' . $this->content_type);
        $this->logger->debug("sending header `Content-Type: $this->content_type}`");
        return $this;
    }

    /**
     * Gets Config instance and extends it with per-host settings based on prefixes
     * 
     * @return \Maildiscover\Config
     */
    public function getConfig($section, $maildomain)
    {
        $config = \Maildiscover\Config::getInstance()->get($section, new \Maildiscover\Config());
        $hostConfig = \Maildiscover\Config::getInstance()->get($maildomain, new \Maildiscover\Config());
        foreach ($hostConfig as $key => $val) {
            if (0 === strpos($key, $section . '_')) {
                $key = str_replace($section . '_', '', $key);
                $config->$key = $val;
            }
        }
        return $config;
    }

    public function __toString()
    {
        $result = $this->render();
        if ($result instanceof \DOMDocument) {
            return $result->saveXml();
        }
        return $this->render();
    }
}