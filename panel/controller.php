<?php

namespace Queue;

use Queue;
use Error;

class Controller extends \Kirby\Panel\Controllers\Base
{
    public static function retry($id)
    {
        try {
            queue::retry($id);

            panel()->notify(':)');
        } catch (Error $e) {
            panel()->alert($e->getMessage());
        }

        panel()->redirect();
    }

    public static function remove($id)
    {
        try {
            queue::remove($id);

            panel()->notify(':)');
        } catch (Error $e) {
            panel()->alert($e->getMessage());
        }

        panel()->redirect();
    }

    public static function flush()
    {
        queue::flush();

        panel()->notify(':)');

        panel()->redirect();
    }

    public static function work()
    {
        $count_jobs = queue::jobs()->count();

        while (queue::hasJobs()) {
            queue::work();
        }

        $count_failed = queue::failedJobs()->count();

        $message = 'Did ' . ($count_jobs == 1 ? '1 job' : $count_jobs . ' jobs');

        if ($count_failed) {
            $message .=  ', ';
            $message .= ($count_failed == 1 ? '1 job' : $count_failed . ' jobs');
            $message .= ' failed';

            panel()->alert($message);
        } else {
            panel()->notify($message);
        }

        panel()->redirect();
    }
}

