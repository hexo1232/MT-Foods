<?php 
require_once __DIR__ . '/../src/PasswordReset.php';

$token = $_POST['token'] ?? '';
$new = $_POST['nova_senha'] ?? '';
$confirm = $_POST['confirmar_senha'] ?? '';

// Verifica se ambas as senhas foram preenchidas e coincidem
if (empty($token) || empty($new) || empty($confirm)) {
    flashMessage("Preencha todos os campos.");
    exit;
}

if ($new !== $confirm) {
    flashMessage("As senhas não coincidem.");
    exit;
}

// Verifica se o token é válido
$record = verifyToken($pdo, $token);
if (!$record) {
    flashMessage("Token inválido ou expirado.");
    exit;  
}

// Atualiza a senha (função já mostra mensagens e redireciona)
updatePassword($pdo, $record['id_usuario'], $record['id_reset'], $new);
exit;
?>
