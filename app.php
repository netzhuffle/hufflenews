<?php
require_once __DIR__.'/conf.php'; // Konfigurationsvariablen
require_once __DIR__.'/vendor/silex/silex.phar'; // Silex: Haupt-Framework
require_once __DIR__.'/vendor/swift/swift_required.php'; // Swift: Mail-Library
$app = new Silex\Application();

/* Konfigurieren */
$app['debug'] = $debug; // Debug-Modus
$twigOptions = array('debug' => $debug); // Twig-Optionen
if($cacheTemplates) {
    $twigOptions['cache'] = $cacheTemplatesPath;
}
$app->register(new Silex\Extension\TwigExtension(), array( // Twig: Template-Framework
    'twig.path'        => __DIR__.'/views',
    'twig.class_path'  => __DIR__.'/vendor/twig',
    'twig.options'     => $twigOptions
));
function stripWhitespace($string) { // Funktion für Twig-Filter um alle Whitespace-Zeichen eines Strings zu entfernen
    $string = preg_replace('/\s/', '', $string);
    $string = str_replace('ComicSansMS', 'Comic Sans MS', $string); // 'Comic Sans MS' wieder zurück korrigieren
    return $string;
}
$app['twig']->addFilter('stripwhitespace', new Twig_Filter_Function('stripWhitespace')); // Filter in Twig registrieren
$app->register(new Silex\Extension\SessionExtension()); // Session: PHP-Sessions

/* Zentrale Funktionen */
$db; // Datenbank-Verbindung (Instanz von PDO)
function getDB() { // Stellt eine Verbindung zur Datenbank her und gibt sie zurück
    global $db, $dbDSN, $dbUser, $dbPassword;
    if(!isset($db)) {
        $db = new PDO($dbDSN, $dbUser, $dbPassword);
    }
    return $db;
}

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
$app->get('/register', function () use ($app, $smtpMail, $smtpName, $smtpServer, $smtpPort, $smtpUser, $smtpPassword) {
    $name = $app['session']->get('name');
    $email = $app['session']->get('email');
    $abo = $app['session']->get('abo');
    $newsletter = $abo['newsletter'] ? 1 : 0;
    $notifications = $abo['notifications'] ? 1 : 0;
    $token = base64_encode("$name,$email,$newsletter,$notifications");
    
    $emailtemplate = $app['twig']->loadTemplate('confirmemail.twig');
    $htmlemail = $emailtemplate->render(array(
    	'name' => $name,
    	'token' => $token,
        'newsletter' => $newsletter,
    	'html' => true
    ));
    $textemail = $emailtemplate->render(array(
    	'name' => $name,
    	'token' => $token,
        'newsletter' => $newsletter,
    	'html' => false
    ));
    
    $message = Swift_Message::newInstance("E-Mail Bestätigung für Hufflepuff-News");
    $message->setFrom(array($smtpMail => $smtpName));
    $message->setTo(array($email => $name));
    $message->setBody($htmlemail, 'text/html');
    $message->addPart($textemail, 'text/plain');
    
    $transport = Swift_SmtpTransport::newInstance($smtpServer, $smtpPort);
    $transport->setUsername($smtpUser);
    $transport->setPassword($smtpPassword);
    $mailer = Swift_Mailer::newInstance($transport);
    $success = $mailer->send($message);
    
    return $app['twig']->render('register.twig', array(
        'name' => $name,
        'success' => $success
    ));
});

/* Registrierungsmailvorschau */
$app->get('/register/preview/{html}', function ($html) use ($app) {
    return $app['twig']->render('confirmemail.twig', array(
    	'name' => "Fetter Mönch",
    	'token' => "abcdef",
    	'html' => $html
    ));
});

/* Mail-Bestätigung (verschickt letzten Newsletter und nimmt in Datenbank auf) */
$app->get('/confirm/{token}', function ($token) use ($app) {
    return $app['twig']->render('confirm.twig', array(
    	'name' => "Emilia",
    	'lastnumber' => "29",
    	'lastdate' => "31.06."
    ));
});

/* Optionen: Newsletter bzw. Benachrichtigungen an- und abbestellen */
$app->get('/options/{token}', function ($token) use ($app) {
    return $app['twig']->render('options.twig', array(
    	'error' => array('name' => false, 'email' => false),
    	'abo' => array('newsletter' => true, 'notifications' => true),
    	'name' => "Emilia",
    	'email' => "emilia@example.com",
    	'admin' => true
    ));
});

/* Optionen speichern */
$app->post('/saveoptions', function ($token) use ($app) {
    $request = $app['request'];
    $newsletter = $request->get('newsletter');
    $notification = $request->get('notification');
    
    return $app['twig']->render('saveoptions.twig', array(
    	'name' => "Emilia",
    	'deleted' => false,
    	'admin' => false,
    	'emailchanged' => true
    ));
});

/* Admin-Seite: Verfassen von Newslettern bzw. Benachrichtigungen */
$app->get('/admin', function () use ($app) {
    return $app['twig']->render('admin.twig', array(
    	'newsletter' => false
    ));
});

/* Preview: Vorschau der E-Mail */
$app->get('/admin/preview', function () use ($app) {
    return $app['twig']->render('preview.twig', array(
    	'text' => array('html' => "Hallo HTML!", 'text' => "Hallo Text!")
    ));
});

/* Versenden */
$app->get('/admin/send', function () use ($app) {
    return $app['twig']->render('send.twig', array(
    	'anzahl' => 59
    ));
});

/* Edit-Users: Bearbeiten von Benutzern */
$app->get('/admin/editusers', function () use ($app) {
    return $app['twig']->render('editusers.twig', array(
    	'users' => array(
    		array('name' => "Emilia", 'email' => "emilia@example.com", 'token' => "12345"),
    		array('name' => "JANNiS", 'email' => "jannis@example.com", 'token' => "12354"),
    		array('name' => "User3", 'email' => "user@example.com", 'token' => "54321")
    	)
    ));
});

return $app;
?>