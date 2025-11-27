<?php
// public/test_smtp.php
require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../config/mailer.php';

echo "<h2>Teste de Configuração SMTP</h2>";
echo "<pre>";
echo "SMTP_HOST: " . $_ENV['SMTP_HOST'] . "\n";
echo "SMTP_PORT: " . $_ENV['SMTP_PORT'] . "\n";
echo "SMTP_USER: " . $_ENV['SMTP_USER'] . "\n";
echo "SMTP_PASS: " . (strlen($_ENV['SMTP_PASS']) === 16 ? "✅ 16 caracteres (OK)" : "❌ " . strlen($_ENV['SMTP_PASS']) . " caracteres (deve ter 16)") . "\n";
echo "</pre>";

try {
    $mail = getMailer();
    $mail->addAddress('matiasmatavel1232@gmail.com'); // Enviar para você mesmo
    $mail->Subject = "Teste SMTP - " . date('Y-m-d H:i:s');
    $mail->Body = "Se você recebeu este email, o SMTP está funcionando corretamente!";
    $mail->SMTPDebug = 2; // Mostra detalhes da conexão
    
    if ($mail->send()) {
        echo "<h3 style='color:green'>✅ Email enviado com sucesso!</h3>";
    } else {
        echo "<h3 style='color:red'>❌ Erro: " . $mail->ErrorInfo . "</h3>";
    }
} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ Exceção: " . $e->getMessage() . "</h3>";
}
