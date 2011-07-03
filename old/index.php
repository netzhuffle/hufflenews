<?php

  // Passw_rter
  define(NEUES_PASSWORT, "neuespw");
  define(ALTES_PASSWORT, "altespw");
  define(ADMIN_PASSWORT, "adminpw");
  
  // Funktionen
  header("Content-type: text/html; charset=iso-8859-1");
  error_reporting(E_ALL);

  function dbconnect() {
    mysql_connect("localhost","root","");
    mysql_select_db("newsletter");
  }

  function htmlstyle($coremsg) {
    $coremsg = nl2br($coremsg);
    $htmlmsg = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">'.
      '<html><head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">'.
      '<title>Hufflepuff-News</title>'.
      '</head><body style="margin:0px; padding:0px; background:url(http://www.huffle-home.de/newsletter/paper.gif);">'.
      '<div style="position:absolute; top:0px; left:0px; height:380px; width:380px; z-index:300;"><img src="http://www.huffle-home.de/newsletter/logo.png" alt="Hufflepuff-Newsletter"></div>'.
      '<div style="position:absolute; top:200px; left:200px; right:50px; z-index:600; text-align:center; font-family:\'Comic Sans MS\',sans-serif; font-size:1.3em;">'.
      $coremsg.'</div></body></html>';
    return $htmlmsg;
  }

  function sendmail($to,$subject,$coremsg,$type=1) {
    $subject = "=?ISO-8859-1?B?".base64_encode($subject)."?=";
    if($type==1) {
      $boundary = rand(1000000,9999999);
      $headers = "From: ".'"Fetter Moench"'." <fettermoench@huffle-home.de>\n".
        "MIME-Version: 1.0\n".
        "Content-Type: multipart/alternative;\n".
        ' boundary="'.$boundary.'"';
      $textmsg = strip_tags(preg_replace("/\[\[.*\|\|(.*)\]\]/isU", "$1", $coremsg));
      $htmlmsg = htmlstyle(preg_replace("/\[\[(.*)\|\|.*\]\]/isU", "$1", $coremsg));
      $message = "Falls dieser Satz hier angezeigt wird, ist etwas nicht gut gelaufen. ".
        "Bitte schreib uns eine Mail und sag uns, womit du diese E-Mail liest. Danke.\r\n\r\n".
        "--".$boundary."\n".
        "Content-Type: text/plain; charset=iso-8859-1\n".
        "Content-Transfer-Encoding: 7bit\n\n".
        $textmsg."\n\n".
        "--".$boundary."\n".
        "Content-type: text/html; charset=iso-8859-1\n".
        "Content-Transfer-Encoding: 7bit\n\n".
        $htmlmsg."\n\n".
        "--".$boundary."--";
    }
    else {
      $headers = "From: ".'"Fetter Moench"'." <fettermoench@huffle-home.de>\n".
        "MIME-Version: 1.0\n".
        "Content-Type: text/plain; charset=iso-8859-1\n".
        'Content-Transfer-Encoding: 7bit\n';
      $textmsg = strip_tags(preg_replace("/\[\[.*\|\|(.*)\]\]/isU", "$1", $coremsg));
    }
    mail($to, $subject, $message, $headers);
  }

  function subscribe_form($msg) {
    $form = $msg . "\n\n<form action='' method='POST'>".
      "&nbsp;Name: <input type='text' maxlength='30' size='30' name='name'>\n".
      "E-Mail: <input type='text' maxlength='50' size='30' name='mail'>\n\n".
      "Und zum Schlu_ noch das Hauspasswort:\n".
      "<input type='text' size='30' name='pwd'><input type='submit' value='Senden!'></form>";
    return $form;
  }

  // Verarbeitung
  function subscribe() {
    if(isset($_REQUEST["t"])) {
      $token = urldecode(base64_decode($_REQUEST["t"]));
      $user = split("&", $token);
    }
    if(isset($user) && count($user)==2) {
      dbconnect();
      $sql = "SELECT count(*) AS n FROM huffle_members WHERE mail = '$user[0]'";
      $result = mysql_fetch_array(mysql_query($sql));
      if($result["n"] == 0) {
        $datetime = date("Y-m-d H:i:s");
        $sql = "INSERT INTO huffle_members (name, mail, first) VALUES ('$user[1]', '$user[0]','$datetime')";
        mysql_query($sql);
      }
      $sql = "SELECT nid, date, text FROM huffle_news ORDER BY date DESC LIMIT 1";
      $result = mysql_query($sql);
      $news = mysql_fetch_array($result);
      $news["datum"] = date("j. n. Y",strtotime($news["date"]));
      $mailtext = preg_replace("/\{\{USER\}\}/", $user[1], stripslashes($news["text"]));
      sendmail($user[0],"Hufflepuff-Newsletter #$news[nid]",$mailtext);
      $sql = "UPDATE huffle_members Set last = '$news[date]' WHERE mail = '$user[0]'";
      mysql_query($sql);
      $meldung = "Herzliche Gratulation, $user[1]!\nDu hast dich jetzt erfolgreich eingetragen.\n\n".
        "Wir senden dir jetzt automatisch den letzten Newsletter (Nummer $news[nid] vom $news[datum]) zu.\n\nViel Spa_ beim lesen, deine Hufflepuff-VS.";
      echo htmlstyle($meldung);
    }
    elseif(isset($_REQUEST["pwd"]) && strtolower($_REQUEST["pwd"]) == ALTES_PASSWORT) {
   	  $seite = "Oh Gott, das ist ja das alte Passwort! Wei_ mit Bart und Staub und so... Du solltest schleunigst mal das neue holen. Echt! Na los. Bitte. Ehrlich.";
      echo htmlstyle($seite);
    }
    elseif(isset($_REQUEST["mail"]) && $_REQUEST["mail"] && isset($_REQUEST["name"]) && $_REQUEST["name"] && isset($_REQUEST["pwd"]) && strtolower($_REQUEST["pwd"]) == NEUES_PASSWORT) {
      $token = urlencode(base64_encode($_REQUEST["mail"]."&".$_REQUEST["name"]));
      $best_tigung = "Hallo $_REQUEST[name]!\n\nDu (oder jemand anders?) hat deine Mailadresse f_r den Hufflepuff-Newsletter eingetragen. ".
        "Um zu best_tigen, dass du den Hufflepuff-Newsletter wirklich empfangen willst, rufe die Seite ".
        "<a href='http://www.huffle-home.de/newsletter/index.php?t=$token'>".
        "http://www.huffle-home.de/newsletter/index.php?t=$token</a> auf.\n".
        "Du bekommst dann auch gleich unseren letzten Newsletter zugeschickt.\n\nDeine Hufflepuff-VS";
      sendmail($_REQUEST["mail"],"E-Mail Best_tigung f_r Hufflepuff-News",$best_tigung);
      $gesendet = "Hallo $_REQUEST[name]!\n\nSch_n, dass du dich f_r die Hufflepuff-News interessierst.\nWir haben dir eine Mail geschickt, ".
        "damit du das ganze nochmal best_tigen kannst. Falls bis in einer Stunde noch keine Mail angekommen ist (auch nicht im Spam-Ordner), ".
        "schreib uns eine Mail. Aber eigentlich sollte es schon klappen ;-)\n\nViel Spa_, deine Hufflepuff-VS.\n\n ".
        "<!-- PS: Falls die Mail zwar ankommt, aber leer ist (oder aus lauter komischen Zeichen besteht), dann ist deine Eule zu schwach, um das Newsletter-Paket zu tragen... ".
        "Falls dieser Fall auftritt, klick <a href='index.php?textonly=1'>hier</a> und f_lle das Formular nochmal aus, und wir schicken dir eine Nur-Text-Version. -->";
      echo htmlstyle($gesendet);
    }
    elseif(isset($_REQUEST["pwd"]) && strtolower($_REQUEST["pwd"]) == "butterschnaps") {
      $seite = "<i>Du trittst durch einen verborgenen Geheimgang. Doch pl_tzlich geht es nicht mehr weiter: Vor dir ist eine Wand. ".
        "Vor dir steht JANNiS und werkelt ein wenig mit Pergament, Feder und Zauberstab. Tja, hier ist halt noch eine Baustelle!</i>";
      echo htmlstyle($seite);
    }
    elseif(isset($_REQUEST["pwd"]) && strtolower($_REQUEST["pwd"]) == "buttercrash") {
      $seite = "<i>Du trittst durch einen verborgenen Geheimgang. Doch pl_tzlich geht es nicht mehr weiter: Vor dir ist eine Wand. ".
        "Vor dir steht JANNiS und werkelt ein wenig mit Pergament, Feder und Zauberstab. Tja, hier ist halt noch eine Baustelle!</i>";
      echo htmlstyle($seite);
    }
    elseif(isset($_REQUEST["pwd"]) && strtolower($_REQUEST["pwd"]) == ADMIN_PASSWORT) {
      $seite = "<div style='font-size:0.6em;'>Oh, gibt's nen neuen Newsletter f_r uns? <i>*freu*</i>\n\n".
        "Noch ein paar Tipps: HTML ist erlaubt, sollte aber nicht zu oft eingesetzt werden, sonst wird das ganze etwas overpowered. ".
        "z.B. <b>fett</b> gibt's durch &lt;b&gt;text&lt;/b&gt;, <i>kursiv</i> mit &lt;i&gt;text&lt;/i&gt;. ".
        "Wenn du irgendwo {{USER}} eingibst, wird das mit dem Namen des Empf_ngers ersetzt. ".
        "Damit kannst du den Newsletter viel pers_nlicher machen!\n\n</div>".
        "<form action='http://www.huffle-home.de/newsletter/index.php' method='POST'>".
        "<input type='submit' value='Fertig, bereit zum senden, los, abschicken!'>".
        "<textarea name='mailtext' cols='100' rows='9' style=\"border:2px solid black; background:url(http://www.huffle-home.de/newsletter/paper.gif);".
        "text-align:center; font-family:'Comic Sans MS',sans-serif; font-size:1em; left:200px; right:50px;\">Hallo {{USER}}, ...</textarea></form>";
      echo htmlstyle($seite);
    }
    elseif(isset($_REQUEST["mailtext"]) && isset($_REQUEST["senden"])) {
      dbconnect();
      $date = date("Y-m-d");
      $sql = "SELECT name, mail FROM huffle_members";
      $result = mysql_query($sql);
      $anz = mysql_num_rows($result);
      $text = preg_replace("/<br \/>/", "", $_REQUEST["mailtext"]);
      $sql = "INSERT INTO huffle_news (date, text) VALUES ('$date', '$text')";
      mysql_query($sql);
      $sql = "SELECT nid FROM huffle_news WHERE date = '$date' ORDER BY nid DESC";
      $resnews = mysql_query($sql);
      $news = mysql_fetch_assoc($resnews);
      while($to = mysql_fetch_assoc($result)) {
        $mailtext = preg_replace("/\{\{USER\}\}/", $to["name"], stripslashes($text));
        sendmail($to["mail"],"Hufflepuff-Newsletter #$news[nid]",$mailtext);
      }
      echo htmlstyle("Der Newsletter wurde erfolgreich an $anz Leute versendet.");
    }
    elseif(isset($_REQUEST["mailtext"])) {
      $kontrollseite = "<div style='font-size:0.8em;'>So, dann lies die Mail sicherheitshalber nochmal durch. ".
        "Darunter siehst du sie nochmal, wie sie f_r jemanden aussieht, der keine HTML-Mails empfangen kann. ".
        "Wenn du mit allem fertig bist, kannst du unten den das Senden best_tigen, und dann warten, bis alles verschickt ist...</div>\n<hr>\n".
        preg_replace("/\{\{USER\}\}/", "Hufflepuff", stripslashes($_REQUEST["mailtext"])).
        "\n\n<hr>\n<div style='background:white; text-align:left; font-family:monospace;'>".
        strip_tags(preg_replace("/\{\{USER\}\}/", "Hufflepuff", stripslashes($_REQUEST["mailtext"]))).
        "</div>\n<hr><div style='font-size:0.8em; font-style:inherit;'><form action='http://www.huffle-home.de/newsletter/index.php' method='POST'>".
        "<input type='checkbox' name='senden' value='true'> Ja, ich habe den Newsletter nochmal durchgelesen und keinen Fehler gefunden, ".
        "und verspreche, dass ich, wenn ich auf Senden geklickt habe, geduldig warte, bis die Meldung kommt, dass es versendet wurde, ".
        "nicht auf Abbrechen klicke, das Fenster schlie_e, Cola auf meine Tastatur tropfe, oder zuviel Knoblauch esse.</div>\n".
        "<input type='submit' value='Senden!'><input type='hidden' name='mailtext' value='".stripslashes($_REQUEST["mailtext"])."'></form>";
      echo htmlstyle($kontrollseite);
    }
    else {
      echo htmlstyle(subscribe_form("Eintragen:"));
    }
  }

  subscribe();
?>