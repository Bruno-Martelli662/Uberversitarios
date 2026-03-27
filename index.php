<?php

require "PHPMailer\src\PHPMailer.php";
require "PHPMailer\src\Exception.php";
require "PHPMailer\src\SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer();

$mail->isSMTP();
$mail->CharSet = "UTF-8";
$mail->SMTPDebug = 0;
$mail->SMTPAuth = true;
$mail->SMTPSecure = 'ssl';
$mail->Host = 'smtp.gmail.com';
$mail->Port = 465;

$mail->Username = 'uberversitarios@gmail.com';
$mail->Password = 'yhvcnuzaeovpumvu';

$mail->setFrom('uberversitariosteste@gmail.com', 'Uberversitários');
$mail->addAddress('uberversitariosteste@gmail.com');

$mail->Subject = "Light";
$mail->MsgHTML("<h1>Absolute Cinema</h1>");

$mail->send();

?>