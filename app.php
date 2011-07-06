<?php
require_once __DIR__.'/vendor/silex/silex.phar';
$app = new Silex\Application();

// Konfigurieren
$app->register(new Silex\Extension\TwigExtension(), array(
    'twig.path'		  => __DIR__.'/views',
    'twig.class_path' => __DIR__.'/vendor/twig'
));

$app->get('/', function () {
    return "Huffle-Newsletter!";
});

$app->get('/twigtest/{name}', function ($name) use ($app) {
    return $app['twig']->render('test.twig', array(
        'name'  => $name,
        'email' => $name.'@example.com'
    ));
});

return $app;
?>