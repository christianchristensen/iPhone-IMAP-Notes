<?php

require_once __DIR__.'/vendor/autoload.php';

$notes = new Notes('minenet@airpost.net', 'P@ssw0rd');
print_r($notes->get());
