<?php
ob_start(); // <--- LINHA NOVA: Inicia o buffer de saída para evitar erro de headers
session_start();

// Captura o perfil do usuário antes de destruir a sessão
// Usamos isset para evitar warning se a chave não existir
$idperfil = isset($_SESSION['usuario']['idperfil']) ? $_SESSION['usuario']['idperfil'] : null;

// ✅ Limpa somente os dados de login
unset($_SESSION['usuario']);

// Destrói a sessão completamente (opcional, mas recomendado para logout total)
// session_destroy(); 

// 🔒 Fecha e salva a sessão
session_write_close();

// ✅ Redireciona com base no perfil
if ($idperfil == 1) {
    header("Location: login.php");
} else {
    header("Location: index.php");
}

ob_end_flush(); // <--- LINHA NOVA: Envia o buffer e encerra
exit;
?>
