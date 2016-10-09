<?php
namespace Zan\Framework\Foundation\Exception;
use Zan\Framework\Extensions\Log\FileLogger;
use Zan\Framework\Foundation\Core\Debug;

class Handler {
    public static function initErrorHandler() {
        error_reporting(E_ALL);
        ini_set('display_errors', FALSE);
        if (Debug::get()) {
            set_exception_handler(['Handler', 'handleException']);
        } else {
            set_exception_handler(['Handler', 'handleExceptionProduct']);
        }
        set_error_handler(['Handler', 'handleError']);
        register_shutdown_function(['Handler', 'handleFatalError']);
    }

    public static function handleException(\Exception $e) {
        $logger = new FileLogger('error');
        yield $logger->error($e);
        return TRUE;
    }

    public static function handleExceptionProduct(\Exception $e) {
        Handler::handleException($e);
    }

    public static function handleError($code, $message, $file, $line) {
        $exception = new \Exception($message, 9999, $code, $file, $line);
        return Handler::handleException($exception);
    }

    public static function handleFatalError() {
        $error = error_get_last();
        if ($error) { //&& self::isLevelFatal($error['type'])
            Handler::handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    protected function isLevelFatal($level) {
        $errors = E_ERROR;
        $errors |= E_PARSE;
        $errors |= E_CORE_ERROR;
        $errors |= E_CORE_WARNING;
        $errors |= E_COMPILE_ERROR;
        $errors |= E_COMPILE_WARNING;
        return ($level & $errors) > 0;
    }
}