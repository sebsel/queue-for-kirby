<?php

class Queue
{
    static $jobs = [];

    public static function define($name, $action)
    {
        static::$jobs[$name] = $action;
    }

    public static function add($name, $data = null)
    {
        $jobfile = static::path() . DS . uniqid() . '.yml';

        yaml::write($jobfile, [
            'added' => date('c'),
            'name' => $name,
            'data' => $data
        ]);
    }

    private static function failed($job, $error)
    {
        $jobfile = static::path() . DS . '.failed' . DS . uniqid() . '.yml';

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

    private static function _jobs($failed = false)
    {
        $path = static::path();
        if($failed) $path = static::failedPath();

        $jobs = dir::read($path);

        $jobs = array_filter($jobs, function($job) {
            return substr($job,0,1) != '.';
        });

        return array_map(function ($job) {
            return yaml::read(static::path() . DS . $job);
        }, $jobs);
    }

    public static function jobs()
    {
        return static::_jobs(false);
    }

    public static function failedJobs()
    {
        return static::_jobs(true);
    }

    public static function hasJobs()
    {
        return static::_get_next_jobfile() !== false;
    }

    public static function flush()
    {
        dir::clean(static::path());
    }

    private static function _get_next_jobfile()
    {
        foreach(dir::read(static::path()) as $jobfile) {
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

        $job = yaml::read(static::path() . DS . $jobfile);
        f::remove(static::path() . DS . $jobfile);

        return $job;
    }

    public static function path()
    {
        return kirby()->roots()->site() . DS . 'queue';
    }

    public static function failedPath()
    {
        return static::path() . DS . '.failed';
    }

    private static function isWorking()
    {
        return f::exists(static::path() . DS . '.working');
    }

    private static function setWorking()
    {
        dir::make(static::path() . DS . '.working');
    }

    private static function stopWorking()
    {
        dir::remove(static::path() . DS . '.working');
    }
}
