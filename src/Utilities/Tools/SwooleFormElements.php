<?php
/**
 * Created by PhpStorm.
 * User: rustysun
 * Date: 16/8/23
 * Time: 上午6:58
 */
namespace Zan\Framework\Utilities\Tools;
/**
 * Class SwooleFormElements
 * @package Zan\Framework\Contract\Utilities\Tools
 */
final class SwooleFormElements {
    protected static $result;

    public static function toArray($elements) {
        self::$result = [];
        if ($elements && is_array($elements)) {
            self::doParse($elements);
        }
        return self::getResult();
    }

    /**
     * @param $elements
     */
    protected static function doParse($elements) {
        foreach ($elements as $key => $value) {
            $names = explode('[', $key);
            $count = count($names) - 1;
            if ($names && is_array($names)) {
                self::parseArray($names, $count, $value);
                continue;
            }
            self::$result[$key] = $value;
        }
    }

    /**
     * @param $names
     * @param $lastIndex
     */
    protected static function parseArray($names, $lastIndex, $value) {
        $result = &self::$result;
        foreach ($names as $index => $name) {
            $name = str_replace(']', '', $name);
            $result[$name] = isset($result[$name]) ? $result[$name] : [];
            if ($index == $lastIndex) {
                $result[$name] = $value;
                return;
            }
            $result = &$result[$name];
        }
    }

    /**
     * @return mixed
     */
    protected static function getResult() {
        return self::$result;
    }
}