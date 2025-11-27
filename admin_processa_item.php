<?php 
// admin_processa_item.php - VERSÃO MULTI-PEDIDOS
session_start();
require_once "conexao.php"; 

// Configurações
$quantidade = max(1, (int)($_POST['quantidade'] ?? 1)); 
$id_produto = intval($_POST['id_produto']);
$ID_ORIGEM_MANUAL = 3;
$ID_PAGAMENTO_PADRAO = 1; 
$ID_ENTREGA_PADRAO = 1;

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || $usuario['idperfil'] !== 1) {
    header('Location: login.php'); 
    exit();
}

if (empty($_POST['id_produto'])) {
    exit("Produto não informado.");
}

// Buscar Produto e Preço
$sql_produto = "SELECT p.id_produto, p.preco, p.preco_promocional, 
                GROUP_CONCAT(c.nome_categoria SEPARATOR ', ') AS categorias
                FROM produto p
                LEFT JOIN produto_categoria pc ON p.id_produto = pc.id_produto
                LEFT JOIN categoria c ON pc.id_categoria = c.id_categoria
                WHERE p.id_produto = ? GROUP BY p.id_produto";
$stmt = $conexao->prepare($sql_produto);
$stmt->bind_param("i", $id_produto);
$stmt->execute();
$res = $stmt->get_result();
$produto = $res->fetch_assoc();
$stmt->close();

if (!$produto) exit("Produto não encontrado.");

$preco_unitario = (stripos($produto['categorias'] ?? '', 'Promoções da semana') !== false && $produto['preco_promocional'] > 0) 
                  ? floatval($produto['preco_promocional']) 
                  : floatval($produto['preco']);
$subtotal_item = $quantidade * $preco_unitario;

// Início da Transação
$conexao->begin_transaction();

try {
    $id_admin = $usuario['id_usuario'];
    $id_pedido_atual = $_SESSION['admin_pedido_id'] ?? null;
    $total_pedido = 0.00;

    // Verificar se existe pedido ativo na sessão E se ele ainda está pendente na BD
    if ($id_pedido_atual) {
        $stmt = $conexao->prepare("SELECT id_pedido, total FROM pedido WHERE id_pedido = ? AND status_pedido = 'pendente'");
        $stmt->bind_param("i", $id_pedido_atual);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            $total_pedido = $row['total'];
        } else {
            // Pedido não existe ou já foi finalizado - criar novo
            $id_pedido_atual = null;
        }
        $stmt->close();
    }

    // Criar novo pedido se necessário
    if (!$id_pedido_atual) {
        $sql_cria = "INSERT INTO pedido (id_usuario, status_pedido, total, idtipo_origem_pedido, idtipo_pagamento, idtipo_entrega, telefone, email) 
                     VALUES (?, 'pendente', 0.00, ?, ?, ?, 0, 'admin@local')"; 
        $stmt = $conexao->prepare($sql_cria);
        $stmt->bind_param("iiii", $id_admin, $ID_ORIGEM_MANUAL, $ID_PAGAMENTO_PADRAO, $ID_ENTREGA_PADRAO);
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao criar pedido: " . $stmt->error);
        }
        
        $id_pedido_atual = $conexao->insert_id;
        $_SESSION['admin_pedido_id'] = $id_pedido_atual;
        $stmt->close();
    }

    // Verificar e Debitar Estoque
    $sql_ing = "SELECT pi.id_ingrediente, pi.quantidade_ingrediente, i.quantidade_estoque, i.nome_ingrediente
                FROM produto_ingrediente pi
                JOIN ingrediente i ON pi.id_ingrediente = i.id_ingrediente
                WHERE pi.id_produto = ?";
    $stmt = $conexao->prepare($sql_ing);
    $stmt->bind_param("i", $id_produto);
    $stmt->execute();
    $res_ing = $stmt->get_result();

    $updates_estoque = [];

    while ($ing = $res_ing->fetch_assoc()) {
        $qtd_total_nec = $ing['quantidade_ingrediente'] * $quantidade;
        if ($ing['quantidade_estoque'] < $qtd_total_nec) {
            throw new Exception("Estoque insuficiente de '{$ing['nome_ingrediente']}'. Necessário: {$qtd_total_nec}, Disponível: {$ing['quantidade_estoque']}");
        }
        $updates_estoque[] = [
            'qty' => $qtd_total_nec,
            'id' => $ing['id_ingrediente']
        ];
    }
    $stmt->close();

    // Executar débitos de estoque
    $stmt_up = $conexao->prepare("UPDATE ingrediente SET quantidade_estoque = quantidade_estoque - ? WHERE id_ingrediente = ?");
    foreach ($updates_estoque as $up) {
        $stmt_up->bind_param("ii", $up['qty'], $up['id']);
        $stmt_up->execute();
    }
    $stmt_up->close();

    // Inserir Item no Pedido
    $sql_item = "INSERT INTO item_pedido (id_pedido, id_produto, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexao->prepare($sql_item);
    $stmt->bind_param("iiidd", $id_pedido_atual, $id_produto, $quantidade, $preco_unitario, $subtotal_item);
    if (!$stmt->execute()) {
        throw new Exception("Erro ao inserir item: " . $stmt->error);
    }
    $stmt->close();

    // Atualizar Total do Pedido
    $novo_total = $total_pedido + $subtotal_item;
    $stmt = $conexao->prepare("UPDATE pedido SET total = ? WHERE id_pedido = ?");
    $stmt->bind_param("di", $novo_total, $id_pedido_atual);
    $stmt->execute();
    $stmt->close();

    $conexao->commit();

    $_SESSION['sucesso_pedido'] = "✅ Item adicionado ao Pedido #{$id_pedido_atual}!";

} catch (Exception $e) {
    $conexao->rollback(); 
    $_SESSION['erro_pedido'] = "❌ Erro: " . $e->getMessage();
}

header('Location: cardapio.php?modo=admin_pedido'); 
exit();
?>