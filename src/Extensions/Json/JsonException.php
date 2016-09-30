<?php
namespace Zan\Framework\Extensions\Json;
use Zan\Framework\Foundation\Exception\ZanException;

/**
 * Class JsonException
 * @package Zan\Framework\Utilities\Json
 */
class JsonException extends ZanException {
    public function __construct($code, $msg = NULL) {
        parent::__construct($msg, $code);
    }
}