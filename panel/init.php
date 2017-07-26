<?php

load([
    'queue\\controller' => __DIR__ . DS . 'controller.php'
]);

$kirby->set('widget', 'failedjobs', __DIR__ . DS . 'widget');

panel()->routes([
    [
        'pattern' => 'queue/retry/(:any)',
        'filter' => 'auth',
        'action' => 'Queue\Controller::retry'
    ],
    [
        'pattern' => 'queue/remove/(:any)',
        'filter' => 'auth',
        'action' => 'Queue\Controller::remove'
    ],
    [
        'pattern' => 'queue/flush',
        'filter' => 'auth',
        'action' => 'Queue\Controller::flush'
    ],
    [
        'pattern' => 'queue/work',
        'filter' => 'auth',
        'action' => 'Queue\Controller::work'
    ]
]);
