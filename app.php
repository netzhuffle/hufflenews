<?php
require_once __DIR__.'/vendor/silex/silex.phar';
$app = new Silex\Application();

/* Einstellungen */
$newpassword = 'Diptamessenz'; // Aktuelles Hauspasswort
$oldpasswords = array('Schwan', 'Schwein', 'Schwubb'); // Die letzten Hauspasswörter
$newsletterpassword = 'Newsletter'; // Passwort um Newsletter zu schreiben
$notificationspassword = 'Notifications'; // Passwort um Benachrichtigungen zu schreiben
$usereditpassword = 'Blubber'; // Passwort um Empfänger zu löschen oder auf Nur-Text umzustellen

/* Konfigurieren */
$app->register(new Silex\Extension\TwigExtension(), array(
    'twig.path'		  => __DIR__.'/views',
    'twig.class_path' => __DIR__.'/vendor/twig'
));
$app->register(new Silex\Extension\SessionExtension());
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
    $errors = $app['session']->has('errors') ? $app['session']->get('errors') : array( // Fehler beim letzten Versuch
        'name' => false,
        'email' => false,
        'abo' => false,
        'password' => false,
        'oldpassword' => false
    );
    $lasttry = $app['session']->has('lasttry') ? $app['session']->get('lasttry') : array( // Werte vom letzten Versuch
        'name' => '',
        'email' => '',
        'abo' => array('newsletter' => false, 'notifications' => false),
        'password' => ''
    );
    return $app['twig']->render('home.twig', array(
        'error' => $errors,
        'lasttry' => $lasttry
    ));
});

/* Prüft die Formular-Angaben und leitet an den richtigen Pfad weiter */
$app->post('/check', function () use ($app, $newsletterpassword, $notificationspassword, $usereditpassword, $newpassword, $oldpasswords) {
    $request = $app['request'];
    $name = $request->get('name');
    $email = $request->get('email');
    $abo = array(
    	'newsletter' => $request->get('newsletter'),
        'notifications' => $request->get('notifications')
    );
    $password = $request->get('password');
    
    /* Admin-Bereiche */
    if($password === $newsletterpassword) {
        $app['session']->set('admin', 'newsletter');
        return $app->redirect($request->getUriForPath('/admin'));
    } elseif($password === $notificationspassword) {
        $app['session']->set('admin', 'notifications');
        return $app->redirect($request->getUriForPath('/admin'));
    } elseif($password === $usereditpassword) {
        $app['session']->set('admin', 'useredit');
        return $app->redirect($request->getUriForPath('/useredit'));
    /* Registrierung */
    } else {
        $errors = array( // Prüfen auf Fehler
            'name' => !(trim($name)),
            'email' => !(trim($email)),
            'abo' => !($abo['newsletter'] || $abo['notifications']),
            'password' => $password !== $newpassword,
            'oldpassword' => in_array($password, $oldpasswords)
        );
        if(!($errors['name'] || $errors['email'] || $errors['abo'] || $errors['password'])) { // Falls kein Fehler
            $app['session']->set('name', $name);
            $app['session']->set('email', $email);
            $app['session']->set('abo', $abo);
            return $app->redirect($request->getUriForPath('/register'));
        } else { // Falls Fehler
            $lasttry = array(
                'name' => $name,
                'email' => $email,
                'abo' => array('newsletter' => $abo['newsletter'], 'notifications' => $abo['notifications']),
                'password' => $password
            );
            $app['session']->set('errors', $errors);
            $app['session']->set('lasttry', $lasttry);
            return $app->redirect($request->getUriForPath('/'));
        }
    }
});

/* Registrierung (verschickt Bestätigungs-Mail) */
$app->post('/register', function () use ($app) {
    $request = $app['request'];
    $name = $request->get('name');
    $mail = $request->get('email');
    $abo = array(
    	'newsletter' => $request->get('abo.newsletter'),
        'notifications' => $request->get('abo.notifications')
    );
    $password = $request->get('password');
    
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

/* Fehler * /
$app->error(function() use ($app) {
    return "Nix gut.";
});
*/

return $app;
?>