<?php
// admin_registra_pagamento.php - VERSÃO PAGAMENTO PÓS-CONSUMO
session_start();
require_once "conexao.php"; 
require_once "require_login.php";

$usuario = $_SESSION['usuario'] ?? null;

if (!$usuario || $usuario['idperfil'] !== 1 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php'); 
    exit();
}

// Captura de dados
$id_pedido = filter_input(INPUT_POST, 'id_pedido', FILTER_VALIDATE_INT);
$total_pedido = filter_input(INPUT_POST, 'total_pedido', FILTER_VALIDATE_FLOAT); 
$metodo_pagamento = $_POST['metodo_pagamento'] ?? '';

if (!$id_pedido || $total_pedido === false || empty($metodo_pagamento)) {
    $_SESSION['erro'] = "Dados de pagamento inválidos ou incompletos.";
    header('Location: admin_finalizar_pedido.php?id_pedido=' . $id_pedido); 
    exit();
}

// Para espécie, validar valor recebido
$valor_recebido = null;
$troco = 0.00;

if ($metodo_pagamento === 'Dinheiro em espécie') {
    $valor_recebido = filter_input(INPUT_POST, 'valor_recebido', FILTER_VALIDATE_FLOAT);
    
    if ($valor_recebido === false || $valor_recebido < $total_pedido) {
        $_SESSION['erro'] = "Para pagamento em espécie, o valor recebido deve ser informado e maior ou igual ao total.";
        header('Location: admin_finalizar_pedido.php?id_pedido=' . $id_pedido); 
        exit();
    }
    
    $troco = $valor_recebido - $total_pedido;
} else {
    // Para métodos eletrônicos, o valor recebido é exatamente o total
    $valor_recebido = $total_pedido;
}

// Mapeamento de métodos para IDs na tabela tipo_pagamento
$metodos_map = [
  // Ajustar conforme sua BD
    'VISA' => 1,
    'E-mola' => 3,
    'M-pesa' => 4,
    'Mkesh' => 5,
       'Dinheiro em espécie' => 6
];

$id_tipo_pagamento = $metodos_map[$metodo_pagamento] ?? 1;

$conexao->begin_transaction();

try {
    // Validar que o pedido existe e está pendente
    $sql_valida = "SELECT total, status_pedido FROM pedido WHERE id_pedido = ? AND 
     status_pedido IN ('pendente', 'Em preparação', 'Saiu Para Entrega', 'Pronto para Retirada','servido') 
      AND status_pedido != 'Pago'
    ";
    $stmt_valida = $conexao->prepare($sql_valida);
    $stmt_valida->bind_param("i", $id_pedido);
    $stmt_valida->execute();
    $resultado = $stmt_valida->get_result();
    
    if ($resultado->num_rows === 0) {
        throw new Exception("Pedido não encontrado ou já foi finalizado.");
    }
    $stmt_valida->close();
    
    // ✅ Atualizar pedido para STATUS "pago" (ou "entregue" conforme sua lógica)
    $sql_update_pedido = "
        UPDATE pedido 
        SET 
            status_pedido = 'Pago',
            idtipo_pagamento = ?,
            data_finalizacao = NOW(),
            valor_pago_manual = ?,
            troco=?
        WHERE id_pedido = ?
    ";
    
    $stmt = $conexao->prepare($sql_update_pedido);
    $stmt->bind_param("iddi", $id_tipo_pagamento, $valor_recebido, $troco, $id_pedido);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("Falha ao atualizar o pedido.");
    }
    $stmt->close();
    
    // ✅ Registrar na tabela payments_local (opcional, para auditoria)
    $sql_payment = "
        INSERT INTO payments_local 
        (reference, amount, status, payment_method, paid_at, id_pedido, id_usuario)
        VALUES (?, ?, 'pago', ?, NOW(), ?, ?)
    ";
    $reference = 'PED-' . $id_pedido . '-' . time();
    $stmt_payment = $conexao->prepare($sql_payment);
    $stmt_payment->bind_param("sdsii", $reference, $total_pedido, $metodo_pagamento, $id_pedido, $usuario['id_usuario']);
    $stmt_payment->execute();
    $stmt_payment->close();

    // ⚠️ ESTOQUE JÁ FOI DEBITADO NA CRIAÇÃO, NÃO DEBITAR NOVAMENTE

    $conexao->commit();
    
    // Limpar sessão se era o pedido em andamento
    if (isset($_SESSION['admin_pedido_id']) && $_SESSION['admin_pedido_id'] == $id_pedido) {
        unset($_SESSION['admin_pedido_id']);
    }
    
    $mensagem_sucesso = "Pedido #{$id_pedido} pago com sucesso!";
    if ($metodo_pagamento === 'Dinheiro em espécie') {
        $mensagem_sucesso .= " Troco: " . number_format($troco, 2, ',', '.') . " MZN.";
    }
    
    $_SESSION['sucesso'] = $mensagem_sucesso;
    header('Location: admin_lista_pedidos_finalizar.php');
    exit();

} catch (Exception $e) {
    $conexao->rollback();
    $_SESSION['erro'] = "Falha no pagamento: " . $e->getMessage();
    header('Location: admin_finalizar_pedido.php?id_pedido=' . $id_pedido); 
    exit();
}
?>