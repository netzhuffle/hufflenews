<?php

/* Newsletter-Passwörter */
$newpassword = 'Diptamessenz'; // Aktuelles Hauspasswort
$oldpasswords = array('Schwan', 'Schwein', 'Schwubb'); // Die letzten Hauspasswörter
$newsletterpassword = 'Newsletter'; // Passwort um Newsletter zu schreiben
$notificationspassword = 'Notifications'; // Passwort um Benachrichtigungen zu schreiben
$usereditpassword = 'Blubber'; // Passwort um Empfänger zu löschen oder auf Nur-Text umzustellen

/* Datenbank-Infos */
$dbServer = 'localhost'; // Datenbank-Server
$dbUser = 'root'; // User-Name
$dbPassword = ''; // User-Passwort
$dbName = 'hufflenews'; // Datenbank (Schema) Name
$dbTablePrefix = 'hnews_'; // Tabellen-Präfix

/* SMTP-Infos */
$smtpServer = 'localhost'; // SMTP-Server
$smtpPort = 25; // SMTP-Port
$smtpMail = 'fettermoench@huffle-home.de'; // Absender-Mailadresse
$smtpName = 'Fetter Moench'; // Absender-Name
$smtpUser = 'info'; // Benutzername
$smtpPassword = ''; // Passwort

/* App */
$debug = false; // Debug-Modus (für Silex und Twig)
$cacheTemplates = false; // Ob Templates gecached werden sollen
$cacheTemplatesPath = __DIR__.'/views/cache'; // Absoluter Pfad zum Cache für Silex-Templates (muss beschreibbar sein!)