<?php
/*
 *    Copyright 2012-2016 Youzan, Inc.
 *
 *    Licensed under the Apache License, Version 2.0 (the "License");
 *    you may not use this file except in compliance with the License.
 *    You may obtain a copy of the License at
 *
 *        http://www.apache.org/licenses/LICENSE-2.0
 *
 *    Unless required by applicable law or agreed to in writing, software
 *    distributed under the License is distributed on an "AS IS" BASIS,
 *    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *    See the License for the specific language governing permissions and
 *    limitations under the License.
 */
namespace Zan\Framework\Utilities\Types;

use Zan\Framework\Foundation\Exception\System\InvalidArgumentException;
use Zan\Framework\Network\Http\Response\RedirectResponse;

class URL
{
    const SCHEME_HTTP = 'http';
    const SCHEME_HTTPS = 'https';

    private static $schemes = array(
        self::SCHEME_HTTP,
        self::SCHEME_HTTPS,
    );

    private static $config;

    public static function setConfig(array &$config)
    {
        self::$config = &$config;
    }

    /**
     * @param bool $index
     * @param bool $scheme
     * @return string
     * @throws InvalidArgumentException
     */
    public static function base($index = FALSE, $scheme = false)
    {
        if (false !== $scheme && !self::_checkScheme($scheme)) {
            throw new InvalidArgumentException('Invalid scheme for URL');
        }
        $baseUrl = '/';
        $siteDomain = '';
        $scheme = (false === $scheme) ? self::SCHEME_HTTP : $scheme;

        if (is_string($index) || strlen($index)) {
            $siteDomain = isset(self::$config[$index]) ? self::$config[$index] : null;
            if (empty(($siteDomain))) {
                $siteDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
                $siteDomain = $scheme . '://' . $siteDomain;
            }
        }

        if (true === $index) {
            $siteDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            $siteDomain = $scheme . '://' . $siteDomain;
        }

        return rtrim($siteDomain . $baseUrl, '/') . '/';
    }

    /**
     * @param string $url
     * @param bool $index
     * @param bool $scheme
     * @return string
     * @throws InvalidArgumentException
     */
    public static function site($url = '', $index = TRUE, $scheme = false)
    {
        if (false !== $scheme && !self::_checkScheme($scheme)) {
            throw new InvalidArgumentException('Invalid scheme for URL::site');
        }

        $urlAnalysis = parse_url($url);
        $host = isset($urlAnalysis['host']) ? $urlAnalysis['host'] : '';
        $path = isset($urlAnalysis['path']) ? trim($urlAnalysis['path'], '/') : '';
        $query = isset($urlAnalysis['query']) ? '?' . $urlAnalysis['query'] : '';
        $fragment = isset($urlAnalysis['fragment']) ? '#' . $urlAnalysis['fragment'] : '';

        if (!empty($host)) {
            $scheme = isset($urlAnalysis['scheme']) ? $urlAnalysis['scheme'] : '';
            if (!self::_checkScheme($scheme)) {
                throw new InvalidArgumentException('Invalid url for URL::site');
            }
            $baseUrl = $scheme . '://' . $host . '/';
        } else {
            $baseUrl = URL::base($index, $scheme);
        }

        $url = $baseUrl . $path . $query . $fragment;

        return $url;
    }

    public static function getRequestUri($exclude='', $params=false)
    {
        yield getRequestUri($exclude,$params);
    }

    public static function removeParams($ps=null,$url=null)
    {
        if(null === $url){
            $url    =  (yield self::getRequestUri('',true));
        }
        if(!$ps ){
            yield $url;
            return;
        }
        $pos   = strpos($url,'?');
        if(false === $pos){
            yield $url;
            return;
        }
        if(!is_array($ps)){
            $ps = [$ps];
        }
        $prefix = substr($url,0,$pos);
        $suffix = substr($url,$pos+1);
        $pMap   = [];
        parse_str($suffix,$pMap);
        foreach($ps as $p){
            if(isset($pMap[$p])){
                unset($pMap[$p]);
            }
        }

        yield $prefix . '?' . http_build_query($pMap);
    }

    public static function redirect($url,$code=302){
        return  new RedirectResponse($url,$code);
    }

    /**
     * check the scheme is valid
     *
     * @param $scheme
     * @return bool
     */
    private static function _checkScheme($scheme)
    {
        return in_array($scheme, self::$schemes);
    }

}
