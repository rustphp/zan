<?php
namespace Zan\Framework\Extensions\Log\Formatter;
/**
 * Interface ILogFormatter
 * @package Zan\Framework\Foundation\Log\Formatter
 */
interface ILogFormatter {
    public function format($record);
}