<?php
require_once __DIR__ . '/../src/PasswordReset.php';

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

if (!$email) {
    flashMessage("E-mail inválido!");
    exit;
}

try {
    $msg = sendPasswordResetLink($pdo, $email);
    flashMessage($msg);
} catch (Exception $e) {
    flashMessage("Erro ao enviar o e-mail: " . $e->getMessage());
}
