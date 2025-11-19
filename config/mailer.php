<?php
// config/mailer.php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function getMailer(): PHPMailer {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'];
    $mail->Port       = (int) $_ENV['SMTP_PORT'];
    $mail->SMTPAuth   = false; // MailHog não requer autenticação
    $mail->SMTPSecure = false; // Sem TLS/SSL

    $mail->setFrom($_ENV['FROM_EMAIL'], $_ENV['FROM_NAME']);
    $mail->isHTML(true);

    // DEBUG opcional
    //$mail->SMTPDebug = 2;

    return $mail;
}
