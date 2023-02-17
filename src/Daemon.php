<?php

namespace Guanhui07\Timer;

use RuntimeException;

/**
 * Class Daemon
 * @package Guanhui07\Timer
 */
class Daemon
{
    public static string $stdoutFile = '/dev/null';
    public static string $daemonName = 'daemon php';
    protected static string $OS = OS_TYPE_LIN;

    public static function runAll()
    {
        self::init();
        self::checkEnvCli(); //检查环境

        //如果是win
        if (static::$OS !== OS_TYPE_LIN) {
            return Timer::factory();
        }
        self::daemonize(); //守护进程化
        self::chdir(); //改变工作目录
        self::closeSTD(); //关闭标准输出、标准错误
        self::setProcessTitle(self::$daemonName); //设置守护进程的名字
        return Timer::factory();
    }

    public static function init(): void
    {
        ini_set('display_errors', 'on');
        error_reporting(E_ALL);
        //重置opcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        //定义常量
        define('OS_TYPE_LIN', 'linux');
        define('OS_TYPE_WIN', 'windows');

    }

    /**
     * 检测执行环境，必须是linux系统和cli方式执行
     */
    protected static function checkEnvCli(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::$OS = OS_TYPE_WIN;
        }

        if (PHP_SAPI !== "cli") {
            exit("only run in command line mode \n");
        }
    }

    /**
     * 设置掩码 fork两次、设置会话组长
     */
    protected static function daemonize(): void
    {
        umask(0);
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new RuntimeException('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        if (-1 === posix_setsid()) {
            throw new RuntimeException("set sid fail");
        }
        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new RuntimeException("fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }
    }

    /**
     * 改变工作目录
     */
    protected static function chdir(): void
    {
        if (!chdir('/')) {
            throw new RuntimeException("change dir fail", 1);
        }
    }

    /**
     * 关闭标准输出、标准错误
     */
    protected static function closeSTD(): void
    {
        //定义两个全局变量
        global $STDOUT, $STDERR;
        $handle = fopen(static::$stdoutFile, 'ab');
        if ($handle) {
            unset($handle);
            set_error_handler(static function () {
            });
            $STDOUT && fclose($STDOUT);
            $STDERR && fclose($STDERR);
            fclose(STDOUT);
            fclose(STDERR);
            $STDOUT = fopen(static::$stdoutFile, 'ab');
            $STDERR = fopen(static::$stdoutFile, 'ab');

            restore_error_handler();
        } else {
            throw new RuntimeException('can not open stdoutFile ' . static::$stdoutFile);
        }
    }

    /**
     * 设置定时器名字
     *
     * @param string $title
     * @return void
     */
    protected static function setProcessTitle(string $title): void
    {
        set_error_handler(static function () {
        });

        cli_set_process_title($title);


        restore_error_handler();
    }

    /**
     * 返回当前执行环境
     * @return string [type] [description]
     */
    public static function getOS(): string
    {
        return self::$OS;
    }
}
