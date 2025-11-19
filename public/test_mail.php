<?php
require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../config/mailer.php';

$mail = getMailer();
$mail->addAddress('teste@dominio.com');
$mail->Subject = "Teste SMTP MailHog";
$mail->Body = "Se você ver este e-mail no MailHog, está funcionando!";
$mail->SMTPDebug = 2;

try {
    $mail->send();
    echo "✅ E-mail enviado!";
} catch (Exception $e) {
    echo "❌ Erro: " . $mail->ErrorInfo;
}
