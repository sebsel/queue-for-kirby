<?php
require(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR .
    'kirby' . DIRECTORY_SEPARATOR . 'bootstrap.php');

$kirby = kirby();
include_once(__DIR__ . DS . 'queue-for-kirby.php');

if (!queue::hasJobs()) exit();

$site  = site();

$kirby->extensions();
$kirby->models();
$kirby->plugins();

while (queue::hasJobs()) {
    queue::work();
}