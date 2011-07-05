<?php
require_once __DIR__.'/vendor/silex/silex.phar';

$app = new Silex\Application();

$app->get('/', function() {
    return "Huffle-Newsletter!";
});

$app->get('/twigtest', function() {
});

return $app;
?>