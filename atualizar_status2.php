<?php
// atualizar_status.php - VERSÃO UNIFICADA COM SUPORTE A "SERVIDO" E data_fim_pedido
include "conexao.php";
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["sucesso" => false, "erro" => "Método inválido"]);
    exit;
}

if (!isset($_POST["id_pedido"], $_POST["novo_status"])) {
    echo json_encode(["sucesso" => false, "erro" => "Parâmetros incompletos"]);
    exit;
}

$id = intval($_POST["id_pedido"]);
$status = trim($_POST["novo_status"]);

// Validar status permitidos
$status_validos = ['pendente', 'Em preparação', 'Pronto para Retirada', 'Saiu Para Entrega', 'entregue', 'servido'];
if (!in_array($status, $status_validos)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Status inválido']);
    exit;
}

$conexao->begin_transaction();

try {
    // Buscar informações do pedido (tipo_entrega e tipo_origem)
    $sql_pedido = "SELECT idtipo_entrega, idtipo_origem_pedido FROM pedido WHERE id_pedido = ?";
    $stmt = $conexao->prepare($sql_pedido);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        throw new Exception("Pedido não encontrado");
    }
    
    $pedido = $resultado->fetch_assoc();
    $tipo_entrega = intval($pedido['idtipo_entrega']);
    $id_tipo_origem = intval($pedido['idtipo_origem_pedido']);
    $eh_presencial = ($id_tipo_origem === 3);
    $stmt->close();
    
    // ===================================
    // ATUALIZAR STATUS DO PEDIDO
    // ===================================
    
    // Se o status for "entregue" ou "servido", atualizar data_fim_pedido
    if ($status === 'entregue' || $status === 'servido') {
        $stmt_update = $conexao->prepare("
            UPDATE pedido 
            SET status_pedido = ?, 
                data_fim_pedido = NOW(),
                data_finalizacao = NOW()
            WHERE id_pedido = ?
        ");
        $stmt_update->bind_param("si", $status, $id);
    } else {
        // Para outros status, apenas atualizar o status_pedido
        $stmt_update = $conexao->prepare("UPDATE pedido SET status_pedido = ? WHERE id_pedido = ?");
        $stmt_update->bind_param("si", $status, $id);
    }
    
    $stmt_update->execute();
    $stmt_update->close();
    
    // ===================================
    // REGISTRAR NO RASTREAMENTO
    // ===================================
    $stmt_rastreio = $conexao->prepare("INSERT INTO rastreamento_pedido (id_pedido, status_pedido2) VALUES (?, ?)");
    $stmt_rastreio->bind_param("is", $id, $status);
    $stmt_rastreio->execute();
    $stmt_rastreio->close();
    
    // ===================================
    // CRIAR NOTIFICAÇÃO
    // ===================================
    $mensagem_notif = "Pedido #{$id}: " . $status;
    $tipo_notif = 'status_pedido';
    
    $stmt_notif = $conexao->prepare("INSERT INTO notificacao (tipo, mensagem, id_pedido, lida) VALUES (?, ?, ?, 0)");
    $stmt_notif->bind_param("ssi", $tipo_notif, $mensagem_notif, $id);
    $stmt_notif->execute();
    $stmt_notif->close();
    
    $conexao->commit();
    
    // ===================================
    // REGENERAR BOTÕES HTML
    // ===================================
    ob_start();
    ?>

    <?php if ($status === "pendente"): ?>
        <form class="ajax-status">
            <input type="hidden" name="id_pedido" value="<?= $id ?>">
            <input type="hidden" name="novo_status" value="Em preparação">
            <input type="hidden" name="eh_presencial" value="<?= $eh_presencial ? '1' : '0' ?>">
            <button class="btn-preparar">Iniciar Preparo</button>
        </form>

    <?php elseif ($status === "Em preparação"): ?>
        <form class="ajax-status">
            <input type="hidden" name="id_pedido" value="<?= $id ?>">
            <input type="hidden" name="novo_status" value="Pronto para Retirada">
            <input type="hidden" name="eh_presencial" value="<?= $eh_presencial ? '1' : '0' ?>">
            <button class="btn-retirar">Pronto para Retirada/Entrega</button>
        </form>

    <?php elseif ($status === "Pronto para Retirada"): ?>

        <?php if ($eh_presencial): ?>
            <!-- PRESENCIAL: Botão "Servir" -->
            <form class="ajax-status">
                <input type="hidden" name="id_pedido" value="<?= $id ?>">
                <input type="hidden" name="novo_status" value="servido">
                <input type="hidden" name="eh_presencial" value="1">
                <button class="btn-servir">🍽️ Servir ao Cliente</button>
            </form>

        <?php elseif ($tipo_entrega == 1): ?>
            <!-- RETIRADA: Botão "Finalizar" -->
            <form class="ajax-status">
                <input type="hidden" name="id_pedido" value="<?= $id ?>">
                <input type="hidden" name="novo_status" value="entregue">
                <input type="hidden" name="eh_presencial" value="0">
                <button class="btn-finalizar">Finalizar Pedido</button>
            </form>

        <?php else: ?>
            <!-- DELIVERY: Botão "Saiu Para Entrega" -->
            <form class="ajax-status">
                <input type="hidden" name="id_pedido" value="<?= $id ?>">
                <input type="hidden" name="novo_status" value="Saiu Para Entrega">
                <input type="hidden" name="eh_presencial" value="0">
                <button class="btn-delivery">Saiu Para Entrega</button>
            </form>
        <?php endif; ?>

    <?php elseif ($status === "Saiu Para Entrega"): ?>
        <form class="ajax-status">
            <input type="hidden" name="id_pedido" value="<?= $id ?>">
            <input type="hidden" name="novo_status" value="entregue">
            <input type="hidden" name="eh_presencial" value="0">
            <button class="btn-finalizar">Finalizar Pedido</button>
        </form>

    <?php elseif ($status === "entregue" || $status === "servido"): ?>
        <!-- Pedido finalizado - não há mais botões -->
        <p style="color: #28a745; font-weight: bold; text-align: center; padding: 10px;">
            ✅ Pedido Concluído
        </p>

    <?php endif; ?>

    <?php
    $botoes = ob_get_clean();

    echo json_encode([
        "sucesso" => true,
        "id_pedido" => $id,
        "novo_status" => $status,
        "botoes" => $botoes
    ]);

} catch (Exception $e) {
    $conexao->rollback();
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
exit;
?>