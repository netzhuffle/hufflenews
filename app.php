<?php
require_once __DIR__.'/conf.php'; // Konfigurationsvariablen
require_once __DIR__.'/vendor/silex/silex.phar'; // Silex: Haupt-Framework

use Silex\Application;

$app = new Application();
$app['debug'] = $debug; // Debug-Modus
$app->register(new Silex\Provider\SessionServiceProvider()); // Session: PHP-Sessions

/* Twig (Template-Engine) konfigurieren */
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path'       => __DIR__.'/views',
    'twig.options'	  => array('cache' => $cacheTemplates ? $cacheTemplatesPath : false),
    'twig.class_path' => __DIR__.'/vendor/twig'
));
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

/* Swiftmailer (E-Mail-Versand) konfigurieren */
$app->register(new Silex\Provider\SwiftmailerServiceProvider(), array(
    'swiftmailer.options'	 => array(
        'host'	    => $smtpServer,
        'port'	    => $smtpPort,
        'username'	=> $smtpUser,
        'password'	=> $smtpPassword
    ),
    'swiftmailer.class_path' => __DIR__.'/vendor/swift/classes'
));

/* Zentrale Funktionen */
$db; // Datenbank-Verbindung (PDO-Instanz)
/**
 * Stellt eine Verbindung zur Datenbank her und gibt sie zurück
 * @return PDO
 */
function getDB() {
    global $db, $app, $dbDSN, $dbUser, $dbPassword;
    if (!isset($db)) {
        try {
            $db = new PDO($dbDSN, $dbUser, $dbPassword);
        } catch (PDOException $e) {
            $app->abort(500, "Fehler: Keine Datenbankverbindung");
        }
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
    if ($html) {
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
    global $app, $smtpMail, $smtpName;
    $successful = 0;
    $message = \Swift_Message::newInstance($subject);
    $message->setFrom(array($smtpMail => $smtpName));
    foreach ($to as $email => $name) {
        $message->setTo(array($email => $name));
        $message->setBody(createEmailText($html, $name, true), 'text/html');
        $message->addPart(createEmailText($text, $name, false), 'text/plain');
        $successful += $app['mailer']->send($message);
    }

    return $successful;
}

/* Hauptseite mit Anmelde-Formular (und Admin-Login) */
$app->get('/', function (Application $app) {
    $errors = $app['session']->get('errors', array( // Fehler beim letzten Versuch
        'name' => false,
        'email' => false,
        'abo' => false,
        'password' => false,
        'oldpassword' => false
    ));
    $lasttry = $app['session']->get('lasttry', array( // Werte vom letzten Versuch
        'name' => '',
        'email' => '',
        'abo' => array('newsletter' => false, 'notifications' => false),
        'password' => ''
    ));
    
    return $app['twig']->render('home.twig', array(
        'error' => $errors,
        'lasttry' => $lasttry
    ));
});

/* Prüft die Formular-Angaben und leitet an den richtigen Pfad weiter */
$app->post('/check', function (Application $app) use ($newsletterpassword, $notificationspassword, $usereditpassword, $newpassword, $oldpasswords) {
    $session = $app['session'];
    $request = $app['request'];
    
    $name = $request->get('name');
    $email = $request->get('email');
    $abo = $request->get('abo');
    if (!isset($abo['newsletter'])) $abo['newsletter'] = false;
    if (!isset($abo['notifications'])) $abo['notifications'] = false;
    $password = $request->get('password');

    /* Admin-Bereiche */
    if ($password === $newsletterpassword) {
        $session->set('admin', 'newsletter');
        return $app->redirect($request->getUriForPath('/admin'));
    } elseif ($password === $notificationspassword) {
        $session->set('admin', 'notifications');
        return $app->redirect($request->getUriForPath('/admin'));
    } elseif ($password === $usereditpassword) {
        $session->set('admin', 'useredit');
        return $app->redirect($request->getUriForPath('/useredit'));
    } else {
        /* Registrierung */
        $errors = array( // Prüfen auf Fehler
            'name' => !(trim($name)),
            'email' => !(trim($email)),
            'abo' => !($abo['newsletter'] || $abo['notifications']),
            'password' => $password !== $newpassword,
            'oldpassword' => in_array($password, $oldpasswords)
        );
        if (!($errors['name'] || $errors['email'] || $errors['abo'] || $errors['password'])) { // Falls kein Fehler
            $session->set('name', $name);
            $session->set('email', $email);
            $session->set('abo', $abo);
            $session->remove('lasttry');
            $session->remove('errors');
            return $app->redirect($request->getUriForPath('/register'));
        } else { // Falls Fehler
            $lasttry = array(
                'name' => $name,
                'email' => $email,
                'abo' => array('newsletter' => $abo['newsletter'], 'notifications' => $abo['notifications']),
                'password' => $password
            );
            $session->set('errors', $errors);
            $session->set('lasttry', $lasttry);
            
            return $app->redirect($request->getUriForPath('/'));
        }
    }
});

/* Registrierung (verschickt Bestätigungs-Mail) */
$app->get('/register', function (Application $app) {
    $session = $app['session'];
    
    $name = $session->get('name');
    $email = $session->get('email');
    $abo = $session->get('abo');
    $session->remove('name');
    $session->remove('email');
    $session->remove('abo');
    
    if (!($name && $email && $abo)) {
        return $app->redirect($request->getUriForPath('/')); 
    }
    
    $newsletter = $abo['newsletter'] ? 1 : 0;
    $notifications = $abo['notifications'] ? 1 : 0;
    $type = $newsletter + 2 * $notifications;
    $token = base64_encode("$name,$email,$type");

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
    if (count($tokenParts) != 3) {
        $app->abort(404, "Fehler: Token ungültig");
    } else {
        list($name, $email, $type) = $tokenParts;

        $db = getDB();
        /* In die Abonnentenliste eintragen */
        $stmt = $db->prepare("REPLACE INTO {$dbTablePrefix}members (name, mail, registerdate, type) VALUES (?, ?, now(), ?)");
        $stmt->execute(array($name, $email, $type));
        
        $newsletter = null;
        if ($type & 1) { // Wenn Newsletter abbonniert
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
            sendMail("Hufflepuff-Newsletter #$id", array($email => $name), $textemail, $htmlemail);
            
            $newsletter = array(
            	'number' => $id,
            	'date' => $date
            );
        }
        
        return $app['twig']->render('confirm.twig', array(
        	'name' => $name,
        	'newsletter' => $newsletter
        ));
    }
});

/* Optionen: Newsletter bzw. Benachrichtigungen an- und abbestellen */
$app->get('/options/{token}', function (Application $app, $token) use ($dbTablePrefix) {
    $email = base64_decode($token);
    $errors = $app['session']->get('errors', array(
        'name' => false,
        'email' => false
    ));
    
    if (!$app['session']->has('lasttry')) {
        $db = getDB();
        $stmt = $db->prepare("SELECT name, type FROM {$dbTablePrefix}members WHERE mail = ?");
        $stmt->execute(array($email));
        $result = $stmt->fetch(PDO::FETCH_NUM);
        if ($result) {
            list($name, $type) = $result;
            $app['session']->set('token', $token);
        } else {
            $app->abort(404, "Fehler: Token ungültig");
        }
    } else {
        $lasttry = $app['session']->get('lasttry');
        $name = $lasttry['name'];
        $email = $lasttry['email'];
        $type = $lasttry['type'];
    }
    
    $abo = array(
    	'newsletter' => $type & 1,
    	'notifications' => $type & 2
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
$app->post('/saveoptions', function (Application $app) use ($dbTablePrefix) {
    $token = $app['session']->get('token');
    if (!$token) {
        $app->abort(403, "Fehler: Kein Token");
    }
    
    $request = $app['request'];
    $name = $request->get('name');
    $oldmail = base64_decode($token);
    $newmail = $request->get('email');
    $abo = $request->get('abo');
    $newsletter = isset($abo['newsletter']) && $abo['newsletter'] ? 1 : 0;
    $notifications = isset($abo['notifications']) && $abo['notifications'] ? 1 : 0;
    $type = $newsletter + 2 * $notifications;
    
    $app['session']->remove('lasttry');
    $app['session']->remove('errors');
    $deleted = false;
    $admin = $app['session']->get('admin') == 'useredit';
    $emailchanged = $oldmail !== $newmail;
    
    $db = getDB();
    if ($type == 0) {
        /* Löschen */
        $deleted = true;
        $stmt = $db->prepare("DELETE FROM {$dbTablePrefix}members WHERE mail = ?");
        $stmt->execute(array($oldmail));
    } else {
        /* Fehlerprüfung */
        $errors = array(
            'name' => !trim($name),
            'email' => !trim($newmail)
        );
        
        if ($errors['name'] || $errors['email']) {
            $lasttry = array(
                'name' => $name,
                'email' => $newmail,
                'type' => $type
            );
            $app['session']->set('errors', $errors);
            $app['session']->set('lasttry', $lasttry);
            
            return $app->redirect($request->getUriForPath('/options/' . $token));
        }
        
        if (!$emailchanged) {
            /* Ändern (ohne Mail) */
            $stmt = $db->prepare("UPDATE {$dbTablePrefix}members SET name = ?, mail = ?, type = ? WHERE mail = ?");
            $stmt->execute(array($name, $newmail, $type, $oldmail));
        } else {
            /* E-Mail-Änderung = löschen und neue Registrierungsmail senden */
            $stmt = $db->prepare("DELETE FROM {$dbTablePrefix}members WHERE mail = ?");
            $stmt->execute(array($oldmail));
            
            $token = base64_encode("$name,$newmail,$type");
            $emailtemplate = $app['twig']->loadTemplate('confirmemail.twig');
            $htmlemail = $emailtemplate->render(array(
            	'name' => $name,
            	'token' => $token,
                'newsletter' => $type & 1,
            	'html' => true
            ));
            $textemail = $emailtemplate->render(array(
            	'name' => $name,
            	'token' => $token,
                'newsletter' => $type & 1,
            	'html' => false
            ));
            $successful = sendMail("Bestätigung der geänderten E-Mail für Hufflepuff-News", array($newmail => $name), $textemail, $htmlemail);
        }
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