<?php
namespace Zan\Framework\Extensions\Log\Writer;
/**
 * Interface ILogWriter
 * @package Zan\Framework\Foundation\Log\Writer
 */
interface ILogWriter {
    public function write($log);
}