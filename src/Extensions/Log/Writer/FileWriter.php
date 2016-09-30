<?php
/**
 * Created by IntelliJ IDEA.
 * User: nuomi
 * Date: 16/5/26
 * Time: 上午11:46
 */
namespace Zan\Framework\Extensions\Log\Writer;
use Zan\Framework\Foundation\Contract\Async;
use Zan\Framework\Foundation\Exception\System\InvalidArgumentException;

/**
 * Class FileWriter
 * @package Zan\Framework\Foundation\Log\Writer
 */
class FileWriter implements ILogWriter, Async {
    private $callback;
    private $path;
    private $dir;
    private $async;

    public function __construct($path, $async = TRUE) {
        if (!$path) {
            throw new InvalidArgumentException('Path not be null');
        }
        $this->path = $path;
        $this->dir = dirname($this->path);
        $this->async = $async;
    }

    public function execute(callable $callback) {
        $this->callback = $callback;
    }

    public function write($log) {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, TRUE);
            chmod($this->dir, 0755);
        }
        $callback = $this->async ? [$this, 'ioReady'] : NULL;
        swoole_async_write($this->path, $log, -1, $callback);
        if (NULL === $callback) {
            $this->ioReady();
        }
    }

    public function ioReady() {
        if (!$this->callback) {
            return;
        }
        call_user_func($this->callback, TRUE);
    }
}
