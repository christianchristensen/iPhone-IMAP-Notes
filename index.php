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
    $app['session']->start();
    $username = $app['session']->get('user');
    if (!empty($username)) {
        return $app->redirect('/note');
    }
    // if session just redirect to /note
    return $app['twig']->render('index.twig', array());
});

$app->match('/advauth', function(Symfony\Component\HttpFoundation\Request $request) use($app) {
    $host = $request->get('host');
    $user = $request->get('user');
    $pass = $request->get('pass');
    $port = $request->get('port');
    $ssl = (bool) $request->get('ssl') == 'on';
    $basefolder = $request->get('basefolder');

    if (!empty($host) && !empty($user)) {
        $app['session']->start();
        $app['session']->set('host', $host);
        $app['session']->set('user', $user);
        $app['session']->set('pass', $pass);
        $app['session']->set('port', (int) $port);
        $app['session']->set('ssl', (bool) $ssl);
        $app['session']->set('basefolder', empty($basefolder) ? NULL : $basefolder);
        $notes = notesSessionMgr($app);
        return $app->redirect('/note');
    }

    return $app['twig']->render('advauth.twig', array());
})->method('GET|POST');

function notesSessionMgr($app) {
    $app['session']->start();
    $host = $app['session']->get('host');
    $user = $app['session']->get('user');
    $pass = $app['session']->get('pass');
    $port = (int) $app['session']->get('port');
    $ssl = (bool) $app['session']->get('ssl');
    $basefolder = $app['session']->get('basefolder');
    $basefolder = empty($basefolder) ? NULL : $basefolder;
    try {
        $notes = new Notes($host, $user, $pass, $basefolder, $port, $ssl);
        $notes->count();
        return $notes;
    }
    catch (Exception $e) {
        $app['session']->clear();
        //return $app->abort(300, 'Uh Oh - going back home', array("Location" => "http://hackmw.dev/"));
        return $app->redirect('/');
    }
}

// ////////// Note app
$app->get('/note', function() use($app) {
    $notes = notesSessionMgr($app);
    if (strpos(get_class($notes), 'Notes') === FALSE) return $app->redirect('/');

    // TODO: Move these to tests
    $index = $notes->index();

    return $app['twig']->render('note.twig', array(
      'index' => array_reverse($index),
    ));
});

$app->get('/note/{id}', function($id) use($app) {
    $notes = notesSessionMgr($app);
    if (strpos(get_class($notes), 'Notes') === FALSE) return $app->redirect('/');
    $add = FALSE;
    // TODO: Move these to tests
    if (strpos($id, 'add') !== FALSE) {
        $message = array(
          'num' => '',
          'uuid' => 'add',
          'subject' => '',
          'body' => '',
        );
        $add = TRUE;
    }
    else {
        $message = $notes->retrieve($id);
    }

    // TODO: Figure out a better way to process these new lines
    $body = $message['body'];
    $body = str_replace('<br>', "\n", $body);
    $body = str_replace('<div>', '', $body);
    $body = str_replace('</div>', "\n", $body);
    return $app['twig']->render('edit.twig', array(
        'num' => $message['num'],
        'uuid' => $message['uuid'],
        'subject' => $message['subject'],
        'body' => $body,
        'delete' => !$add,
    ));
});

$app->post('/note/{id}', function(Symfony\Component\HttpFoundation\Request $request, $id) use($app) {
    $notes = notesSessionMgr($app);
    if (strpos(get_class($notes), 'Notes') === FALSE) return $app->redirect('/');
    $save = $request->get('save');
    $delete = $request->get('delete');
    if (!empty($save)) {
        $body = $request->get('body');
        if (strpos($id, 'add') !== FALSE) {
            $id = $notes->create($body);
        }
        else {
            $id = $notes->update($id, $body);
        }
    }
    elseif (!empty($delete)) {
        // TODO: DRY this up
        $notes->delete($id);
        return $app->redirect('/note');
    }
    return $app->redirect('/note/'.$id);
});

$app->delete('/note/{id}', function($id) use($app) {
    $notes = notesSessionMgr($app);
    if (strpos(get_class($notes), 'Notes') === FALSE) return $app->redirect('/');
    $notes->delete($id);
    return new Symfony\Component\HttpFoundation\Response('Deleted', 201);
});

$app->run();
