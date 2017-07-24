<?php
use PHPUnit\Framework\TestCase;

// Place this test in a fresh install of Kirby to test it.
// Go to `site/plugins/queue-for-kirby/tests` in the terminal
// Then run `phpunit test --colors` (yaay, colors!)
// Make sure you installed PHPUnit

if(!defined('DS'))  define('DS', DIRECTORY_SEPARATOR);
require(dirname(__DIR__).DS.'..'.DS.'..'.DS.'..'.DS.'kirby'.DS.'bootstrap.php');

// Sorry, need to load some Kirby stuff
$kirby = kirby(); $site = site();
$kirby->extensions(); $kirby->models(); $kirby->plugins();

/**
 * @covers Queue
 */
final class QueueTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        if(count(dir::read(queue::path())))
            die('There are jobs in the queue! Make sure to start fresh.');
    }

    public function tearDown()
    {
        queue::flush();
    }

    /** @test */
    public function Queue_add__adds_jobs()
    {
        $jobcount = count(dir::read(queue::path()));

        queue::add('test_job', [
            'param' => 'some data'
        ]);

        $this->assertEquals(
            count(dir::read(queue::path())),
            $jobcount + 1
        );
    }

    /** @test */
    public function Queue_jobs__returns_jobs()
    {
        queue::add('test_job', [
            'param' => 'some data'
        ]);

        queue::add('another_job');

        $jobs = queue::jobs();

        $this->assertCount(2, $jobs);

        // Test first job of queue
        $job = array_shift($jobs);
        $this->assertEquals('test_job', $job->name());
        $this->assertEquals([
            'param' => 'some data'
        ], $job->data());

        // Test second job of queue
        $job = array_shift($jobs);
        $this->assertEquals('another_job', $job->name());
        $this->assertEquals(null, $job->data());

        // Note that the code above does not remove the jobs!
        $this->assertCount(2, queue::jobs());
        // Use queue::work() for that
    }

    /** @test */
    public function Queue_flush__removes_all_jobs()
    {
        $this->assertCount(0, queue::jobs());

        queue::add('test_job');
        queue::add('test_job');
        queue::add('test_job');

        $this->assertCount(3, queue::jobs());

        queue::flush();

        $this->assertCount(0, queue::jobs());
    }

    /** @test */
    public function job_succeeds()
    {
        queue::define('test_job', function() {
            dir::make(queue::path() . DS . '.test');
        });

        $params = [
            'param1' => 'some data',
            'param2' => 'more data'
        ];

        queue::add('test_job', $params);

        // Now we have 1 job
        $this->assertCount(1, queue::jobs());

        // Get the first job ourselfs
        $job = yaml::read(queue::path() . DS .
            a::first(dir::read(queue::path())));

        // Check some things about the job
        $this->assertEquals(new Job($job), a::first(queue::jobs()));
        $this->assertEquals($job['name'], 'test_job');
        $this->assertEquals($job['data'], $params);

        queue::work();

        // Now we have 0 jobs
        $this->assertCount(0, queue::jobs());

        // And the job has been done
        $this->assertFileExists(queue::path() . DS . '.test');
    }

    /** @test */
    public function job_fails_on_false()
    {
        queue::define('test_job', function() {
            return false;
        });

        queue::add('test_job');

        // Now we have 1 job
        $this->assertCount(1, queue::jobs());

        queue::work();

        // Now we have 0 jobs and 1 failed job
        $this->assertCount(0, queue::jobs());
        $this->assertCount(1, queue::failedJobs());

        // Get the first job ourselfs
        $job = yaml::read(queue::failedPath() . DS .
            a::first(dir::read(queue::failedPath())));

        // Check some things about the job
        $this->assertEquals(new Job($job), a::first(queue::failedJobs()));
        $this->assertEquals($job['name'], 'test_job');
        $this->assertEquals($job['error'], 'Job returned false');
    }

    /** @test */
    public function job_fails_on_exception()
    {
        queue::define('test_job', function() {
            throw new Exception('Exception message');
        });

        queue::add('test_job');

        // Now we have 1 job
        $this->assertCount(1, queue::jobs());

        queue::work();

        // Now we have 0 jobs and 1 failed job
        $this->assertCount(0, queue::jobs());
        $this->assertCount(1, queue::failedJobs());

        // Get the first job ourselfs
        $job = yaml::read(queue::failedPath() . DS .
            a::first(dir::read(queue::failedPath())));

        // Check some things about the job
        $this->assertEquals(new Job($job), a::first(queue::failedJobs()));
        $this->assertEquals($job['name'], 'test_job');
        $this->assertEquals($job['error'], 'Exception message');
    }

    /** @test */
    public function job_fails_on_error()
    {
        queue::define('test_job', function() {
            throw new Error('Error message');
        });

        queue::add('test_job');

        // Now we have 1 job
        $this->assertCount(1, queue::jobs());

        queue::work();

        // Now we have 0 jobs and 1 failed job
        $this->assertCount(0, queue::jobs());
        $this->assertCount(1, queue::failedJobs());

        // Get the first job ourselfs
        $job = yaml::read(queue::failedPath() . DS .
            a::first(dir::read(queue::failedPath())));

        // Check some things about the job
        $this->assertEquals(new Job($job), a::first(queue::failedJobs()));
        $this->assertEquals($job['name'], 'test_job');
        $this->assertEquals($job['error'], 'Error message');
    }

    /** @test */
    public function job_fails_on_non_existent_action()
    {
        queue::add('job_without_action');

        // Now we have 1 job
        $this->assertCount(1, queue::jobs());

        queue::work();

        // Now we have 0 jobs and 1 failed job
        $this->assertCount(0, queue::jobs());
        $this->assertCount(1, queue::failedJobs());

        // Get the first job ourselfs
        $job = yaml::read(queue::failedPath() . DS .
            a::first(dir::read(queue::failedPath())));

        // Check some things about the job
        $this->assertEquals(new Job($job), a::first(queue::failedJobs()));
        $this->assertEquals($job['name'], 'job_without_action');
        $this->assertEquals($job['error'], 'Action \'job_without_action\' not defined');
    }
}
