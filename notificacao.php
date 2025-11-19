
<?php
function enviarNotificacaoPedido($id_pedido, $conexao) {
    if (!$id_pedido || !$conexao) {
        return false;
    }

    // Insere notificação na tabela
    $stmt = $conexao->prepare("
        INSERT INTO notificacao (id_pedido, lida, data_criacao)
        VALUES (?, 0, NOW())
    ");
    $stmt->bind_param("i", $id_pedido);
    $stmt->execute();
    $stmt->close();

    return true;
}
?>