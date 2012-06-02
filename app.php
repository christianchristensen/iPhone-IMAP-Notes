<?php

require_once __DIR__.'/vendor/autoload.php';

$notes = new Notes('minenet@airpost.net', 'P@ssw0rd', 'INBOX');
$debug = $notes->index();
print_r($debug);
