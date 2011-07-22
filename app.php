<?php
require_once __DIR__.'/vendor/silex/silex.phar';
$app = new Silex\Application();

/* Konfigurieren */
$app->register(new Silex\Extension\TwigExtension(), array(
    'twig.path'		  => __DIR__.'/views',
    'twig.class_path' => __DIR__.'/vendor/twig'
));
$app['debug'] = true; // Debug-Modus

/* Zentrale Konstanten und Funktionen */
define('OPTION_NEWSLETTER', 1); // Option: Newsletter abbonieren (Binär: 01)
define('OPTION_NOTIFICATION', 2); // Option: Benachrichtigungen abbonieren (Binär: 10)
$convertToken = function($token) { // Teilt $token in $token['user'] und $token['mail'] auf
    $token = base64_decode($token);
    return split('&', $token);
};

/* Hauptseite mit Anmelde-Formular (und Admin-Login) */
$app->get('/', function () use ($app) {
    return $app['twig']->render('home.twig');
});

/* Registrierung (verschickt Bestätigungs-Mail) */
$app->post('/register', function () use ($app) {
    $request = $app['request'];
    $name = $request->get('name');
    $mail = $request->get('email');
    
    return "$name, du hast theoretisch Mail an $mail!";
});

/* Mail-Bestätigung (verschickt letzten Newsletter und nimmt in Datenbank auf) */
$app->get('/confirm/{options}/{token}', function ($options, $token) use ($app) {
    return "$name, du bist theoretisch registriert und hast Mail an $mail!";
})->convert('token', $convertToken);

/* Optionen: Newsletter bzw. Benachrichtigungen an- und abbestellen */
$app->get('/options/{token}', function ($token) use ($app) {
    return "Newsletter/Benachrichtigungen an/aus!";
})->convert('token', $convertToken);
/* Optionen speichern */
$app->post('/options/{token}', function ($token) use ($app) {
    $request = $app['request'];
    $newsletter = $request->get('newsletter');
    $notification = $request->get('notification');
    
    return "Newsletter ($newsletter) und Benachrichtigungen ($notification) theoretisch gespeichert!";
})->convert('token', $convertToken);

/* Twig-Test (Spielwiese) */
$app->get('/twigtest/{name}', function ($name) use ($app) {
    return $app['twig']->render('test.twig', array(
        'name'  => $name,
        'email' => $name.'@example.com'
    ));
});

/* Fehler */
$app->error(function() use ($app) {
    return "Nix gut.";
});

return $app;
?>