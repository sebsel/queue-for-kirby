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
        $job = $jobs->nth(0);
        $this->assertEquals('test_job', $job->name());
        $this->assertEquals([
            'param' => 'some data'
        ], $job->data());
        $this->assertEquals('some data', $job->get('param'));

        // Test second job of queue
        $job = $jobs->nth(1);
        $this->assertEquals('another_job', $job->name());
        $this->assertEquals(null, $job->data());
        $this->assertEquals(null, $job->get('param'));

        // Note that the code above does not remove the jobs
        // or change the queue at all!
        // Use queue::work() for that
    }

    /** @test */
    public function Queue_retry__adds_to_queue()
    {
        queue::define('job_to_fail', function() {
            return false;
        });

        queue::add('job_to_fail');
        queue::work();

        // We now have 0 jobs and 1 failed job
        $this->assertCount(0, queue::jobs());
        $this->assertCount(1, queue::failedJobs());

        // Take the Job and retry
        $failedJob = queue::failedJobs()->first();
        queue::retry($failedJob);

        // The job has been added to the queue again
        $this->assertCount(1, queue::jobs());
        $this->assertCount(0, queue::failedJobs());

        // Job will still fail
        queue::work();

        // The job has failed
        $this->assertCount(0, queue::jobs());
        $this->assertCount(1, queue::failedJobs());

        // Take the Job and retry by ID
        $failedJob = queue::failedJobs()->first();
        $id = $failedJob->id();

        // The ID is a string
        $this->assertInternalType('string', $id);

        queue::retry($id);

        // And the job is back in the queue again
        $this->assertCount(1, queue::jobs());
        $this->assertCount(0, queue::failedJobs());
    }

    /**
     * @test
     * @expectedException Error
     * @expectedExceptionMessage Job not found
     */
    public function Queue_retry__throws_error_on_wrong_id()
    {
        queue::add('job_to_fail');
        queue::work();

        // Take the first ID and add rubbish
        $id = queue::failedJobs()->first()->id();
        $id = $id . 'asdasd';

        queue::retry($id);
    }

    /**
     * @test
     * @expectedException Error
     * @expectedExceptionMessage queue::retry() expects a Job object
     */
    public function Queue_retry__throws_error_on_non_job()
    {
        queue::add('job_to_fail');
        queue::work();

        queue::retry(new HTML());
    }

    /** @test */
    public function Queue_remove__removes_failed_jobs()
    {
        queue::add('job_to_fail');
        queue::add('another_job_that_fails');
        queue::work();
        queue::work();

        // We now have 0 jobs and 2 failed job
        $this->assertCount(0, queue::jobs());
        $this->assertCount(2, queue::failedJobs());

        // Take the Job, check if it's the one, and remove it
        $failedJob = queue::failedJobs()->first();
        $this->assertEquals('job_to_fail', $failedJob->name());
        queue::remove($failedJob);

        // We have only one failed job now
        $this->assertCount(0, queue::jobs());
        $this->assertCount(1, queue::failedJobs());

        // The remaining job is the other job
        $failedJob = queue::failedJobs()->first();
        $this->assertEquals('another_job_that_fails', $failedJob->name());

        // Remove by ID
        queue::remove($failedJob->id());

        // All the jobs are gone
        $this->assertCount(0, queue::jobs());
        $this->assertCount(0, queue::failedJobs());
    }

    /**
     * @test
     * @expectedException Error
     * @expectedExceptionMessage Job not found
     */
    public function Queue_remove__throws_error_on_wrong_id()
    {
        queue::add('job_to_fail');
        queue::work();

        // Take the first ID and add rubbish
        $id = queue::failedJobs()->first()->id();
        $id = $id . 'asdasd';

        queue::remove($id);
    }

    /**
     * @test
     * @expectedException Error
     * @expectedExceptionMessage queue::remove() expects a Job object
     */
    public function Queue_remove__throws_error_on_non_job()
    {
        queue::add('job_to_fail');
        queue::work();

        queue::remove(new Silo());
    }

    /** @test */
    public function Queue_flush__removes_all_jobs()
    {
        $this->assertCount(0, queue::jobs());
        $this->assertFalse(queue::hasJobs());

        queue::add('test_job');
        queue::add('test_job');
        queue::add('test_job');

        $this->assertCount(3, queue::jobs());
        $this->assertTrue(queue::hasJobs());

        queue::flush();

        $this->assertCount(0, queue::jobs());
        $this->assertFalse(queue::hasJobs());
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
        $this->assertEquals(
            new Job($job),
            queue::jobs()->first()
        );
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
        $this->assertEquals(
            new Job($job),
            queue::failedJobs()->first()
        );
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
        $this->assertEquals(
            new Job($job),
            queue::failedJobs()->first()
        );
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
        $this->assertEquals(
            new Job($job),
            queue::failedJobs()->first()
        );
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
        $this->assertEquals(
            new Job($job),
            queue::failedJobs()->first()
        );
        $this->assertEquals($job['name'], 'job_without_action');
        $this->assertEquals($job['error'], 'Action \'job_without_action\' not defined');
    }

    /** @test */
    public function job_fails_on_teminating_process()
    {
        // # This one is impossible to test via PHPUnit, because we
        // # exit the PHP-unit process as well. To test this function,
        // # uncomment it, run the test it, and see the contents of
        // # the `site/queue/.failed` folder yourself.
        //
        // # Assert for yourself that:
        // #   $job['name'] == 'teminating_job'
        // #   $job['error'] == 'Job action terminated execution'
        //
        // # After that, remove the file from the queue.
        //
        // queue::define('teminating_job', function() {
        //     die();
        // });
        // queue::add('teminating_job');
        // queue::work();
    }

}
