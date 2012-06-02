<?php

require_once __DIR__.'/vendor/autoload.php';

$app = new Silex\Application();

// Debug helper provided by Silex
$app['debug'] = TRUE;

// register the session extension
$app->register(new Silex\Provider\SessionServiceProvider());

$app->get('/', function() use($app) {
    print_r('Hello, World!');
});

$app->get('/email', function() use($app) {
    $app['session']->start();
    $notes = new Notes('mail.messagingengine.com', 'minenet@airpost.net', 'P@ssw0rd', 'INBOX');
    // TODO: Move these to tests
    $debug = $notes->index();
    //$debug = $notes->create(rand(0, 1000) . "Hello There\n\n\n World One \n Two Three");
    //$debug = $notes->create("uh oh");
    //$debug = $notes->retrieve('4adb0460-acf5-11e1-81f0-7f6c2cc069f2');
    //$debug = $notes->retrieve(2);
    //$debug = $debug = $notes->delete('410e98c0-acf5-11e1-a4c8-17f957629924');
    print_r($debug);
});

$app->run();
