<?php

require_once __DIR__.'/vendor/autoload.php';

$notes = new Notes('minenet@airpost.net', 'P@ssw0rd', 'INBOX');
// TODO: Move these to tests
//$debug = $notes->index();
$debug = $notes->create("123Hello There\n\n\n World One \n Two Three");
//$debug = $notes->create("uh oh");
print_r($debug);
