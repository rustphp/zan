<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Zan\Framework\Network\Http\Bag;
/**
 * FileBag is a container for key/value pairs.
 */
class FileBag implements \IteratorAggregate, \Countable {
    /**
     * File storage.
     *
     * @var array
     */
    protected $files;

    /**
     * Constructor.
     *
     * @param array $files An array of files
     */
    public function __construct(array $files = array()) {
        $this->files = $files;
    }

    /**
     * Returns the files.
     *
     * @return array An array of files
     */
    public function all() {
        return $this->files;
    }

    /**
     * Returns the parameter keys.
     *
     * @return array An array of parameter keys
     */
    public function keys() {
        return array_keys($this->files);
    }

    /**
     * Returns a parameter by name.
     *
     * @param string $key The key
     * @param mixed $default The default value if the parameter key does not exist
     *
     * @return mixed
     */
    public function get($key, $default = null) {
        return array_key_exists($key, $this->files) ? $this->files[$key] : $default;
    }

    /**
     * Sets a parameter by name.
     *
     * @param string $key The key
     * @param mixed $value The value
     */
    public function set($key, $value) {
        $this->files[$key] = $value;
    }

    /**
     * Returns true if the parameter is defined.
     *
     * @param string $key The key
     *
     * @return bool true if the parameter exists, false otherwise
     */
    public function has($key) {
        return array_key_exists($key, $this->files);
    }

    /**
     * Removes a parameter.
     *
     * @param string $key The key
     */
    public function remove($key) {
        unset($this->files[$key]);
    }

    /**
     * Returns an iterator for files.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator() {
        return new \ArrayIterator($this->files);
    }

    /**
     * Returns the number of files.
     *
     * @return int The number of files
     */
    public function count() {
        return count($this->files);
    }
}
