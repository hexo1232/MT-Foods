<?php
// admin_limpa_pedido.php

// ... (Incluir configurações e validar admin) ...

if (isset($_SESSION['admin_pedido_id'])) {
    $id_pedido = $_SESSION['admin_pedido_id'];

    // Opcional: Marcar o pedido no BD como 'Cancelado' antes de limpar a sessão.
    // $conexao->query("UPDATE pedido SET status = 'Cancelado' WHERE id = {$id_pedido}");

    // Limpar a sessão
    unset($_SESSION['admin_pedido_id']);

    $_SESSION['alerta'] = "Pedido #{$id_pedido} cancelado e limpado da sessão.";
    header('Location: cardapio.php');
    exit();
} else {
    $_SESSION['alerta'] = "Nenhum pedido em curso para limpar.";
    header('Location: cardapio.php');
    exit();
}
?>
