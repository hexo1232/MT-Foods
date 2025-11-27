<?php
// admin_cancelar_pedido.php - Cancela pedido e ESTORNA estoque
session_start();
require_once "conexao.php"; 
require_once "require_login.php";

$usuario = $_SESSION['usuario'] ?? null;

if (!$usuario || $usuario['idperfil'] !== 1) {
    header('Location: login.php'); 
    exit();
}

$id_pedido = filter_input(INPUT_GET, 'id_pedido', FILTER_VALIDATE_INT);

if (!$id_pedido) {
    $_SESSION['erro'] = "Pedido não especificado.";
    header('Location: admin_lista_pedidos_finalizar.php'); 
    exit();
}

$conexao->begin_transaction();

try {
    // Validar que o pedido existe e está pendente
    $sql_valida = "SELECT status_pedido FROM pedido WHERE id_pedido = ?";
    $stmt_valida = $conexao->prepare($sql_valida);
    $stmt_valida->bind_param("i", $id_pedido);
    $stmt_valida->execute();
    $resultado = $stmt_valida->get_result();
    
    if ($resultado->num_rows === 0) {
        throw new Exception("Pedido não encontrado.");
    }
    
    $pedido_info = $resultado->fetch_assoc();
    
    if ($pedido_info['status_pedido'] !== 'pendente') {
        throw new Exception("Apenas pedidos pendentes podem ser cancelados.");
    }
    $stmt_valida->close();
    
    // ✅ ESTORNAR ESTOQUE - Buscar todos os itens e seus ingredientes
    
    // 1. Estornar ingredientes BASE dos produtos
    $sql_itens = "
        SELECT ip.id_produto, ip.quantidade, ip.id_item_pedido
        FROM item_pedido ip
        WHERE ip.id_pedido = ?
    ";
    $stmt_itens = $conexao->prepare($sql_itens);
    $stmt_itens->bind_param("i", $id_pedido);
    $stmt_itens->execute();
    $resultado_itens = $stmt_itens->get_result();
    
    while ($item = $resultado_itens->fetch_assoc()) {
        $id_produto = $item['id_produto'];
        $qtd_produto = $item['quantidade'];
        $id_item_pedido = $item['id_item_pedido'];
        
        // Buscar ingredientes base
        $sql_ingredientes = "
            SELECT pi.id_ingrediente, pi.quantidade_ingrediente
            FROM produto_ingrediente pi
            WHERE pi.id_produto = ?
        ";
        $stmt_ing = $conexao->prepare($sql_ingredientes);
        $stmt_ing->bind_param("i", $id_produto);
        $stmt_ing->execute();
        $resultado_ing = $stmt_ing->get_result();
        
        while ($ingrediente = $resultado_ing->fetch_assoc()) {
            $id_ingrediente = $ingrediente['id_ingrediente'];
            $qtd_estornar = $ingrediente['quantidade_ingrediente'] * $qtd_produto;
            
            // CREDITAR estoque (devolver)
            $sql_credito = "UPDATE ingrediente SET quantidade_estoque = quantidade_estoque + ? WHERE id_ingrediente = ?";
            $stmt_credito = $conexao->prepare($sql_credito);
            $stmt_credito->bind_param("ii", $qtd_estornar, $id_ingrediente);
            $stmt_credito->execute();
            $stmt_credito->close();
        }
        $stmt_ing->close();
        
        // 2. Estornar personalizações (extras e removidos)
        $sql_pers = "
            SELECT ipp.ingrediente_nome, ipp.tipo
            FROM item_pedido_personalizacao ipp
            WHERE ipp.id_item_pedido = ?
        ";
        $stmt_pers = $conexao->prepare($sql_pers);
        $stmt_pers->bind_param("i", $id_item_pedido);
        $stmt_pers->execute();
        $resultado_pers = $stmt_pers->get_result();
        
        while ($pers = $resultado_pers->fetch_assoc()) {
            // Buscar ID do ingrediente pelo nome
            $sql_id_ing = "SELECT id_ingrediente FROM ingrediente WHERE nome_ingrediente = ?";
            $stmt_id = $conexao->prepare($sql_id_ing);
            $stmt_id->bind_param("s", $pers['ingrediente_nome']);
            $stmt_id->execute();
            $res_id = $stmt_id->get_result();
            
            if ($row_id = $res_id->fetch_assoc()) {
                $id_ingrediente = $row_id['id_ingrediente'];
                
                if ($pers['tipo'] === 'extra') {
                    // Se era EXTRA (foi debitado), agora CREDITAR (devolver)
                    $sql_credito_extra = "UPDATE ingrediente SET quantidade_estoque = quantidade_estoque + ? WHERE id_ingrediente = ?";
                    $stmt_cred_ex = $conexao->prepare($sql_credito_extra);
                    $qtd = 1; // Cada registro representa 1 unidade
                    $stmt_cred_ex->bind_param("ii", $qtd, $id_ingrediente);
                    $stmt_cred_ex->execute();
                    $stmt_cred_ex->close();
                    
                } elseif ($pers['tipo'] === 'removido') {
                    // Se era REMOVIDO (foi creditado), agora DEBITAR (remover o crédito)
                    $sql_debito_rem = "UPDATE ingrediente SET quantidade_estoque = quantidade_estoque - ? WHERE id_ingrediente = ?";
                    $stmt_deb_rem = $conexao->prepare($sql_debito_rem);
                    $qtd = 1;
                    $stmt_deb_rem->bind_param("ii", $qtd, $id_ingrediente);
                    $stmt_deb_rem->execute();
                    $stmt_deb_rem->close();
                }
            }
            $stmt_id->close();
        }
        $stmt_pers->close();
    }
    $stmt_itens->close();
    
    // ✅ Marcar pedido como CANCELADO
    $sql_cancela = "UPDATE pedido SET status_pedido = 'cancelado', data_finalizacao = NOW() WHERE id_pedido = ?";
    $stmt_cancela = $conexao->prepare($sql_cancela);
    $stmt_cancela->bind_param("i", $id_pedido);
    $stmt_cancela->execute();
    $stmt_cancela->close();
    
    $conexao->commit();
    
    // Limpar sessão se for o pedido em andamento
    if (isset($_SESSION['admin_pedido_id']) && $_SESSION['admin_pedido_id'] == $id_pedido) {
        unset($_SESSION['admin_pedido_id']);
    }
    
    $_SESSION['sucesso'] = "Pedido #{$id_pedido} cancelado com sucesso. Estoque estornado.";
    header('Location: admin_lista_pedidos_finalizar.php');
    exit();

} catch (Exception $e) {
    $conexao->rollback();
    $_SESSION['erro'] = "Erro ao cancelar pedido: " . $e->getMessage();
    header('Location: admin_lista_pedidos_finalizar.php'); 
    exit();
}
?>