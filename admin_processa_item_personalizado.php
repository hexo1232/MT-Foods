<?php 
// admin_processa_item_personalizado.php - VERSÃO MULTI-PEDIDOS
session_start();
require_once "conexao.php"; 
require_once "require_login.php"; 

// Configurações
$ID_ORIGEM_MANUAL = 3;
$ID_PAGAMENTO_PADRAO = 1;
$ID_ENTREGA_PADRAO = 1;

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || $usuario['idperfil'] !== 1) {
    header('Location: login.php'); 
    exit();
}

// Validação de entrada
if (empty($_POST['id_produto']) || empty($_POST['quantidade']) || !isset($_POST['preco'])) {
    http_response_code(400);
    exit("Dados incompletos.");
}

$id_produto = intval($_POST['id_produto']);
$quantidade = max(1, intval($_POST['quantidade']));
$preco_base_unitario = floatval($_POST['preco']); 
$preco_unitario_final = $preco_base_unitario;
$custo_total_novo_item = $quantidade * $preco_unitario_final;

$ingredientes_reduzidos_json = $_POST['ingredientes_reduzidos'] ?? '[]';
$ingredientes_incrementados_json = $_POST['ingredientes_incrementados'] ?? '[]';

$ingredientes_reduzidos = json_decode($ingredientes_reduzidos_json, true);
$ingredientes_incrementados = json_decode($ingredientes_incrementados_json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    exit("Erro na leitura dos dados de personalização.");
}

// Início da Transação
$conexao->begin_transaction();

try {
    $id_admin = $usuario['id_usuario'] ?? 0;
    if ($id_admin <= 0) {
        throw new Exception("ID de Administrador inválido.");
    }

    $total_antigo = 0.00;
    $id_pedido_atual = $_SESSION['admin_pedido_id'] ?? null;

    // Verificar pedido ativo
    if ($id_pedido_atual) {
        $stmt_check = $conexao->prepare("SELECT id_pedido, total FROM pedido WHERE id_pedido = ? AND status_pedido = 'pendente'");
        $stmt_check->bind_param("i", $id_pedido_atual);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        
        if ($row = $res_check->fetch_assoc()) {
            $total_antigo = floatval($row['total']);
        } else {
            $id_pedido_atual = null;
        }
        $stmt_check->close();
    }
    
    // Criar pedido se necessário
    if (!$id_pedido_atual) {
        $sql_cria_pedido = "
            INSERT INTO pedido 
            (id_usuario, status_pedido, total, idtipo_origem_pedido, idtipo_pagamento, idtipo_entrega, telefone, email) 
            VALUES (?, 'pendente', 0.00, ?, ?, ?, 0, '')
        "; 
        $stmt = $conexao->prepare($sql_cria_pedido);
        $stmt->bind_param("iiii", $id_admin, $ID_ORIGEM_MANUAL, $ID_PAGAMENTO_PADRAO, $ID_ENTREGA_PADRAO);
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao criar pedido: " . $stmt->error);
        }
        
        $id_pedido_atual = $conexao->insert_id;
        $_SESSION['admin_pedido_id'] = $id_pedido_atual;
        $stmt->close();
    }

    // Movimentação de Estoque - Base
    $sql_ingredientes_base = "
        SELECT pi.id_ingrediente, pi.quantidade_ingrediente, i.quantidade_estoque, i.nome_ingrediente
        FROM produto_ingrediente pi
        JOIN ingrediente i ON pi.id_ingrediente = i.id_ingrediente
        WHERE pi.id_produto = ?
    ";
    $stmt_base = $conexao->prepare($sql_ingredientes_base);
    $stmt_base->bind_param("i", $id_produto);
    $stmt_base->execute();
    $resultado_base = $stmt_base->get_result();

    while ($ingrediente = $resultado_base->fetch_assoc()) {
        $id_ingrediente = $ingrediente['id_ingrediente'];
        $qtd_base = $ingrediente['quantidade_ingrediente'] * $quantidade;
        
        if ($ingrediente['quantidade_estoque'] < $qtd_base) {
            throw new Exception("Estoque insuficiente para '{$ingrediente['nome_ingrediente']}' (Base). Necessário: {$qtd_base}, Disponível: {$ingrediente['quantidade_estoque']}");
        }

        $stmt_debito = $conexao->prepare("UPDATE ingrediente SET quantidade_estoque = quantidade_estoque - ? WHERE id_ingrediente = ?");
        $stmt_debito->bind_param("ii", $qtd_base, $id_ingrediente);
        $stmt_debito->execute();
        $stmt_debito->close();
    }
    $stmt_base->close();

    // Debitar Extras
    if (!empty($ingredientes_incrementados)) {
        foreach ($ingredientes_incrementados as $ingr) {
            $id_ingrediente = intval($ingr['id_ingrediente']);
            $qtd_debito = intval($ingr['qtd']) * $quantidade; 
            
            if ($qtd_debito > 0) {
                $stmt_check = $conexao->prepare("SELECT quantidade_estoque, nome_ingrediente FROM ingrediente WHERE id_ingrediente = ?");
                $stmt_check->bind_param("i", $id_ingrediente);
                $stmt_check->execute();
                $res_stock = $stmt_check->get_result();
                $dados_stock = $res_stock->fetch_assoc();
                $stmt_check->close();

                if (!$dados_stock || $dados_stock['quantidade_estoque'] < $qtd_debito) {
                    throw new Exception("Estoque insuficiente para '{$dados_stock['nome_ingrediente']}' (Extra). Necessário: {$qtd_debito}");
                }

                $stmt_debito = $conexao->prepare("UPDATE ingrediente SET quantidade_estoque = quantidade_estoque - ? WHERE id_ingrediente = ?");
                $stmt_debito->bind_param("ii", $qtd_debito, $id_ingrediente);
                $stmt_debito->execute();
                $stmt_debito->close();
            }
        }
    }

    // Creditar Removidos
    if (!empty($ingredientes_reduzidos)) {
        foreach ($ingredientes_reduzidos as $ingr) {
            $id_ingrediente = intval($ingr['id_ingrediente']);
            $qtd_credito = intval($ingr['qtd']) * $quantidade; 
            
            if ($qtd_credito > 0) {
                $stmt_credito = $conexao->prepare("UPDATE ingrediente SET quantidade_estoque = quantidade_estoque + ? WHERE id_ingrediente = ?");
                $stmt_credito->bind_param("ii", $qtd_credito, $id_ingrediente);
                $stmt_credito->execute();
                $stmt_credito->close();
            }
        }
    }

    // Inserir item no pedido
    $sql_insere_item = "INSERT INTO item_pedido (id_pedido, id_produto, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
    $stmt_item = $conexao->prepare($sql_insere_item);
    $stmt_item->bind_param("iiidd", $id_pedido_atual, $id_produto, $quantidade, $preco_unitario_final, $custo_total_novo_item);
    
    if (!$stmt_item->execute()) {
        throw new Exception("Falha ao inserir item no pedido: " . $stmt_item->error);
    }
    $id_item_pedido = $conexao->insert_id;
    $stmt_item->close();

    // Registrar personalizações
    $stmt_nome = $conexao->prepare("SELECT nome_ingrediente FROM ingrediente WHERE id_ingrediente = ?");
    $stmt_detalhe = $conexao->prepare("INSERT INTO item_pedido_personalizacao (id_item_pedido, ingrediente_nome, tipo) VALUES (?, ?, ?)");

    // Extras
    foreach ($ingredientes_incrementados as $ingr) {
        $id_ingr = intval($ingr['id_ingrediente']);
        $qtd = intval($ingr['qtd']);
        if ($qtd > 0) {
            $stmt_nome->bind_param("i", $id_ingr);
            $stmt_nome->execute();
            $nome = $stmt_nome->get_result()->fetch_assoc()['nome_ingrediente'] ?? 'Extra';
            
            $tipo = 'extra';
            for($i=0; $i<$qtd; $i++) {
                $stmt_detalhe->bind_param("iss", $id_item_pedido, $nome, $tipo);
                $stmt_detalhe->execute();
            }
        }
    }

    // Removidos
    foreach ($ingredientes_reduzidos as $ingr) {
        $id_ingr = intval($ingr['id_ingrediente']);
        $qtd = intval($ingr['qtd']);
        if ($qtd > 0) {
            $stmt_nome->bind_param("i", $id_ingr);
            $stmt_nome->execute();
            $nome = $stmt_nome->get_result()->fetch_assoc()['nome_ingrediente'] ?? 'Removido';
            
            $tipo = 'removido';
            for($i=0; $i<$qtd; $i++) {
                $stmt_detalhe->bind_param("iss", $id_item_pedido, $nome, $tipo);
                $stmt_detalhe->execute();
            }
        }
    }
    $stmt_nome->close();
    $stmt_detalhe->close();

    // Atualizar total do pedido
    $novo_total = $total_antigo + $custo_total_novo_item;
    $stmt_total_update = $conexao->prepare("UPDATE pedido SET total = ? WHERE id_pedido = ?");
    $stmt_total_update->bind_param("di", $novo_total, $id_pedido_atual);
    $stmt_total_update->execute();
    $stmt_total_update->close();

    $conexao->commit();
    $_SESSION['sucesso_pedido'] = "✅ Item personalizado adicionado ao Pedido #{$id_pedido_atual}!";

} catch (Exception $e) {
    $conexao->rollback(); 
    $_SESSION['erro_pedido'] = "❌ Erro: " . $e->getMessage();
}

header('Location: cardapio.php?modo=admin_pedido'); 
exit();
?>