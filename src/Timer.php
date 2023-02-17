<?php

namespace Guanhui07\Timer;

use Guanhui07\Timer\Events\Event;
use Guanhui07\Timer\Events\Select;

/**
 * Class Timer
 * @package Guanhui07\Timer
 */
class Timer
{
    public static function factory()
    {
        if (extension_loaded('event')) {
            return new Event;
        }

        return new Select();
    }
}
