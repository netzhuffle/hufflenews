<?php

use Silex\Application;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;

$app = new Application();
$app->register(new UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new ServiceControllerServiceProvider());

$db; // TODO rewrite to $app->share
/**
 * Stellt eine Verbindung zur Datenbank her und gibt sie zurück
 * @return PDO
 */
function getDB()
{
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
 * Nutzt bei [[x||y]] bei HTML-Mails x und bei Text-Mails y
 * Entfernt alle HTML-Tags aus Text-Mails
 * Ändert Zeilenumbrüche in &lt;br&gt;s in HTML-Mails
 * @param sting $newsletter Newsletter-Originaltext
 * @param string $name der Name des Empfängers
 * @param bool $html Ob HTML (bei false Text)
 * @return Resultierender Text
 */
function createEmailText($text, $name, $html)
{
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
function sendMail($subject, $to, $text, $html, $token, $request)
{
    global $app, $smtpMail, $smtpName;
    $successful = 0;
    $message = \Swift_Message::newInstance($subject);
    $message->setFrom(array($smtpMail => $smtpName));
    foreach ($to as $email => $name) {
        $message->setTo(array($email => $name));
        $message->setBody(createEmailText($html, $name, true), 'text/html');
        $message->addPart(createEmailText($text, $name, false), 'text/plain');
        $unsubscribeLink = $request->getUriForPath('/options/' . $token);
        $message->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsubscribeLink . '>');
        $message->getHeaders()->addTextHeader('Precedence', 'bulk');
        $successful += $app['mailer']->send($message);
    }

    return $successful;
}

return $app;
