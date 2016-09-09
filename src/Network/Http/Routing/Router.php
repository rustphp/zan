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
namespace Zan\Framework\Network\Http\Routing;

use Zan\Framework\Network\Http\Request\Request;
use Zan\Framework\Utilities\DesignPattern\Singleton;

class Router {

    use Singleton;

    private $config;
    private $url;
    private $route = '';
    private $format = '';
    private $rules = [];
    private $parameters = [];
    private $separator = '/';

    private function prepare($url)
    {
        if(empty($url)) {
            return;
        }
        $base_path = isset($this->config['base_path']) ? trim($this->config['base_path']) : NULL;
        if ($base_path) {
            $url = str_replace($base_path, $this->separator, $url);
        }
        $this->url = ltrim($url, $this->separator);
        $this->removeIllegalString();
        $this->rules = UrlRule::getRules();
    }

    private function clear()
    {
        $this->url = '';
        $this->route = '';
        $this->format = '';
        $this->parameters = [];
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function route(Request $request)
    {
        $requestUri = $request->server->get('REQUEST_URI');
        if(preg_match('/\.ico$/i', $requestUri)){
            $requestUri = '';
        }
        if (!$requestUri) {
            throw new InvalidRouteException();
        }
        $this->prepare($requestUri);
        $this->parseRequestFormat($requestUri);
        empty($this->url) ? $this->setDefaultRoute() : $this->parseRegexRoute();
        $this->repairRoute();
        $request->setRoute($this->route);
        $request->setRequestFormat($this->format);
        $this->setParameters($request, $this->parameters);
        $route = $this->parseRoute();
        $this->clear();
        return $route;
    }

    public function parseRoute()
    {
        $parts = array_filter(explode($this->separator, trim($this->route, $this->separator)));
        $route['action_name'] = array_pop($parts);
        $route['controller_name'] = join($this->separator, $parts);
        return $route;
    }

    private function parseRequestFormat($requestUri)
    {
        if(false === strpos($requestUri, '.')) {
            $this->setDefaultFormat();
            return;
        }
        $base_path = isset($this->config['base_path']) ? trim($this->config['base_path']) : NULL;
        if ($base_path) {
            $requestUri = str_replace($base_path, $this->separator, $requestUri);
        }
        $explodeArr = explode('.', $requestUri);
        $whiteList = isset($this->config['format_whitelist']) ? $this->config['format_whitelist'] : array();
        $this->format = in_array($explodeArr[1], $whiteList) ? trim($explodeArr[1]) : $this->getDefaultFormat();
        $this->url = $explodeArr[0];
    }

    private function repairRoute()
    {
        $path = array_filter(explode($this->separator, $this->route));
        $pathCount = count($path);
        switch($pathCount)
        {
        case 0:
            $this->setDefaultRoute();
            break;
        case 1:
            $this->setDefaultControllerAndDefaultAction();
            break;
        case 2:
            $this->setDefaultAction();
            break;
        }
    }

    private function parseRegexRoute()
    {
        $rules = UrlRegex::formatRules($this->rules);
        $result = UrlRegex::decode($this->url, $rules);
        $this->route = ltrim($result['url'], $this->separator);
        $this->parameters = $result['parameter'];
    }

    private function setParameters(Request $request, array $parameters = [])
    {
        if(empty($parameters)) {
            return;
        }
        $request->query->add($parameters);
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    private function setDefaultRoute()
    {
        $this->route = $this->getDefaultRoute();
    }

    private function getDefaultRoute()
    {
        return $this->config['default_route'];
    }

    private function setDefaultControllerAndDefaultAction()
    {
        $path = array_filter(explode($this->separator, $this->route));
        array_push($path, $this->getDefaultController(), $this->getDefaultAction());
        $this->route = join($this->separator, $path);
    }

    private function getDefaultController()
    {
        return $this->config['default_controller'];
    }

    private function setDefaultAction()
    {
        $path = array_filter(explode($this->separator, $this->route));
        array_push($path, $this->getDefaultAction());
        $this->route = join($this->separator, $path);
    }

    private function getDefaultAction()
    {
        return $this->config['default_action'];
    }

    private function setDefaultFormat()
    {
        $this->format = $this->getDefaultFormat();
    }

    private function getDefaultFormat()
    {
        return $this->config['default_format'];
    }

    private function removeIllegalString()
    {
        $patterns   = [
            '/^\s*\/\//','/\?.*$/','/\#.*$/','/\/\s*$/','/^\s*\//'
        ];
        $replaces   = [
            '','','','','',
        ];
        $this->url = preg_replace($patterns, $replaces, $this->url);
    }
}