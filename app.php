<?php
require_once __DIR__.'/conf.php'; // Konfigurationsvariablen
require_once __DIR__.'/vendor/silex/silex.phar'; // Silex: Haupt-Framework
require_once __DIR__.'/vendor/swift/swift_required.php'; // Swift: Mail-Library
$app = new Silex\Application();

/* Konfigurieren */
$app->register(new Silex\Extension\TwigExtension(), array( // Twig: Template-Framework
    'twig.path'		  => __DIR__.'/views',
    'twig.class_path' => __DIR__.'/vendor/twig'
));
$app->register(new Silex\Extension\SessionExtension()); // Session: PHP-Sessions
$app['debug'] = true; // Debug-Modus

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
    $abo = $request->get('abo');
    if(!isset($abo['newsletter'])) $abo['newsletter'] = false;
    if(!isset($abo['notifications'])) $abo['notifications'] = false;
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
$app->get('/register', function () use ($app) {
    $name = $app['session']->get('name');
    $email = $app['session']->get('email');
    $abo = $app['session']->get('abo');
    $newsletter = $abo['newsletter'] ? 0 : 1;
    $notifications = $abo['notifications'] ? 0 : 1;
    $token = base64_encode("$name,$email,$newsletter,$notifications");
    
    $textemail = $app['twig']->render('confirmemail.twig', array(
    	'name' => $name,
    	'token' => $token,
    	'html' => false
    ));
    $htmlemail = $app['twig']->render('confirmemail.twig', array(
    	'name' => $name,
    	'token' => $token,
    	'html' => true
    ));
    
    $email = Swift_Message::newInstance("E-Mail Bestätigung für Hufflepuff-News");
    $email->setFrom(array($smtpMail => $smtpName));
    $email->setTo(array($email => $name));
    $email->setBody($htmlemail, 'text/html');
    $email->addPart($textemail, 'text/plain');
    
    $transport = Swift_SmtpTransport::newInstance($smtpServer, $smtpPort);
    $transport->setUsername($smtpUser);
    $transport->setPassword($smtpPassword);
    $mailer = Swift_Mailer::newInstance($transport);
    $mailer->send($email);
    
    return $app['twig']->render('register.twig', array(
        'name' => $name
    ));
});

/* Mail-Bestätigung (verschickt letzten Newsletter und nimmt in Datenbank auf) */
$app->get('/confirm/{options}/{token}', function ($options, $token) use ($app) {
    return "$name, du bist theoretisch registriert und hast Mail an $mail!";
});

/* Optionen: Newsletter bzw. Benachrichtigungen an- und abbestellen */
$app->get('/options/{token}', function ($token) use ($app) {
    return "Newsletter/Benachrichtigungen an/aus!";
});
/* Optionen speichern */
$app->post('/options/{token}', function ($token) use ($app) {
    $request = $app['request'];
    $newsletter = $request->get('newsletter');
    $notification = $request->get('notification');
    
    return "Newsletter ($newsletter) und Benachrichtigungen ($notification) theoretisch gespeichert!";
});

return $app;
?>