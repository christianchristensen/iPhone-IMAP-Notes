<?php

require_once __DIR__.'/vendor/autoload.php';

$app = new Silex\Application();

// Debug helper provided by Silex
$app['debug'] = TRUE;

// register the session extension
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

$app->get('/', function() use($app) {
    return $app['twig']->render('index.twig', array());
});

$app->get('/note', function() use($app) {
    $notes = new Notes('mail.messagingengine.com', 'minenet@airpost.net', 'P@ssw0rd', 'INBOX');
    // TODO: Move these to tests
    $index = $notes->index();

    return $app['twig']->render('note.twig', array(
      'index' => $index,
    ));
});

$app->get('/note/{id}', function($id) use($app) {
    $notes = new Notes('mail.messagingengine.com', 'minenet@airpost.net', 'P@ssw0rd', 'INBOX');
    // TODO: Move these to tests
    $message = $notes->retrieve($id);

    return $app['twig']->render('edit.twig', array(
        'num' => $message['num'],
        'uuid' => $message['uuid'],
        'subject' => $message['subject'],
        'body' => $message['body'],
    ));

});

$app->post('/note/{id}', function($id) use($app) {

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
