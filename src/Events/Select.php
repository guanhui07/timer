<?php

namespace Guanhui07\Timer\Events;

use SplPriorityQueue;

class Select implements LibInterface
{

    protected ?SplPriorityQueue $scheduler = null;

    protected array $eventTimer = [];

    public int $timerId = 1;

    protected int $selectTimeout = 100000000;

    protected $socket = array();

    public function __construct()
    {
        $this->socket = stream_socket_pair(
            DIRECTORY_SEPARATOR === '/' ?
                STREAM_PF_UNIX : STREAM_PF_INET, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->scheduler = new SplPriorityQueue();
        $this->scheduler->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
    }

    public function add($fd, $func, $flag = true, $args = []):int
    {
        $flag = $flag === true ? self::EV_TIMER : self::EV_TIMER_ONCE;

        $timer_id = $this->timerId++;
        $run_time = microtime(true) + $fd;

        $this->scheduler->insert($timer_id, -$run_time);
        $this->eventTimer[$timer_id] = array($func, (array)$args, $flag, $fd);
        $select_timeout = ($run_time - microtime(true)) * 1000000;
        if ($this->selectTimeout > $select_timeout) {
            $this->selectTimeout = $select_timeout;
        }
        return $timer_id;
    }

    public function loop():void
    {
        while (true) {
            $read = $this->socket;
            set_error_handler(static function () {
            });
            $write = $except = [];
            stream_select($read, $write, $except, 0, $this->selectTimeout);
            restore_error_handler();

            if (!$this->scheduler->isEmpty()) {
                $this->tick();
            }
        }
    }

    public function getTimerCount(): int
    {
        return count($this->eventTimer);
    }

    /**
     * Tick for timer.
     *
     * @return void
     */
    protected function tick(): void
    {
        while (!$this->scheduler->isEmpty()) {
            $scheduler_data = $this->scheduler->top();
            $timer_id = $scheduler_data['data'];
            $next_run_time = -$scheduler_data['priority'];
            $time_now = microtime(true);
            $this->selectTimeout = ($next_run_time - $time_now) * 1000000;
            if ($this->selectTimeout <= 0) {
                $this->scheduler->extract();

                if (!isset($this->eventTimer[$timer_id])) {
                    continue;
                }
                // [func, args, flag, timer_interval]
                $task_data = $this->eventTimer[$timer_id];
                if ($task_data[2] === self::EV_TIMER) {
                    $next_run_time = $time_now + $task_data[3];
                    $this->scheduler->insert($timer_id, -$next_run_time);
                }
                call_user_func_array($task_data[0], $task_data[1]);
                if (isset($this->eventTimer[$timer_id]) && $task_data[2] === self::EV_TIMER_ONCE) {
                    $this->del($timer_id, self::EV_TIMER_ONCE);
                }
                continue;
            }
            return;
        }
        $this->selectTimeout = 100000000;
    }


    public function del($fd): bool
    {
        $fd_key = (int)$fd;
        unset($this->eventTimer[$fd_key]);
        return true;
    }


    public function clearAllTimer():void
    {
        $this->scheduler = new SplPriorityQueue();
        $this->scheduler->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $this->eventTimer = [];
    }

}
