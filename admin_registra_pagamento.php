<?php
// admin_registra_pagamento.php

session_start();
require_once "conexao.php"; 
// Certifique-se de que 'conexao.php' inicializa a variável $conexao,e que 
require_once "require_login.php";

// -----------------------------------------------------
// 1. Validação de Acesso e POST Data
// -----------------------------------------------------
$PERFIL_ADMIN = 'Administrador'; 
$usuario = $_SESSION['usuario'] ?? null;

if (!$usuario || $usuario['idperfil'] !==1 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php'); 
    exit();
}

// 🎯 Captura de dados
$id_pedido = filter_input(INPUT_POST, 'id_pedido', FILTER_VALIDATE_INT);
$total_pedido = filter_input(INPUT_POST, 'total_pedido', FILTER_VALIDATE_FLOAT); 
$valor_pago_admin = filter_input(INPUT_POST, 'valor_pago', FILTER_VALIDATE_FLOAT); 

if (!$id_pedido || $valor_pago_admin === false || $total_pedido === false) {
    $_SESSION['erro'] = "Dados de finalização inválidos ou incompletos.";
    header('Location: admin_finalizar_pedido.php'); 
    exit();
}

// Verifica se o valor pago é suficiente.
if ($valor_pago_admin < $total_pedido) {
    $_SESSION['erro'] = "O valor recebido ({$valor_pago_admin} MZN) é inferior ao total ({$total_pedido} MZN).";
    header('Location: admin_finalizar_pedido.php'); 
    exit();
}

// -----------------------------------------------------
// 2. Transação para Finalização e Baixa de Estoque
// -----------------------------------------------------

$conexao->begin_transaction();

try {
    
    // A. 🎯 Atualizar Tabela 'pedido' para CONCLUÍDO
    
    // Estes IDs devem corresponder aos seus valores na BD (ex: Presencial/Retirada/Manual)
    $ID_PAGAMENTO_PRESENCIAL = 1; 
    $ID_ENTREGA_DEFAULT = 1; 
    
    $sql_update_pedido = "
        UPDATE pedido 
        SET 
            status_pedido = 'pendente', 
            idtipo_pagamento = ?, 
            idtipo_entrega = ?,
            data_finalizacao = NOW(), 
            valor_pago_manual = ?
        WHERE id_pedido = ? AND status_pedido = 'pendente'
    ";
    
    $stmt = $conexao->prepare($sql_update_pedido);
    // Note que o campo 'total' NÃO é atualizado, pois já foi definido em admin_processa_item.php
    $stmt->bind_param("iidi", $ID_PAGAMENTO_PRESENCIAL, $ID_ENTREGA_DEFAULT, $valor_pago_admin, $id_pedido);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("Pedido não encontrado ou já finalizado. Transação interrompida.");
    }
    
    // B. 📉 Lógica de Baixa de Estoque (Ingredientes) - Início da lógica de consumo
    
    // 1. Obter todos os ingredientes necessários para este pedido.
    $sql_itens_ingredientes = "
        SELECT 
            ip.id_item_pedido, 
            ip.quantidade AS qtd_produto,
            pi.id_ingrediente,
            pi.quantidade_ingrediente AS qtd_base
        FROM item_pedido ip
        JOIN produto_ingrediente pi ON ip.id_produto = pi.id_produto
        WHERE ip.id_pedido = ?
    ";
    
    $stmt_ingr = $conexao->prepare($sql_itens_ingredientes);
    $stmt_ingr->bind_param("i", $id_pedido);
    $stmt_ingr->execute();
    $resultado_ingredientes = $stmt_ingr->get_result();
    
    $ingredientes_consumidos = []; 
    
    while ($row = $resultado_ingredientes->fetch_assoc()) {
        $id_ingrediente = $row['id_ingrediente'];
        $consumo = $row['qtd_produto'] * $row['qtd_base'];

        $ingredientes_consumidos[$id_ingrediente] = ($ingredientes_consumidos[$id_ingrediente] ?? 0) + $consumo;
        
        // Ajuste para personalização: Busca ajustes na tabela de personalização
        // (Ajustar nomes de tabela conforme seu schema exato se necessário)
        $sql_mod = "SELECT tipo, quantidade_ajuste FROM carrinho_ingrediente WHERE id_item_carrinho = ?"; 
        // NOTE: Usei 'carrinho_ingrediente' como placeholder para personalização em item_pedido
        $stmt_mod = $conexao->prepare($sql_mod);
        $stmt_mod->bind_param("i", $row['id_item_pedido']);
        $stmt_mod->execute();
        $resultado_mod = $stmt_mod->get_result();
        
        while($mod = $resultado_mod->fetch_assoc()){
            $ajuste = $mod['quantidade_ajuste'] * $row['qtd_produto'];
            
            if ($mod['tipo'] === 'removido') {
                $ingredientes_consumidos[$id_ingrediente] -= $ajuste;
            } elseif ($mod['tipo'] === 'extra') {
                $ingredientes_consumidos[$id_ingrediente] += $ajuste;
            }
        }
        $stmt_mod->close();
    }
    $stmt_ingr->close();
    
    // 2. Atualizar o estoque na tabela 'ingrediente'
    foreach ($ingredientes_consumidos as $id_ingrediente => $consumo_total) {
        if ($consumo_total > 0) {
            $sql_baixa_estoque = "
                UPDATE ingrediente 
                SET quantidade_estoque = quantidade_estoque - ? 
                WHERE id_ingrediente = ? AND quantidade_estoque >= ?
            ";
            $stmt_estoque = $conexao->prepare($sql_baixa_estoque);
            $stmt_estoque->bind_param("iii", $consumo_total, $id_ingrediente, $consumo_total);
            $stmt_estoque->execute();
            
            if ($stmt_estoque->affected_rows === 0) {
                $q_estoque = $conexao->query("SELECT quantidade_estoque FROM ingrediente WHERE id_ingrediente = {$id_ingrediente}")->fetch_assoc()['quantidade_estoque'] ?? 0;
                throw new Exception("Estoque insuficiente para o ingrediente ID {$id_ingrediente}. Necessário: {$consumo_total}, Disponível: {$q_estoque}.");
            }
            $stmt_estoque->close();
        }
    }

    // Comita a transação se não houver exceções
    $conexao->commit();
    
    // -----------------------------------------------------
    // 3. Sucesso e Limpeza
    // -----------------------------------------------------
    
    $troco = $valor_pago_admin - $total_pedido;
    unset($_SESSION['admin_pedido_id']);
    
    $_SESSION['sucesso'] = "Pedido #{$id_pedido} concluído com sucesso! Troco: " . number_format($troco, 2, ',', '.') . " MZN.";
    header('Location: dashboard.php');
    exit();

} catch (Exception $e) {
    // -----------------------------------------------------
    // 4. Tratamento de Erro (Rollback)
    // -----------------------------------------------------
    $conexao->rollback();
    
    $_SESSION['erro'] = "Falha na transação de pagamento: " . $e->getMessage();
    header('Location: admin_finalizar_pedido.php'); 
    exit();
}
?>