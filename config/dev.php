<?php

use Silex\Provider\MonologServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\HttpFragmentServiceProvider;

// include the prod configuration
require __DIR__.'/prod.php';

// enable the debug mode
$app['debug'] = true;

// send all mails to me
$app['swiftmailer.delivery_addresses'] = 'jannis@huffle-home.de';

$app->register(new MonologServiceProvider(), array(
  'monolog.logfile' => __DIR__.'/../dev.log',
));
$app->register(new ServiceControllerServiceProvider());
$app->register(new HttpFragmentServiceProvider());
$app->register(new WebProfilerServiceProvider(), array(
  'profiler.cache_dir' => __DIR__.'/../cache/profiler',
));
