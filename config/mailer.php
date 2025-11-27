<?php
// config/mailer.php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function getMailer(): PHPMailer {
    $mail = new PHPMailer(true);

    try {
        // Configuração SMTP
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->Port       = (int) $_ENV['SMTP_PORT'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE']; // 'ssl' ou 'tls'
        $mail->SMTPAuth   = true; // Gmail PRECISA de autenticação
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        
        // Timeout para evitar travamento
        $mail->Timeout = 10; // 10 segundos
        $mail->SMTPKeepAlive = false;
        
        // Remetente
        $mail->setFrom($_ENV['FROM_EMAIL'], $_ENV['FROM_NAME']);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        // DEBUG (comentar em produção)
        // $mail->SMTPDebug = 2; // 0=off, 1=client, 2=client+server
        
    } catch (Exception $e) {
        error_log("Erro ao configurar PHPMailer: " . $e->getMessage());
        throw $e;
    }

    return $mail;
}
