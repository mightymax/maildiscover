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

class Config implements \ArrayAccess, \Countable, \Iterator
{
    protected static $instance;

    protected $config = [];

    /* for Iterator: */
    private $keys = [];
    private $position = 0;

    public function __construct(Array $config = [])
    {
        $this->setConfig($config);
    }

    /**
     * @return \Maildiscover\Config
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function load($ini_file, $clear = false)
    {
        if (!file_exists($ini_file)) {
            throw new Exception('INI file `{$ini_file}` does not exist.', 500);
        }

        if (!is_file($ini_file)) {
            throw new Exception('INI file `{$ini_file}` is not a file.', 500);
        }

        if (!is_readable($ini_file)) {
            throw new Exception('INI file `{$ini_file}` is not readable.', 500);
        }

        $config = @parse_ini_file($ini_file, true);
        if (!is_array($config)) {
            throw new Exception('Failed to parse INI file `{$ini_file}`.', 500);
        }

        $this->setConfig($config, $clear);
        return $this;
    }

    public function setConfig(Array $config, $clear = false)
    {
        if (true === $clear) {
            $this->config = [];
        }
        foreach ($config as $key => $var) {
            if (\is_array($var)) {
                $extends = preg_split('/\s*:\s*/', $key, 2);
                if (count($extends) == 2) {
                    list($key, $extending) = $extends;
                    if ($this->get($extending)) {
                        $var = array_merge($this->get($extending)->toArray(), $var);
                    } else {
                        throw new Exception("Can not extend `{$key}` on non-existing section `{$extending}`.", 500);
                    }
                }
                $this->config[$key] = new self($var);
            } else {
                if (is_numeric($var)) {
                    $var = $var + 0;
                }
                $this->config[$key] = $var;
            }
        }
        $this->keys = array_keys($this->config);
        $this->position = 0;
        return $this;
    }

    public function __get($key)
    {
        return $this->offsetGet($key);
    }

    public function __set($key, $val)
    {
        $setterFunction = '__set' . \ucfirst($key);
        if (\is_callable([$this, $setterFunction])) {
            return $this->$setterFunction($val);
        } else {
            return $this->offsetSet($key, $val);
        }
    }

    public function get($key, $default = null)
    {
        if (\method_exists($this, "__get__{$key}")) {
            return $this->{"__get__{$key}"}();
        }

        return $this->offsetExists($key) ? $this->offsetGet($key) : $default;
    }

    public function getOnOff($key, $default = null)
    {
        if (!isset($this[$key])) {
          return $default;
        }
        
        return true === (bool)$this->get($key) ? 'on' : 'off';
    }

    public function toArray($recursive = false)
    {
        return $this->config;
    }

    function toArrayRecursive($config = [])
    {
        if (!$config) $config = $this->toArray();
        foreach ($config as $key => $var) {
            if ($var instanceof \Maildiscover\Config) {
                $config[$key] = $this->toArrayRecursive($var->toArray());
            }
        }
        return $config;
    }

    public function merge(self $config)
    {
        return $this->setConfig(array_merge($this->toArray(), $config->toArray()), true);
    }

    public function keys() {
        return $this->keys;
    }


    /* ArrayAccess */

    public function offsetSet($key, $val) {
        if (!isset($this->keys[$key])) {
            $this->keys[] = $key;
        }
        $this->config[$key] = $val;
    }

    public function offsetExists($key) {
        return isset($this->config[$key]);
    }

    public function offsetUnset($key) {
        unset($this->config[$key]);
    }

    public function offsetGet($key) 
    {
        if(isset($this->config[$key])) return $this->config[$key];
    }

    /* Countable */
    public function count()
    {
        return count($this->config);
    }

    /* Iterator */
    public function rewind() {
        $this->position = 0;
    }

    public function current() {
        return $this->config[$this->key($this->position)];
    }

    public function key() {
        return $this->keys[$this->position];
    }

    public function next() {
        ++$this->position;
    }

    public function valid() {
        return isset($this->keys[$this->position]);
    }

}