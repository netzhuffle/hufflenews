<?php
require_once __DIR__.'/conf.php'; // Konfigurationsvariablen
require_once __DIR__.'/vendor/silex/silex.phar'; // Silex: Haupt-Framework
require_once __DIR__.'/vendor/swift/swift_required.php'; // Swift: Mail-Library

use Silex\Application;

$app = new Application();

/* Konfigurieren */
$app['debug'] = $debug; // Debug-Modus
$twigOptions = array( // Twig-Optionen
    'twig.path'        => __DIR__.'/views',
    'twig.class_path'  => __DIR__.'/vendor/twig'
);
if($cacheTemplates) {
    $twigOptions['twig.options'] = array('cache' => $cacheTemplatesPath);
}
$app->register(new Silex\Extension\TwigExtension(), $twigOptions); // Twig: Template-Framework
/**
 * Funktion für Twig-Filter um alle Whitespace-Zeichen eines Strings zu entfernen
 * @param string $string der String
 * @return String ohne Whitespaces
 */
function stripWhitespaceFilter($string) {
    $string = preg_replace('/\s/', '', $string);
    $string = str_replace('ComicSansMS', 'Comic Sans MS', $string); // 'Comic Sans MS' wieder zurück korrigieren
    return $string;
}
$app['twig']->addFilter('stripwhitespace', new Twig_Filter_Function('stripWhitespaceFilter')); // Filter in Twig registrieren
$app->register(new Silex\Extension\SessionExtension()); // Session: PHP-Sessions

/* Zentrale Funktionen */
$db; // Datenbank-Verbindung (Instanz von PDO)
/**
 * Stellt eine Verbindung zur Datenbank her und gibt sie zurück
 * @return PDO
 */
function getDB() {
    global $db, $dbDSN, $dbUser, $dbPassword;
    if(!isset($db)) {
        $db = new PDO($dbDSN, $dbUser, $dbPassword);
    }
    return $db;
}
/**
 * Erstellt aus dem Originaltext einen versandbereiten Text
 * Ersetzt {{USER}} durch den User-Namen
 * Nutzt bei [[x|y]] bei HTML-Mails x und bei Text-Mails y
 * Entfernt alle HTML-Tags aus Text-Mails
 * Ändert Zeilenumbrüche in &lt;br&gt;s in HTML-Mails
 * @param sting $newsletter Newsletter-Originaltext
 * @param string $name der Name des Empfängers
 * @param bool $html Ob HTML (bei false Text)
 * @return Resultierender Text
 */
function createEmailText($text, $name, $html) {
    $text = str_replace("{{USER}}", $name, $text);
    if($html) {
        $text = nl2br(preg_replace("/\[\[(.*)\|\|.*\]\]/isU", "$1", $text), false);
    } else {
        $text = strip_tags(preg_replace("/\[\[.*\|\|(.*)\]\]/isU", "$1", $text));
    }
    return $text;
}
/**
 * Verschickt eine E-Mail
 * @param string $subject Betreff
 * @param array $to Empfänger: array mit Keys = E-Mail und Values = Name; z.B. array("max@example.com" => "Max", "lisa@example.org" => "Lisa")
 * @param string $text Inhalt als Text
 * @param string $html Inhalt als HTML
 * @return Anzahl der erfolgreichen Empfängern
 */
function sendMail($subject, $to, $text, $html) {
    global $smtpMail, $smtpName, $smtpServer, $smtpPort, $smtpUser, $smtpPassword;
    $successful = 0;
    $message = \Swift_Message::newInstance($subject);
    $message->setFrom(array($smtpMail => $smtpName));

    foreach($to as $email => $name) {
        $message->setTo(array($email => $name));
        $message->setBody(createEmailText($html, $name, true), 'text/html');
        $message->addPart(createEmailText($text, $name, false), 'text/plain');

        $transport = \Swift_SmtpTransport::newInstance($smtpServer, $smtpPort);
        $transport->setUsername($smtpUser);
        $transport->setPassword($smtpPassword);
        $mailer = \Swift_Mailer::newInstance($transport);

        $successful += $mailer->send($message);
    }

    return $successful;
}

/* Hauptseite mit Anmelde-Formular (und Admin-Login) */
$app->get('/', function (Application $app) {
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
$app->post('/check', function (Application $app) use ($newsletterpassword, $notificationspassword, $usereditpassword, $newpassword, $oldpasswords) {
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
$app->get('/register', function (Application $app) {
    $name = $app['session']->get('name');
    $email = $app['session']->get('email');
    $abo = $app['session']->get('abo');
    $newsletter = $abo['newsletter'] ? 1 : 0;
    $notifications = $abo['notifications'] ? 1 : 0;
    $type = $newsletter + 2 * $notifications;
    $token = base64_encode("$name,$email,$type");
    $app['session']->remove('lasttry');
    $app['session']->remove('errors');

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
    $successful = sendMail("E-Mail-Bestätigung für Hufflepuff-News", array($email => $name), $textemail, $htmlemail);

    return $app['twig']->render('register.twig', array(
        'name' => $name,
        'success' => !!$successful
    ));
});

/* Mail-Bestätigung (verschickt letzten Newsletter und nimmt in Datenbank auf) */
$app->get('/confirm/{token}', function (Application $app, $token) use ($dbTablePrefix) {
    $tokenParts = explode(",", base64_decode($token));
    if(count($tokenParts) != 3) {
        $app->abort(404, "Fehler: Token ungültig");
    } else {
        list($name, $email, $type) = $tokenParts;

        $db = getDB();
        /* In die Abonnentenliste eintragen */
        $stmt = $db->prepare("INSERT INTO {$dbTablePrefix}members (name, mail, registerdate, type) VALUES (?, ?, now(), ?)");
        $stmt->execute(array($name, $email, $type));
        /* Letzten Newsletter aus Datenbank abrufen */
        $stmt = $db->prepare("SELECT id, date, text FROM {$dbTablePrefix}news ORDER BY date DESC LIMIT 1");
        $stmt->execute();
        list($id, $date, $text) = $stmt->fetch(PDO::FETCH_NUM);

        /* Newsletter verschicken */
        $emailtemplate = $app['twig']->loadTemplate('sendemail.twig');
        $token = base64_encode($email);
        $htmlemail = $emailtemplate->render(array(
            'text' => $text,
        	'token' => $token,
        	'html' => true
        ));
        $textemail = $emailtemplate->render(array(
            'text' => $text,
        	'token' => $token,
        	'html' => false
        ));
        echo $textemail;
        sendMail("Hufflepuff-Newsletter #$id", array($email => $name), $textemail, $htmlemail);

        return $app['twig']->render('confirm.twig', array(
        	'name' => $name,
        	'lastnumber' => $id,
        	'lastdate' => $date
        ));
    }
});

/* Optionen: Newsletter bzw. Benachrichtigungen an- und abbestellen */
$app->get('/options/{token}', function (Application $app, $token) use ($dbTablePrefix) {
    $email = base64_decode($token);
    $errors = $app['session']->has('errors') ? $app['session']->get('errors') : array(
        'name' => false,
        'email' => false
    );
    
    if(!$app['session']->has('lasttry')) {
        $db = getDB();
        $stmt = $db->prepare("SELECT name, type FROM {$dbTablePrefix}members WHERE mail = ?");
        $stmt->execute(array($email));
        list($name, $type) = $stmt->fetch(PDO::FETCH_NUM);
        $app['session']->set('oldmail', $email);
    } else {
        $lasttry = $app['session']->get('lasttry');
        $name = $lasttry['name'];
        $email = $lasttty['email'];
        $type = $lasttry['type'];
    }
    
    $abo = array(
    	'newsletter' => $type & 2,
    	'notifications' => $type & 1
    );
    $admin = $app['session']->get('admin') == 'useredit';
    
    return $app['twig']->render('options.twig', array(
    	'error' => $errors,
    	'abo' => $abo,
    	'name' => $name,
    	'email' => $email,
    	'admin' => $admin
    ));
});

/* Optionen speichern */
$app->post('/saveoptions', function (Application $app) {
    $request = $app['request'];
    $name = $request->get('name');
    $oldmail = $app['session']->get('oldmail');
    $newmail = $request->get('email');
    $newsletter = $request->get('newsletter');
    $notification = $request->get('notification');
    
    $deleted = false;
    $admin = $app['session']->get('admin') == 'useredit';
    $emailchanged = $oldmail !== $newmail;
    
    if(!$newsletter && !$notification) {
        $deleted = true;
    }

    return $app['twig']->render('saveoptions.twig', array(
    	'name' => $name,
    	'deleted' => $deleted,
    	'admin' => $admin,
    	'emailchanged' => $emailchanged
    ));
});

/* Admin-Seite: Verfassen von Newslettern bzw. Benachrichtigungen */
$app->get('/admin', function (Application $app) {
    return $app['twig']->render('admin.twig', array(
    	'newsletter' => false
    ));
});

/* Preview: Vorschau der E-Mail */
$app->get('/admin/preview', function (Application $app) {
    return $app['twig']->render('preview.twig', array(
    	'text' => array('html' => "Hallo HTML!", 'text' => "Hallo Text!")
    ));
});

/* Versenden */
$app->get('/admin/send', function (Application $app) {
    return $app['twig']->render('send.twig', array(
    	'anzahl' => 59
    ));
});

/* Edit-Users: Bearbeiten von Benutzern */
$app->get('/admin/editusers', function (Application $app) {
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