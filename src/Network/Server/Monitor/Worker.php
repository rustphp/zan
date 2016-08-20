<?php

/**
 * Created by PhpStorm.
 * User: heize
 * Date: 16/4/26
 * Time: 下午4:57
 */

namespace Zan\Framework\Network\Server\Monitor;

use Zan\Framework\Utilities\DesignPattern\Singleton;
use Zan\Framework\Network\Server\Timer\Timer;
use Zan\Framework\Utilities\Types\Time;


class Worker
{
    use Singleton;

    const GAP_TIME = 180000;
    const GAP_REACTION_NUM = 1500;
    const DEFAULT_MAX_CONCURRENCY = 500;

    public $classHash;
    public $workerId;
    public $server;
    public $config;

    public $reactionNum;
    public $totalReactionNum;
    public $maxConcurrency;

    /**
     * @param $server
     * @param $config
     */
    public function init($server,$config){
        if(!is_array($config)){
            return ;
        }

        $this->classHash = spl_object_hash($this);
        $this->server = $server;
        $this->workerId = $server->swooleServer->worker_id;
        $this->config = $config;
        $this->reactionNum = 0;
        $this->totalReactionNum = 0;
        $this->maxConcurrency = isset($this->config['max_concurrency']) ?
                                        $this->config['max_concurrency'] :
                                        self::DEFAULT_MAX_CONCURRENCY;

        $this->restart();
        $this->checkStart();
    }

    public function restart()
    {
        $time = isset($this->config['max_live_time'])?$this->config['max_live_time']:1800000;
        $time += $this->workerId * self::GAP_TIME;

        Timer::after($time, [$this,'closePre'], $this->classHash.'_restart');
    }

    public function checkStart(){
        $time = isset($this->config['check_interval'])?$this->config['check_interval']:5000;

        Timer::tick($time, [$this,'check'], $this->classHash.'_check');
    }

    public function check(){
        $this->output('check');

        $memory =  memory_get_usage();
        $memory_limit = isset($this->config['memory_limit'])
                ? $this->config['memory_limit']
                : 1024 * 1024 * 1024 * 1.5;

        $reaction_limit = isset($this->config['max_request'])
                ? $this->config['max_request']
                : 100000;
        $reaction_limit = $reaction_limit + $this->workerId * self::GAP_REACTION_NUM;

        if($memory > $memory_limit
            || $this->totalReactionNum > $reaction_limit
        ){
            $this->closePre();
        }
    }


    public function closePre()
    {
        $this->output('ClosePre');

        Timer::clearTickJob($this->classHash.'_check');

        /* @var $this->server Server */
        $this->server->swooleServer->deny_request($this->workerId);
        
        $this->closeCheck();
    }

    public function closeCheck(){
        $this->output('CloseCheck');

        if($this->reactionNum > 0){
            Timer::after(1000,[$this,'closeCheck']);
        }else{
            $this->close();
        }
    }

    public function close(){
        $this->output('Close');

        echo "close:workerId->".$this->workerId.",time:".Time::current(true)."\n";

        $this->server->swooleServer->exit();
    }

    public function reactionReceive(){
        //触发限流
        if ($this->reactionNum > $this->maxConcurrency) {
            return false;
        }
        $this->totalReactionNum++;
        $this->reactionNum ++;
        return true;
    }

    public function reactionRelease(){
        $this->reactionNum --;
    }


    public function output($str){
        if(isset($this->config['debug']) && true == $this->config['debug']){
            $output = "###########################\n";
            $output .= $str.":workerId->".$this->workerId."\n";
            $output .= 'time:'.time()."\n";
            $output .= "request number:".$this->reactionNum."\n";
            $output .= "total request number:".$this->totalReactionNum."\n";
            $output .= "###########################\n\n";
            echo $output;
        }
    }

}