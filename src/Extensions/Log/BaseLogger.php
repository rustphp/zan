<?php
namespace Zan\Framework\Extensions\Log;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Zan\Framework\Foundation\Exception\System\InvalidArgumentException;
use Zan\Framework\Foundation\Core\Config;
use Zan\Framework\Utilities\Types\Arr;
use Zan\Framework\Foundation\Application;

/**
 * Class BaseLogger
 * @package Zan\Framework\Foundation\Log
 */
abstract class BaseLogger implements LoggerInterface {
    protected        $config;
    protected static $levelMap = [
        LogLevel::DEBUG     => 'DEBUG',
        LogLevel::INFO      => 'INFO',
        LogLevel::NOTICE    => 'NOTICE',
        LogLevel::WARNING   => 'WARNING',
        LogLevel::ERROR     => 'ERROR',
        LogLevel::CRITICAL  => 'CRITICAL',
        LogLevel::ALERT     => 'ALERT',
        LogLevel::EMERGENCY => 'EMERGENCY',
    ];
    protected        $name;
    /**
     * @var \Zan\Framework\Extensions\Log\Writer\ILogWriter;
     */
    protected $writer = NULL;
    /**
     * @var \Zan\Framework\Extensions\Log\Formatter\BaseFormatter;
     */
    protected $formatter = NULL;
    protected $logLevel  = 0;
    protected $timezone  = NULL;

    /**
     * BaseLogger constructor.
     * @param string $config_key
     * @throws InvalidArgumentException
     */
    public function __construct($config_key) {
        if (!$config_key) {
            throw new InvalidArgumentException('Config is required' . $config_key);
        }
        $config = self::getConfigByKey($config_key);
        $this->config = $config;
        $this->logLevel = $config['level'];
        $this->timezone = new DateTimeZone(date_default_timezone_get() ?: 'PRC');
        $this->name = $config['app'];
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     * @return \Generator|void
     */
    public function emergency($message, array $context = []) {
        yield $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     * @return \Generator|void
     */
    public function alert($message, array $context = []) {
        yield $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     * @return \Generator|void
     */
    public function critical($message, array $context = []) {
        yield $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     * @return \Generator|void
     */
    public function error($message, array $context = []) {
        yield $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     * @return \Generator|void
     */
    public function warning($message, array $context = []) {
        yield $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     * @return \Generator|void
     */
    public function notice($message, array $context = []) {
        yield $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     * @return \Generator|void
     */
    public function info($message, array $context = []) {
        yield $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     * @return \Generator|void
     */
    public function debug($message, array $context = []) {
        yield $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     * @return \Generator
     * @throws InvalidArgumentException
     */
    public function log($level, $message, array $context = []) {
        if (!$this->checkLevel($level)) {
            throw new InvalidArgumentException('Log level[' . $level . '] is illegal');
        }
        $record = (yield $this->getRecord($level, $message, $context));
        $log = (yield $this->formatter->format($record));
        yield $this->writer->write($log);
    }

    /**
     * @param int $level
     * @return bool
     */
    protected function checkLevel($level) {
        return !isset(static::$levelMap[$level]) ? FALSE : TRUE;
    }

    /**
     * @param int    $level
     * @param string $message
     * @param array  $context
     * @return \Generator
     */
    protected function getRecord($level, $message, $context = []) {
        $levelName = static::$levelMap[$level];
        $dateTime = new DateTimeImmutable('now', $this->timezone);
        yield [
            'message'    => $message,
            'context'    => $context,
            'level'      => $level,
            'level_name' => $levelName,
            'channel'    => $this->name,
            'datetime'   => $dateTime,
            'extra'      => [],
        ];
    }

    /**
     * @param   $formatter
     * @param   $writer
     */
    protected function setHandler($formatter, $writer) {
        $this->writer = $writer;
        $this->formatter = $formatter;
    }

    private static function getDefaultConfig() {
        return [
            'factory'    => '',
            'module'     => NULL,
            'level'      => 'debug',
            'storeType'  => 'normal',
            'path'       => 'debug.log',
            'useBuffer'  => FALSE,
            'bufferSize' => 4096,
            'async'      => TRUE,
            'format'     => 'json',
        ];
    }

    private static function getConfigByKey($key) {
        $logUrl = Config::get('log.' . $key, NULL);
        if (!$logUrl) {
            throw new InvalidArgumentException('Can not find config for logKey: ' . $key);
        }
        $config = parse_url($logUrl);
        $result = self::getDefaultConfig();
        $result['factory'] = $config['scheme'];
        $result['level'] = $config['host'];
        if (isset($config['path'])) {
            $result['path'] = $config['path'];
        }
        if (isset($config['query'])) {
            parse_str($config['query'], $params);
            $params = self::fixBooleanValue($params);
            $result = Arr::merge($result, $params);
        }
        if (!$result['module']) {
            $result['module'] = $key;
        }
        if (isset($result['format'])) {
            $result['format'] = strtolower($result['format']);
        }
        // force set app value to Application name
        $result['app'] = Application::getInstance()->getName();
        return $result;
    }

    /**
     * @param $params
     * @return mixed
     */
    private static function fixBooleanValue($params) {
        if (empty($params)) {
            return $params;
        }
        foreach ($params as $key => $val) {
            if ($val == "true") {
                $params[$key] = TRUE;
            } else {
                if ($val == "false") {
                    $params[$key] = FALSE;
                }
            }
        }
        return $params;
    }
}
