<?php

class Queue
{
    static $jobs = [];

    public static function define($name, $action)
    {
        static::$jobs[$name] = $action;
    }

    public static function add($name, $data)
    {
        $jobfile = static::_queue_path() . DS . uniqid() . '.yml';

        yaml::write($jobfile, [
            'added' => date('c'),
            'name' => $name,
            'data' => $data
        ]);
    }

    private static function failed($job, $error)
    {
        $jobfile = static::_queue_path() . DS . '.failed' . DS . uniqid() . '.yml';

        yaml::write($jobfile, [
            'added' => $job['added'],
            'name' => $job['name'],
            'data' => $job['data'],
            'error' => $error,
            'tried' => date('c')
        ]);
    }

    public static function work()
    {
        // Protect ourselfs against multiple workers at once
        if (static::isWorking()) exit();
        static::setWorking();

        if (static::hasJobs()) {
            $job = static::_get_next_job();
            try {
                if (call_user_func(static::$jobs[$job['name']], $job['data']) === false) {
                    throw new Error('Job returned false');
                }
            } catch (Exception $e) {
                static::failed($job, $e->getMessage());
            } catch (Error $e) {
                static::failed($job, $e->getMessage());
            }
        }

        static::stopWorking();
    }

    public static function hasJobs()
    {
        return static::_get_next_jobfile() !== false;
    }

    private static function _get_next_jobfile()
    {
        foreach(dir::read(static::_queue_path()) as $jobfile) {
            // No .working or .DS_store
            if (substr($jobfile,0,1) == '.') continue;

            // Return first jobfile we find
            return $jobfile;
        }

        return false;
    }

    private static function _get_next_job()
    {
        $jobfile = static::_get_next_jobfile();

        $job = yaml::read(static::_queue_path() . DS . $jobfile);
        f::remove(static::_queue_path() . DS . $jobfile);

        return $job;
    }

    private static function _queue_path()
    {
        return kirby()->roots()->site() . DS . 'queue';
    }

    private static function isWorking()
    {
        return f::exists(static::_queue_path() . DS . '.working');
    }

    private static function setWorking()
    {
        dir::make(static::_queue_path() . DS . '.working');
    }

    private static function stopWorking()
    {
        dir::remove(static::_queue_path() . DS . '.working');
    }
}
