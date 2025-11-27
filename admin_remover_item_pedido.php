<?php
// admin_remover_item_pedido.php - Remove item e reabastece estoque
session_start();
require_once "conexao.php";
require_once "require_login.php";

$usuario = $_SESSION['usuario'] ?? null;

if (!$usuario || $usuario['idperfil'] !== 1) {
    header('Location: login.php'); 
    exit();
}

$id_item_pedido = filter_input(INPUT_GET, 'id_item', FILTER_VALIDATE_INT);
$id_pedido = filter_input(INPUT_GET, 'id_pedido', FILTER_VALIDATE_INT);

if (!$id_item_pedido || !$id_pedido) {
    $_SESSION['erro'] = "Parâmetros inválidos.";
    header('Location: admin_lista_pedidos_finalizar.php');
    exit();
}

$conexao->begin_transaction();

try {
    // Buscar dados do item
    $sql_item = "
        SELECT ip.id_produto, ip.quantidade, ip.subtotal
        FROM item_pedido ip
        WHERE ip.id_item_pedido = ? AND ip.id_pedido = ?
    ";
    $stmt = $conexao->prepare($sql_item);
    $stmt->bind_param("ii", $id_item_pedido, $id_pedido);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        throw new Exception("Item não encontrado.");
    }
    
    $item = $resultado->fetch_assoc();
    $stmt->close();
    
    // Recrédito de estoque - Ingredientes base
    $sql_ing = "
        SELECT pi.id_ingrediente, pi.quantidade_ingrediente
        FROM produto_ingrediente pi
        WHERE pi.id_produto = ?
    ";
    $stmt_ing = $conexao->prepare($sql_ing);
    $stmt_ing->bind_param("i", $item['id_produto']);
    $stmt_ing->execute();
    $res_ing = $stmt_ing->get_result();
    
    while ($ing = $res_ing->fetch_assoc()) {
        $qtd_credito = $ing['quantidade_ingrediente'] * $item['quantidade'];
        
        $stmt_credito = $conexao->prepare("UPDATE ingrediente SET quantidade_estoque = quantidade_estoque + ? WHERE id_ingrediente = ?");
        $stmt_credito->bind_param("ii", $qtd_credito, $ing['id_ingrediente']);
        $stmt_credito->execute();
        $stmt_credito->close();
    }
    $stmt_ing->close();
    
    // Recrédito de estoque - Personalizações (extras)
    $sql_pers = "
        SELECT ipp.ingrediente_nome, ipp.tipo, COUNT(*) as qtd
        FROM item_pedido_personalizacao ipp
        WHERE ipp.id_item_pedido = ? AND ipp.tipo = 'extra'
        GROUP BY ipp.ingrediente_nome
    ";
    $stmt_pers = $conexao->prepare($sql_pers);
    $stmt_pers->bind_param("i", $id_item_pedido);
    $stmt_pers->execute();
    $res_pers = $stmt_pers->get_result();
    
    while ($pers = $res_pers->fetch_assoc()) {
        // Buscar ID do ingrediente pelo nome
        $stmt_id = $conexao->prepare("SELECT id_ingrediente FROM ingrediente WHERE nome_ingrediente = ? LIMIT 1");
        $stmt_id->bind_param("s", $pers['ingrediente_nome']);
        $stmt_id->execute();
        $res_id = $stmt_id->get_result();
        
        if ($row_id = $res_id->fetch_assoc()) {
            $qtd_credito_extra = $pers['qtd'] * $item['quantidade'];
            
            $stmt_credito = $conexao->prepare("UPDATE ingrediente SET quantidade_estoque = quantidade_estoque + ? WHERE id_ingrediente = ?");
            $stmt_credito->bind_param("ii", $qtd_credito_extra, $row_id['id_ingrediente']);
            $stmt_credito->execute();
            $stmt_credito->close();
        }
        $stmt_id->close();
    }
    $stmt_pers->close();
    
    // Débito de estoque - Removidos (devolver o que foi creditado)
    $sql_rem = "
        SELECT ipp.ingrediente_nome, COUNT(*) as qtd
        FROM item_pedido_personalizacao ipp
        WHERE ipp.id_item_pedido = ? AND ipp.tipo = 'removido'
        GROUP BY ipp.ingrediente_nome
    ";
    $stmt_rem = $conexao->prepare($sql_rem);
    $stmt_rem->bind_param("i", $id_item_pedido);
    $stmt_rem->execute();
    $res_rem = $stmt_rem->get_result();
    
    while ($rem = $res_rem->fetch_assoc()) {
        $stmt_id = $conexao->prepare("SELECT id_ingrediente FROM ingrediente WHERE nome_ingrediente = ? LIMIT 1");
        $stmt_id->bind_param("s", $rem['ingrediente_nome']);
        $stmt_id->execute();
        $res_id = $stmt_id->get_result();
        
        if ($row_id = $res_id->fetch_assoc()) {
            $qtd_debito_rem = $rem['qtd'] * $item['quantidade'];
            
            $stmt_debito = $conexao->prepare("UPDATE ingrediente SET quantidade_estoque = quantidade_estoque - ? WHERE id_ingrediente = ?");
            $stmt_debito->bind_param("ii", $qtd_debito_rem, $row_id['id_ingrediente']);
            $stmt_debito->execute();
            $stmt_debito->close();
        }
        $stmt_id->close();
    }
    $stmt_rem->close();
    
    // Deletar item (CASCADE deleta personalizações)
    $stmt_del = $conexao->prepare("DELETE FROM item_pedido WHERE id_item_pedido = ?");
    $stmt_del->bind_param("i", $id_item_pedido);
    $stmt_del->execute();
    $stmt_del->close();
    
    // Atualizar total do pedido
    $stmt_update = $conexao->prepare("UPDATE pedido SET total = total - ? WHERE id_pedido = ?");
    $stmt_update->bind_param("di", $item['subtotal'], $id_pedido);
    $stmt_update->execute();
    $stmt_update->close();
    
    $conexao->commit();
    
    $_SESSION['sucesso'] = "Item removido com sucesso e estoque reabastecido!";
    
} catch (Exception $e) {
    $conexao->rollback();
    $_SESSION['erro'] = "Erro ao remover item: " . $e->getMessage();
}

// Redirecionar de volta para detalhes do pedido ou lista
if (isset($_SESSION['admin_pedido_id']) && $_SESSION['admin_pedido_id'] == $id_pedido) {
    header('Location: cardapio.php?modo=admin_pedido');
} else {
    header('Location: admin_ver_detalhes_pedido.php?id_pedido=' . $id_pedido);
}
exit();
?>