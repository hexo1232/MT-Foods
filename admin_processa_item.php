<?php 
// 1. Incluir ficheiros de configuração e conexão com o BD
session_start();
require_once "conexao.php"; 


$quantidade = max(1, (int)($_POST['quantidade'] ?? 1)); 
$id_produto = intval($_POST['id_produto']);

$ID_ORIGEM_MANUAL = 3; // 🎯 Origem manual/presencial

// 2. Verifica se o admin está logado
$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || $usuario['idperfil'] !== 1) {
    header('Location: login.php'); 
    exit();
}

// -------------------------------------------------------------------
// Buscar informações do produto no BD (com categorias)
// -------------------------------------------------------------------
if (empty($_POST['id_produto'])) {
    http_response_code(400);
    exit("Produto não informado.");
}



$sql_produto = "
    SELECT p.id_produto, p.nome_produto, p.preco, p.preco_promocional,
           GROUP_CONCAT(c.nome_categoria SEPARATOR ', ') AS categorias
    FROM produto p
    LEFT JOIN produto_categoria pc ON p.id_produto = pc.id_produto
    LEFT JOIN categoria c ON pc.id_categoria = c.id_categoria
    WHERE p.id_produto = ?
    GROUP BY p.id_produto
";
$stmt_produto = $conexao->prepare($sql_produto);
$stmt_produto->bind_param("i", $id_produto);
$stmt_produto->execute();
$resultado_produto = $stmt_produto->get_result();
$produto = $resultado_produto->fetch_assoc();
$stmt_produto->close();

if (!$produto) {
    http_response_code(404);
    exit("Produto não encontrado.");
}



// -------------------------------------------------------------------
// Quantidade e Preço final
// -------------------------------------------------------------------


// Se for promoção usa preco_promocional, senão preço normal
if (stripos($produto['categorias'], 'Promoções da semana') !== false && $produto['preco_promocional'] > 0) {
    $preco_unitario_final = floatval($produto['preco_promocional']);
} else {
    $preco_unitario_final = floatval($produto['preco']);
}

$custo_total_novo_item = $quantidade * $preco_unitario_final;

// -------------------------------------------------------------------
// Inicia a transação
// -------------------------------------------------------------------
$conexao->begin_transaction();

try {
    // -----------------------------------------------------
    // C. Iniciar ou Obter o ID do Pedido em Curso
    // -----------------------------------------------------
    $id_admin = $usuario['id_usuario'] ?? 0;
    if ($id_admin <= 0) {
        throw new Exception("ID de Administrador inválido na sessão. Faça login novamente.");
    }

    $total_antigo     = 0.00;
    $id_pedido_atual  = $_SESSION['admin_pedido_id'] ?? null;
    
    if (!$id_pedido_atual) {
        // Cria um novo pedido
        $sql_cria_pedido = "
            INSERT INTO pedido 
            (id_usuario, status_pedido, total, idtipo_origem_pedido, idtipo_pagamento, idtipo_entrega) 
            VALUES (?, 'pendente', 0.00, ?, 1, 1)
        "; 

        $stmt = $conexao->prepare($sql_cria_pedido);
        $stmt->bind_param("ii", $id_admin, $ID_ORIGEM_MANUAL);
        $stmt->execute();

        if ($stmt->error) {
            throw new Exception("Erro ao criar o novo pedido: " . $stmt->error);
        }
        
        $id_pedido_atual = $conexao->insert_id;
        $_SESSION['admin_pedido_id'] = $id_pedido_atual;
    } else {
        // Busca o total atual
        $sql_total = "SELECT total FROM pedido WHERE id_pedido = ?";
        $stmt_total = $conexao->prepare($sql_total);
        $stmt_total->bind_param("i", $id_pedido_atual);
        $stmt_total->execute();

        $resultado_total = $stmt_total->get_result();
        if ($row = $resultado_total->fetch_assoc()) {
            $total_antigo = $row['total'];
        }
        $stmt_total->close();
    }

    // -----------------------------------------------------
    // D. Inserir o Item na Tabela 'item_pedido'
    // -----------------------------------------------------
   $sql_insere_item = "
        INSERT INTO item_pedido 
        (id_pedido, id_produto, quantidade, preco_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ";
    $stmt_item = $conexao->prepare($sql_insere_item);
    

    $stmt_item->bind_param("iiidd", $id_pedido_atual, $id_produto, $quantidade, $preco_unitario_final, $custo_total_novo_item);
    
    $stmt_item->execute();
    $id_item_pedido = $conexao->insert_id;
    $stmt_item->close();

    
 $novo_total = $total_antigo + $custo_total_novo_item;
    
    $sql_atualiza_total = "UPDATE pedido SET total = ? WHERE id_pedido = ?";
    $stmt_total_update = $conexao->prepare($sql_atualiza_total);
    $stmt_total_update->bind_param("di", $novo_total, $id_pedido_atual);
    $stmt_total_update->execute();
    $stmt_total_update->close();

    // Commit
    $conexao->commit();

} catch (Exception $e) {
    $conexao->rollback(); 
    $_SESSION['erro_pedido'] = "Erro na transação: " . $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']); 
    exit();
}
    
// 6. Sucesso e Redirecionamento
$_SESSION['sucesso_pedido'] = "Item adicionado ao Pedido #{$id_pedido_atual} com sucesso! Total: " . 
    number_format($novo_total, 2, ',', '.') . " MZN";

header('Location: cardapio.php?modo=admin_pedido'); 
exit();
