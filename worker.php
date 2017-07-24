<?php
require(dirname(__DIR__) . DIRECTORY_SEPARATOR .
    '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR .
    'kirby' . DIRECTORY_SEPARATOR . 'bootstrap.php');

$kirby = kirby();
$kirby->plugin('queue-for-kirby');

if (!queue::hasJobs()) exit();

$site  = site();

$kirby->extensions();
$kirby->models();
$kirby->plugins();

while (queue::hasJobs()) {
    queue::work();
}