<?php

use Dotenv\Dotenv;

require_once 'vendor/autoload.php';
$env = Dotenv::createImmutable(__DIR__);
$env->load();
