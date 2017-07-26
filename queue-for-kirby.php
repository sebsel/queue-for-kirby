<?php

class Job extends Obj {
    public function get($key, $default = null)
    {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }
}

class Queue
{
    private static $actions = [];

    /**
     * Defines an action to perform when job is worked on
     * @param  string    Name of the action
     * @param  Callable  Closure with the action
     */
    public static function define($name, $action)
    {
        static::$actions[$name] = $action;
    }

    /**
     * Adds a new job to the queue
     * @param string  Name of the action to be performed
     * @param mixed   Any data you want to pass in
     */
    public static function add($name, $data = null)
    {
        $id = uniqid();

        $jobfile = static::path() . DS . $id . '.yml';

        yaml::write($jobfile, [
            'id' => $id,
            'added' => date('c'),
            'name' => $name,
            'data' => $data
        ]);
    }

    private static function failed($job, $error)
    {
        $jobfile = static::path() . DS . '.failed' . DS . $job->id() . '.yml';

        yaml::write($jobfile, [
            'id' => $job->id(),
            'added' => $job->added(),
            'name' => $job->name(),
            'data' => $job->data(),
            'error' => $error,
            'tried' => date('c')
        ]);
    }

    /**
     * Executes the first job in the queue folder
     */
    public static function work()
    {
        // Protect ourselfs against multiple workers at once
        if (static::isWorking()) exit();
        static::setWorking();

        if (static::hasJobs()) {
            $job = static::_get_next_job();
            try {
                if (!isset(static::$actions[$job->name()])
                    or !is_callable(static::$actions[$job->name()])) {
                    throw new Error("Action '{$job->name()}' not defined");
                }
                if (call_user_func(static::$actions[$job->name()], $job) === false) {
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

    private static function _jobs($failed)
    {
        $path = static::path();
        if($failed) $path = static::failedPath();

        $jobs = dir::read($path);

        $jobs = array_filter($jobs, function($job) {
            return substr($job,0,1) != '.';
        });

        $jobs = array_map(function ($job) use ($path) {
            return new Job(yaml::read($path . DS . $job));
        }, $jobs);

        return new Collection($jobs);
    }

    /**
     * Returns all jobs in the queue
     * @return Collection  Collection with Job objects
     */
    public static function jobs()
    {
        return static::_jobs(false);
    }

    /**
     * Returns all failed jobs
     * @return Collection  Collection with Job objects
     */
    public static function failedJobs()
    {
        return static::_jobs(true);
    }

    /**
     * @return boolean
     */
    public static function hasJobs()
    {
        return static::_get_next_jobfile() !== false;
    }

    /**
     * Removes all jobs from the queue, including failed jobs
     */
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

        return new Job($job);
    }

    /**
     * Returns the full path of the queue folder
     * @return string
     */
    public static function path()
    {
        return kirby()->roots()->site() . DS . 'queue';
    }

    /**
     * Returns the full path of the failed jobs folder
     * @return string
     */
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
