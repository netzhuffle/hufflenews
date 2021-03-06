<?php

/* Newsletter-Passwörter */
$newpassword = 'Diptamessenz'; // Aktuelles Hauspasswort
$oldpasswords = array('Schwan', 'Schwein', 'Schwubb'); // Die letzten Hauspasswörter
$newsletterpassword = 'Newsletter'; // Passwort um Newsletter zu schreiben
$notificationspassword = 'Notifications'; // Passwort um Benachrichtigungen zu schreiben
$usereditpassword = 'Blubber'; // Passwort um Empfänger zu löschen oder auf Nur-Text umzustellen

/* Datenbank-Infos */
$dbDSN = 'mysql:host=localhost;dbname=hufflenews'; // PDO-DSN-String, z.B. 'mysql:host=hostname;dbname=databasename'
$dbUser = 'root'; // User-Name
$dbPassword = ''; // User-Passwort
$dbTablePrefix = 'hnews_'; // Tabellen-Präfix

/* SMTP-Infos */
$smtpServer = 'localhost'; // SMTP-Host
$smtpPort = 25; // SMTP-Port
$smtpMail = 'fettermoench@localhost'; // Absender-Mailadresse
$smtpName = 'Fetter Moench'; // Absender-Name
$smtpUser = 'info'; // Benutzername
$smtpPassword = ''; // Passwort

/* App */
$app['twig.path'] = array(__DIR__.'/../views');
$app['twig.options'] = array('cache' => __DIR__.'/../cache/twig');
$app['swiftmailer.options'] = array(
  'host'	    => $smtpServer,
  'port'	    => $smtpPort,
  'username'	=> $smtpUser,
  'password'	=> $smtpPassword
);
$app['swiftmailer.sender_address'] = $smtpMail;