# Queue for Kirby

This plugin adds a basic queue to the Kirby CMS, using Cron and Kirby's flat file system.

## Installation

Copy the files of this repo to your `site/plugins` folder. You can also run `kirby plugin:install sebsel/queue-for-kirby` if you have the [CLI tools](https://github.com/getkirby/cli) installed.

You then need to add `site/plugins/queue-for-kirby/worker.php` to your [Cron Jobs](https://en.wikipedia.org/wiki/Cron) or similar software, for once per minute, depending on how fast you want your jobs done. You can even move this file if you want, but make sure to change the `require` path to point at `kirby/bootstrap.php`, so it can load Kirby.

The plugin will try to create the folder `site/queue` and some files and folders within it.

## Configuration

The plugin is designed to be used in other plugins, see below for examples.

**Please note**: your domain specific configuration-files (`config.example.com.php`) might not have been loaded when you run the worker.

## Widget

This plugin will add a widget to the panel dashboard if there are failed jobs, or if there are more than 5 jobs in the queue (indicating that there's something wrong).

<img src="queue-widget.jpg" alt="queue-widget" width="480px">

## How to define jobs

You need to define the following things within the base-file of your plugin, not in any lazy loaded classes (with Kirby's `load()` or composer's autoloading). Just put it in `site/plugins/your_plugin/your_plugin.php`.

```php
// Make sure that the Queue plugin is loaded.
kirby()->plugin('queue-for-kirby');
if(!class_exists('queue')) throw new Exception('This plugin requires the Queue for Kirby plugin');

// Define a job by giving it a name and an action
queue::define('send_webmention', function($job) {

    // Get your data
    $target = $job->get('target');
    $source = $job->get('source');

    // Do something with your data, for example: send a webmention!
    if(!send_webmention($target, $source)) {
        // Throw an error to fail a job
        throw new Error('Sending webmention failed');
        // or just return false, but setting a message for the user is better.
    }

    // No need to return or display anything else!
});
```

Then, anywhere else in your plugin code, you can call `queue::add()` to schedule a new job. This will also work in the lazy loaded classes, if you added the previous step to your plugin base-file.

```php
// Schedule a job by giving a job-name and the data you need
queue::add('send_webmention', [
    'target' => $target,
    'source' => $source
]);
```

The data you pass in is up to you: you can define your own jobs. The only caveat is that the data needs to be stored in YAML. To access a `Page` object, you can use:

```php
queue::define('some_page_action', function($job) {
    $page = page($job->get('page'));
});

// and

queue::add('some_page_action', [
    'page' => $page->id()
]);
```

Do not forget to add `site/plugins/queue-for-kirby/worker.php` to a Cron Job, or your jobs will sit in the queue forever!

## Failed jobs

Failed jobs are currently added to `site/queue/.failed`, with some information about the error attached. They will not be retried automatically. To trigger them again, just move them one folder down to `site/queue` and make sure the worker runs.

To fail a job, either throw an exception or return false.

## Available methods

The following static methods are available on this class:

### queue::define($name, $action)

Define a new action that should be executed once the job is handled by the worker.

```php
queue::define('job_name', function($job) {
    // Do something
});
```

### queue::add($name, $data = null)

Add a new job to the queue. The data you pass in can be anything, as long as it can be stored in YAML. The data can be empty.

```php
queue::add('job_name', [
    'param' => 'some data'
]);

queue::add('another_job');
```

### queue::jobs()

Returns a [Collection](https://getkirby.com/docs/toolkit/api#collection) of Job objects, for all jobs in the queue.

Doing something with these jobs does **not** change the queue. Only `queue::work()` removes jobs from the queue.

```php
queue::jobs();
// Returns, for example:
object(Collection) {
    object(Job) {
        'id' => '5975f78ed3db6',
        'added' => '2001-01-01T01:01:01+00:00',
        'name' => 'job_name',
        'data' => [
            'param' => 'some data'
        ]
    },
    object(Job) {
        'id' => '5975f78ed303f',
        'added' => '2001-01-01T01:01:02+00:00',
        'name' => 'another_job',
        'data' => null
    }
}

// and

queue::jobs()->first();
// returns the first Job
```

### queue::failedJobs()

Returns a [Collection](https://getkirby.com/docs/toolkit/api#collection) of Job objects, representing the failed jobs.

```php
queue::failedJobs();
// Returns, for example:
object(Collection) {
    object(Job) {
        'id' => '5975f78ed3db6',
        'added' => '2001-01-01T01:01:01+00:00',
        'name' => 'job_name',
        'data' => [
            'param' => 'some data'
        ],
        'error' => 'Job returned false',
        'tried' => '2001-01-01T01:01:03+00:00'
    }
}

// and

queue::failedJobs()->last();
// returns the last failed Job
```

### queue::retry($failedJob)

Moves a failed job back in the queue. Use to trigger in a panel widget or after some other user input.

Note that this does not immediately act on the failed job. It is just added to the queue – probably at the front due to it's old ID – and gets handled as soon as your Cron Job executes `worker.php`.

```php
$failedJob = queue::failedJobs()->first();

queue::retry($failedJob);

// or

queue::retry('5975f78ed3db6');
```

### queue::remove($failedJob)

Removes a failed job entirely. Note that this only works for failed jobs.

```php
$failedJob = queue::failedJobs()->last();

queue::remove($failedJob);

// or

queue::remove('5975f78ed3db6');
```

### queue::work()

Executes the first job in the queue. Don't call this one outside of `worker.php`, because that would defeat the purpose of the queue.

### queue::hasJobs()

Returns `true` or `false`, depending on wether there are jobs in the queue.

### queue::flush()

Removes all jobs from the queue, **including** failed jobs.

### queue::path()

Returns the full path of `site/queue`.

### queue::failedPath()

Returns the full path of `site/queue/.failed`.



## Job methods

On a Job object, you can find the following methods:

### $job->get($key)

Tries to get the value under `$key` from the `$data` array. (You can also pass a non-array to `$data`, in which case `$job->get('some_key')` will return `null`, and you have to use `$job->data()` to get it.)

```php
queue::define('job_name', function($job) {
    $job->get('param');
    // contains 'some data' in the job added below
});

queue::add('job_name', [
    'param' => 'some data'
]);
```

### $job->data()

Returns the `$data` that was passed in when the job was created.

### $job->id()

Returns the ID of the job, which is a unique identifier for the job. This is also the filename, minus '.yml'.

### $job->name()

Returns the name of the job, which is the name of the action that is performed when working on the job.

### $job->added()

Returns the date the job was added to the queue, formatted as `date('c')` (`2001-01-01T01:01:01+00:00`).

### $job->error()

Returns the error for a failed job, or `null` on a normal one.

### $job->tried()

Returns the date the job was last tried to execute, formatted as `date('c')` (`2001-01-01T01:01:01+00:00`). This will be `null` for non-failed jobs.
