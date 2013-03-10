<?php

use Symfony\Component\ClassLoader\DebugClassLoader;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\HttpKernel\Debug\ExceptionHandler;

require_once __DIR__.'/vendor/autoload.php';

error_reporting(-1);
//DebugClassLoader::enable();
ErrorHandler::register();
ExceptionHandler::register();

$app = require __DIR__.'/src/app.php';
require __DIR__.'/config/dev.php';
require __DIR__.'/src/controllers.php';
$app->run();