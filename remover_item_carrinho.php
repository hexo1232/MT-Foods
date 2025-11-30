
<?php
session_start();
include "conexao.php"; // Certifica-se de que este arquivo contém a conexão com o banco de dados

// Verifica se o UUID do item foi passado via URL
if (!isset($_GET['uuid']) || empty($_GET['uuid'])) {
    header("Location: ver_carrinho.php?msg=erro&detalhe=Item não especificado.");
    exit;
}

$uuid_item = $_GET['uuid'];

// --- Lógica para Usuário Logado (Carrinho no Banco de Dados) ---
if (isset($_SESSION['usuario']['id_usuario'])) {
    $id_usuario = $_SESSION['usuario']['id_usuario'];
    $conexao->begin_transaction(); // Inicia a transação

    try {
        // 1. Localiza o id_item_carrinho com base no UUID e id do usuário
        $stmt_select = $conexao->prepare("
            SELECT ic.id_item_carrinho
            FROM item_carrinho ic
            JOIN carrinho c ON ic.id_carrinho = c.id_carrinho
            WHERE c.id_usuario = ? AND ic.uuid = ? AND c.status = 'activo'
        ");
        $stmt_select->bind_param("is", $id_usuario, $uuid_item);
        $stmt_select->execute();
        $resultado = $stmt_select->get_result();

        if ($resultado->num_rows > 0) {
            $item_info = $resultado->fetch_assoc();
            $id_item_carrinho = $item_info['id_item_carrinho'];

            // 2. Apaga primeiro os ingredientes associados ao item para evitar erro de chave estrangeira
            $stmt_delete_ingredientes = $conexao->prepare("DELETE FROM carrinho_ingrediente WHERE id_item_carrinho = ?");
            $stmt_delete_ingredientes->bind_param("i", $id_item_carrinho);
            $stmt_delete_ingredientes->execute();

            // 3. Em seguida, apaga o item do carrinho
            $stmt_delete_item = $conexao->prepare("DELETE FROM item_carrinho WHERE id_item_carrinho = ?");
            $stmt_delete_item->bind_param("i", $id_item_carrinho);
            $stmt_delete_item->execute();
            
            $conexao->commit(); // Finaliza a transação com sucesso
            header("Location: ver_carrinho.php?msg=removido");
            exit;
        } else {
            // Se o item não for encontrado, redireciona com mensagem de erro
            $conexao->rollback(); // Reverte a transação mesmo que nada tenha sido feito para segurança
            header("Location: ver_carrinho.php?msg=erro&detalhe=Item não encontrado.");
            exit;
        }
    } catch (Exception $e) {
        $conexao->rollback(); // Reverte a transação em caso de erro
        error_log("Erro ao remover item do carrinho: " . $e->getMessage()); // Registra o erro no log
        header("Location: ver_carrinho.php?msg=erro&detalhe=Erro ao remover o item do carrinho.");
        exit;
    }
}
// --- Lógica para Usuário Não Logado (Carrinho na Sessão) ---
else {
    if (isset($_SESSION['carrinho'])) {
        foreach ($_SESSION['carrinho'] as $index => $item) {
            // Verifica se o UUID do item na sessão corresponde ao do URL
            if ($item['uuid'] === $uuid_item) {
                // Remove o item da sessão
                unset($_SESSION['carrinho'][$index]);
                break; // Sai do loop após encontrar e remover o item
            }
        }
        // Reorganiza os índices do array da sessão para evitar lacunas
        $_SESSION['carrinho'] = array_values($_SESSION['carrinho']);
    }
    header("Location: ver_carrinho.php");
    exit;
}
?>
