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
namespace Zan\Framework\Network\Http;

use \swoole_http_request as SwooleHttpRequest;
use \swoole_http_response as SwooleHttpResponse;
use Zan\Framework\Foundation\Core\Config;
use Zan\Framework\Foundation\Core\Debug;
use Zan\Framework\Foundation\Coroutine\Signal;
use Zan\Framework\Foundation\Coroutine\Task;
use Zan\Framework\Network\Http\Response\BaseResponse;
use Zan\Framework\Network\Http\Response\InternalErrorResponse;
use Zan\Framework\Network\Http\Routing\Router;
use Zan\Framework\Network\Server\Middleware\MiddlewareManager;
use Zan\Framework\Network\Server\Timer\Timer;
use Zan\Framework\Utilities\DesignPattern\Context;
use Zan\Framework\Network\Http\Request\Request;
use Zan\Framework\Utilities\Types\Time;

class RequestHandler
{
    private $context = null;
    /**
     * @var \Zan\Framework\Network\Server\Middleware\MiddlewareManager
     */
    private $middleWareManager = null;
    /**
     * @var \Zan\Framework\Foundation\Coroutine\Task
     */
    private $task = null;
    private $event = null;

    const DEFAULT_TIMEOUT = 30000;

    public function __construct()
    {
        $this->context = new Context();
        $this->event = $this->context->getEvent();
    }

    public function handle(SwooleHttpRequest $swooleRequest, SwooleHttpResponse $swooleResponse)
    {
        try {
            $request = Request::createFromSwooleHttpRequest($swooleRequest);
            $this->initContext($request, $swooleResponse);
            $this->middleWareManager = new MiddlewareManager($request, $this->context);

            $requestTask = new RequestTask($request, $swooleResponse, $this->context, $this->middleWareManager);
            $coroutine = $requestTask->run();

            //bind event
            $timeout = $this->context->get('request_timeout');
            $this->event->once($this->getRequestFinishJobId(), [$this, 'handleRequestFinish']);
            Timer::after($timeout, [$this, 'handleTimeout'], $this->getRequestTimeoutJobId());

            $this->task = new Task($coroutine, $this->context);
            $this->task->run();

        } catch (\Exception $e) {
            if (Debug::get()) {
                echo_exception($e); 
            }
            $coroutine = RequestExceptionHandlerChain::getInstance()->handle($e);
            Task::execute($coroutine, $this->context);
            $this->event->fire($this->getRequestFinishJobId());
        }

    }

    private function initContext($request, SwooleHttpResponse $swooleResponse)
    {

        $this->context->set('request', $request);
        $this->context->set('swoole_response', $swooleResponse);

        $router = Router::getInstance();
        $route = $router->route($request);
        $this->context->set('controller_name', $route['controller_name']);
        $this->context->set('action_name', $route['action_name']);

        $cookie = new Cookie($request, $swooleResponse);
        $this->context->set('cookie', $cookie);

        $this->context->set('request_time', Time::stamp());
        $request_timeout = Config::get('server.request_timeout');
        $request_timeout = $request_timeout ? $request_timeout : self::DEFAULT_TIMEOUT;
        $this->context->set('request_timeout', $request_timeout);

        $this->context->set('request_end_event_name', $this->getRequestFinishJobId());
    }

    public function handleRequestFinish()
    {
        Timer::clearAfterJob($this->getRequestTimeoutJobId());
        $response = $this->context->get('response');
        $coroutine = $this->middleWareManager->executeTerminators($response);
        Task::execute($coroutine, $this->context);
    }
    public function handleTimeout()
    {
        $this->task->setStatus(Signal::TASK_KILLED);
        $response = new InternalErrorResponse('服务器超时', BaseResponse::HTTP_GATEWAY_TIMEOUT);
        $this->context->set('response', $response);
        $swooleResponse = $this->context->get('swoole_response');
        $response->sendBy($swooleResponse);
        $this->event->fire($this->getRequestFinishJobId());
    }

    private function getRequestFinishJobId()
    {
        return spl_object_hash($this) . '_request_finish';
    }

    private function getRequestTimeoutJobId()
    {
        return spl_object_hash($this) . '_handle_timeout';
    }
}
