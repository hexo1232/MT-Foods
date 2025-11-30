<?php
ob_start(); // Inicia o buffer de saída

session_start();

// -----------------------------------------------------------------
// 💡 LÓGICA CORRIGIDA: Adiciona a verificação "if" que faltava.
// A linha 20 do seu código original (que tinha "}") foi removida.
// -----------------------------------------------------------------

if (isset($_SESSION['usuario'])) {

    // Captura o perfil do usuário antes de destruir a sessão
    $idperfil = $_SESSION['usuario']['idperfil'] ?? null;

    // ✅ Limpa somente os dados de login
    unset($_SESSION['usuario']);

    // 🔒 Fecha e salva a sessão
    session_write_close();

    // Redireciona com base no perfil (se for admin, redireciona para login, por exemplo)
    if ($idperfil == 1) { // 1 = Admin, supondo que o login de admin seja diferente
        header("Location: login.php");
    } else {
        header("Location: index.php");
    }

} else { // Usuário não estava logado, apenas redireciona para a página inicial
    header("Location: index.php");
}

ob_end_flush(); // Envia o buffer e encerra
exit;
?>
