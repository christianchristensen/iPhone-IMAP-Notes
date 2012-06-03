<?php
define('CONS_KEY', 'deadbeef');
define('CONS_SECRET', 'deadbeef');

require_once __DIR__.'/vendor/autoload.php';

$app = new Silex\Application();

// Debug helper provided by Silex
$app['debug'] = TRUE;
// TODO:
//  Search messages
//  If a notes folder doesn't exist create it to continue...
//  OAuth with gmail (limit surface area)
//  Icon (white) on black navbar top

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

$app->get('/login', function () use ($app) {
    $app['session']->start();
    // check if the user is already logged-in
    if (null !== ($username = $app['session']->get('user'))) {
        return $app->redirect('/');
    }

    $oauth = new HTTP_OAuth_Consumer(CONS_KEY, CONS_SECRET);
    $oauth->accept(new HTTP_Request2(NULL, NULL, array(
            'ssl_cafile' => 'assets/mozilla.pem',
    )));
    $oauth->getRequestToken('https://www.google.com/accounts/OAuthGetRequestToken');
    $oauth_token = $oauth->getToken();
    $oauth_token_secret = $oauth->getTokenSecret();

    $app['session']->set('access_token', $oauth_token);
    $app['session']->set('access_secret', $oauth_token_secret);

    return $app->redirect('https://www.google.com/accounts/OAuthAuthorizeToken?oauth_token=' . $oauth_token);
});

$app->get('/auth', function() use ($app) {
    $app['session']->start();
    // check if the user is already logged-in or we're already auth
    if ((null !== $app['session']->get('username')) || (null !== $app['session']->get('auth_secret'))) {
        return $app->redirect('/');
    }

    $oauth_token = $app['session']->get('access_token');
    $secret = $app['session']->get('access_secret');
    if ($oauth_token == null) {
        $app->abort(400, 'Invalid token');
    }

    $oauth = new HTTP_OAuth_Consumer(CONS_KEY, CONS_SECRET);
    $oauth->accept(new HTTP_Request2(NULL, NULL, array(
            'ssl_cafile' => 'assets/mozilla.pem',
    )));
    $oauth->setToken($oauth_token);
    $oauth->setTokenSecret($secret);
    try {
        $oauth->getAccessToken('https://www.google.com/accounts/OAuthGetAccessToken');
    } catch (OAuthException $e) {
        $app->abort(401, $e->getMessage());
    }

    // Set authorized token details for subsequent requests
    $app['session']->set('auth_token', $oauth->getToken());
    $app['session']->set('auth_secret', $oauth->getTokenSecret());

    return $app->redirect('/req');
});

$app->get('/req', function () use ($app) {
    $app['session']->start();
    $token = $app['session']->get('auth_token');
    // check if we have our auth keys
    if (null === ($secret = $app['session']->get('auth_secret'))) {
        return $app->redirect('/');
    }
    $oauth = new HTTP_OAuth_Consumer(CONS_KEY, CONS_SECRET);
    $oauth->accept(new HTTP_Request2(NULL, NULL, array(
            'ssl_cafile' => 'assets/mozilla.pem',
    )));
    $oauth->setToken($token);
    $oauth->setTokenSecret($secret);
    // TODO: Push this upstream to SDK lib
    print_r('WE MADE IT!!');
//     $oauth->sendRequest('https://www.allplayers.com/?q=api/v1/rest/groups/54395c18-f611-11e0-a44b-12313d04fc0f.json', array(), 'GET');
//     $response = $oauth->getLastResponse();
//     $json = json_decode($response->getResponse()->getBody());

//     // HACK: set username to group UUID (eventually move this to users/current.json)
//     $app['session']->set('username', $json->uuid);
//     return $app->redirect('/');
});

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
    // $notes = new Notes('mail.messagingengine.com', 'minenet@airpost.net', 'P@ssw0rd', 'INBOX');
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