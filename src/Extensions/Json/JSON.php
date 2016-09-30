<?php
namespace Zan\Framework\Extensions\Json;
/**
 * Class JSON
 * @package Zan\Framework\Utilities\Json
 */
class JSON {
    /**
     * Return the JSON representation of a value
     *
     * @param  mixed $data
     * @return \Generator|void
     */
    public static function encode($data) {
        $json = (yield json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $error_code = (yield json_last_error());
        if (JSON_ERROR_NONE === $error_code) {
            yield $json;
            return;
        }
        if (JSON_ERROR_UTF8 !== $error_code) {
            yield FALSE;
            return;
        }
        $data = self::detectAndCleanUtf8($data);
        yield self::encode($data);
    }

    /**
     * @param string $json
     * @param bool   $assoc
     * @return \Generator|void
     */
    public static function decode($json, $assoc = FALSE) {
        $result = (yield json_decode($json, $assoc));
        $error_code = (yield json_last_error());
        if (JSON_ERROR_NONE !== $error_code) {
            yield FALSE;
            return;
        }
        yield $result;
    }

    /**
     * Detect invalid UTF-8 string characters and convert to valid UTF-8.
     *
     * Valid UTF-8 input will be left unmodified, but strings containing
     * invalid UTF-8 codepoints will be reencoded as UTF-8 with an assumed
     * original encoding of ISO-8859-15. This conversion may result in
     * incorrect output if the actual encoding was not ISO-8859-15, but it
     * will be clean UTF-8 output and will not rely on expensive and fragile
     * detection algorithms.
     *
     * Function converts the input in place in the passed variable so that it
     * can be used as a callback for array_walk_recursive.
     *
     * @param mixed &$data Input to check and convert if needed
     * @public
     * @return \Generator|void
     */
    public function detectAndCleanUtf8($data) {
        if (!is_string($data) || preg_match('//u', $data)) {
            yield $data;
            return;
        }
        $data = (yield preg_replace_callback(
            '/[\x80-\xFF]+/',
            function ($m) {
                return utf8_encode($m[0]);
            },
            $data
        ));
        yield str_replace(
            ['¤', '¦', '¨', '´', '¸', '¼', '½', '¾'],
            ['€', 'Š', 'š', 'Ž', 'ž', 'Œ', 'œ', 'Ÿ'],
            $data
        );
    }
}