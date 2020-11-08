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

class Logger 
{
    protected static $instance;

    const NONE = 0;
    const ERROR = 1;
    const WARNING  = 2;
    const NOTICE = 4;
    const DEBUG = 8;
    const ALL = 1 + 2 + 4 + 8;

    
    protected static $level = self::NONE;
    protected $logfile;

    protected $start_time;

    private function __construct() 
    {
        $config = Config::getInstance();
        if ((int)$config->get('log_level')) {
            self::$level = (int)$config->get('log_level');
        }
        if (self::$level == self::NONE) return;

        $log_file = $config->get('log_file', 'BASE_PATH/logs/maildiscover.log');
        $log_file = str_replace('BASE_PATH', (defined('BASE_PATH')? BASE_PATH :__DIR__.'/../logs'), $log_file);
        if (preg_match_all('/%[a-zA-Z]/', $log_file, $matches)) {
            foreach ($matches[0] as $match) {
                $log_file = str_replace($match, date(substr($match, 1)), $log_file);
            }
        }

        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            if (!mkdir($log_dir, 0777, true)) {
                throw new \Exception("failed to create `{$log_dir}`.");
            }
        }

        if (!\is_writable($log_dir)) {
            throw new \Exception("Logdir `{$log_dir}` not writable.");
        }

        if (file_exists($log_file) && !\is_writable($log_file)) {
            throw new \Exception("Logfile `{$log_file}` not writable.");
        }
        $this->logfile = fopen($log_file, 'a');
        if (!$this->logfile) {
            throw new \Exception("Failed to open logfile `{$log_file}` for writing (w+).");
        }

    }

    public function __destruct()
    {
        if ($this->logfile) {
            fclose($this->logfile);
        }
    }

    /**
     * @return self
     */
    public static function setLevel($level)
    {
        self::$level = $level;
        return self::getInstance();
    }

    public static function hasLevel($level)
    {
        return self::$level & $level;
    }

    public function error($msg)
    {
        if (self::$level & self::ERROR) {
            fwrite($this->logfile, date("c"). "\tERROR\t{$msg}\n");
        }
    }

    public function warning($msg)
    {
        if (self::$level & self::WARNING) {
            fwrite($this->logfile, date("c"). "\tWARNING\t{$msg}\n");
        }
    }

    public function notice($msg)
    {
        if (self::$level & self::NOTICE) {
            fwrite($this->logfile, date("c"). "\tNOTICE\t{$msg}\n");
        }
    }

    public function debug($msg)
    {
        if (self::$level & self::NOTICE) {
            fwrite($this->logfile, date("c"). "\tDEBUG\t{$msg}\n");
        }
    }

    public function start()
    {
        $this->start_time = defined('LOGGER_START')? LOGGER_START : microtime(true);
        $this->notice('*** ' . get_class($this)." started ***");
    }

    public function stop()
    {
        $elapsed_time = round(1000 * (microtime(true) - $this->start_time), 2);
        $this->notice('*** ' . get_class($this)." finished in {$elapsed_time} ms. ***");
    }

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

}
