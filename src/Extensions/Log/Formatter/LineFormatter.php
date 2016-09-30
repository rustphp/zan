<?php
namespace Zan\Framework\Extensions\Log\Formatter;
use Zan\Framework\Utilities\Json\JSON;

/**
 * Formats incoming records into a one-line string
 *
 * This is especially useful for logging to files
 *
 */
class LineFormatter extends BaseFormatter {
    const DEFAULT_FORMAT = "[%datetime%] %message% %context% %extra%\n";
    protected $format;
    protected $allowInlineLineBreaks;
    protected $includeStacktraces;

    /**
     * @param string $format The format of the message
     * @param string $dateFormat The format of the timestamp: one supported by DateTime::format
     * @param bool   $allowInlineLineBreaks Whether to allow inline line breaks in log entries
     * @param bool   $ignoreEmptyContextAndExtra
     */
    public function __construct($format = NULL, $dateFormat = NULL, $allowInlineLineBreaks = FALSE) {
        $this->format = $format ?: static::DEFAULT_FORMAT;
        $this->allowInlineLineBreaks = $allowInlineLineBreaks;
        parent::__construct($dateFormat);
    }

    public function includeStacktraces($include = TRUE) {
        $this->includeStacktraces = $include;
        if ($this->includeStacktraces) {
            $this->allowInlineLineBreaks = TRUE;
        }
    }

    public function allowInlineLineBreaks($allow = TRUE) {
        $this->allowInlineLineBreaks = $allow;
    }

    public function ignoreEmptyContextAndExtra($ignore = TRUE) {
        $this->ignoreEmptyContextAndExtra = $ignore;
    }

    /**
     * @param array $records
     * @param bool  $isBatch
     * @return \Generator|void
     */
    public function format($records, $isBatch = FALSE) {
        $formated = (yield parent::format($records, $isBatch));
        $output = [];
        foreach ($formated as $vars) {
            $format = $this->format;
            $vars['extra'] = isset($vars['extra']) ? $vars['extra'] : [];
            foreach ($vars['extra'] as $var => $val) {
                if (FALSE !== strpos($format, '%extra.' . $var . '%')) {
                    $val = (yield $this->stringify($val));
                    $format = str_replace('%extra.' . $var . '%', $val, $format);
                    unset($vars['extra'][$var]);
                }
            }
            $vars['context'] = isset($vars['context']) ? $vars['context'] : [];
            foreach ($vars['context'] as $var => $val) {
                if (FALSE !== strpos($format, '%context.' . $var . '%')) {
                    $val = (yield $this->stringify($val));
                    $format = str_replace('%context.' . $var . '%', $val, $format);
                    unset($vars['context'][$var]);
                }
            }
            if (!$vars['context']) {
                unset($vars['context']);
                $format = str_replace('%context%', '', $format);
            }
            if (!$vars['extra']) {
                unset($vars['extra']);
                $format = str_replace('%extra%', '', $format);
            }
            foreach ($vars as $var => $val) {
                if (FALSE !== strpos($format, '%' . $var . '%')) {
                    $val = (yield $this->stringify($val));
                    $format = str_replace('%' . $var . '%', $val, $format);
                }
            }
            $output[] = $format;
        }
        yield implode("\n\r", $output);
    }

    public function stringify($value) {
        if (NULL === $value || is_bool($value)) {
            yield var_export($value, TRUE);
            return;
        }
        if (is_scalar($value)) {
            yield (string)$value;
            return;
        }
        $json = (yield JSON::encode($value));
        yield $this->replaceNewlines($json);
    }

    protected function normalizeException(\Throwable $e) {
        $previousText = '';
        if ($previous = $e->getPrevious()) {
            do {
                $previousText .= ', ' . get_class($previous) . '(code: ' . $previous->getCode() . '): ' . $previous->getMessage() . ' at ' . $previous->getFile() . ':' . $previous->getLine();
            } while ($previous = $previous->getPrevious());
        }
        $str = '[object] (' . get_class($e) . '(code: ' . $e->getCode() . '): ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . $previousText . ')';
        if ($this->includeStacktraces) {
            $str .= "\n[stacktrace]\n" . $e->getTraceAsString();
        }
        return $str;
    }

    protected function replaceNewlines($str) {
        if ($this->allowInlineLineBreaks) {
            yield $str;
            return;
        }
        yield str_replace(["\r\n", "\r", "\n"], ' ', $str);
    }
}