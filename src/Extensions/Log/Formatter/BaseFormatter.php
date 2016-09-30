<?php
namespace Zan\Framework\Extensions\Log\Formatter;
use Throwable;
use Zan\Framework\Extensions\Json\JSON;

/**
 * Class BaseFormatter
 * @package Zan\Framework\Foundation\Log\Formatter
 */
abstract class BaseFormatter implements ILogFormatter {
    const SIMPLE_DATE_FORMAT = "Y-m-d H:i:s.u";
    protected $dateFormat;

    /**
     * BaseFormatter constructor.
     * @param string $dateFormat
     */
    public function __construct($dateFormat = NULL) {
        $this->dateFormat = NULL === $dateFormat ? static::SIMPLE_DATE_FORMAT : $dateFormat;
    }

    /**
     * @param array $records
     * @param bool  $isBatch
     * @return \Generator|void
     */
    public function format($records, $isBatch = FALSE) {
        if (!$isBatch) {
            $records = [$records];
        }
        $result = [];
        foreach ($records as $key => $record) {
            $result[$key] = (yield $this->normalize($record));
        }
        yield $result;
    }

    /**
     * @param array $data
     * @param int   $depth
     * @return \Generator|string|void
     */
    protected function normalize($data, $depth = 0) {
        if ($depth > 9) {
            yield 'Over 9 levels deep, aborting normalization';
            return;
        }
        if (is_object($data)) {
            yield $this->normalizeObject($data);
            return;
        }
        if (is_resource($data)) {
            yield sprintf('[resource(%s)]', get_resource_type($data));
            return;
        }
        yield $data;
    }

    /**
     * @param $obj
     * @return \Generator|void
     */
    protected function normalizeObject($obj) {
        if ($obj instanceof Throwable) {
            yield $this->normalizeException($obj);
            return;
        }
        $class = get_class($obj);
        if ($obj instanceof \JsonSerializable) {
            yield [$class => $obj->jsonSerialize()];
            return;
        }
        if (method_exists($obj, '__toString')) {
            yield [$class => $obj->__toString()];
            return;
        }
        // the rest is normalized by json encoding and decoding it
        $encoded = JSON::encode($obj);
        if ($encoded === FALSE) {
            $value = 'JSON_ERROR';
        } else {
            $value = json_decode($encoded, TRUE);
        }
        yield [$class => $value];
    }

    /**
     * @param Throwable $e
     * @return \Generator
     */
    protected function normalizeException(Throwable $e) {
        yield $data = [
            'class'   => get_class($e),
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile() . ':' . $e->getLine(),
            'param'   => $e->getTrace()[0]['args'],
        ];
        $trace = $e->getTrace();
        foreach ($trace as $frame) {
            if (isset($frame['file'])) {
                yield $data['trace'][] = $frame['file'] . ':' . $frame['line'];
            } elseif (isset($frame['function']) && $frame['function'] === '{closure}') {
                yield $data['trace'][] = $frame['function'];
            } else {
                yield $data['trace'][] = $this->normalize($frame);
            }
        }
        if ($previous = $e->getPrevious()) {
            yield $data['previous'] = $this->normalizeException($previous);
        }
        yield $data;
    }

    /**
     * @param \DateTimeInterface $date
     * @return \Generator|void
     */
    protected function formatDate($date) {
        if ($this->dateFormat === self::SIMPLE_DATE_FORMAT) {
            yield (string)$date;
            return;
        }
        yield $date->format($this->dateFormat);
    }
}