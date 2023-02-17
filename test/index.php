<?php
require __DIR__ . '/../vendor/autoload.php';
use Guanhui07\Timer\Daemon;

$timer = Daemon::runAll();

function microtime_float()
{
    [$usec, $sec] = explode(" ", microtime());
    return bcadd($usec, $sec, 3);
}

//2.5秒
$timer->add(2.5, function () {

    if (Daemon::getOS() === OS_TYPE_WIN) {
        echo microtime_float() . "\n";
    } else {
        file_put_contents("/tmp/test.txt", microtime_float() . "\n", FILE_APPEND);
    }
});

$timer->add(1, function () {
    if (Daemon::getOS() === OS_TYPE_WIN) {
        echo microtime_float() . "once \n";
    } else {
        file_put_contents("/tmp/test.txt", microtime_float() . "once \n", FILE_APPEND);
    }
}, false);

$timer->loop();
