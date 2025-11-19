<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

// Verifica se foi passado um ID válido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redireciona com uma mensagem de erro
    header("Location: ver_pratos.php?msg=erro&detalhe=" . urlencode("ID não especificado."));
    exit;
}

$id_produto = intval($_GET['id']);

// Inicia uma transação para garantir atomicidade
$conexao->begin_transaction();

try {
    // 1. Apagar os arquivos de imagem associados no servidor
    $stmt_imagens = $conexao->prepare("SELECT caminho_imagem FROM produto_imagem WHERE id_produto = ?");
    $stmt_imagens->bind_param("i", $id_produto);
    $stmt_imagens->execute();
    $resultado_imagens = $stmt_imagens->get_result();

    while ($img = $resultado_imagens->fetch_assoc()) {
        $caminho = $img['caminho_imagem'];
        if (file_exists($caminho)) {
            unlink($caminho); // Remove o arquivo físico
        }
    }

    // 2. Apagar os dados dependentes no banco (ordem correta: filhos → pai)

    // A. Apaga os itens do carrinho
    $stmt_carrinho = $conexao->prepare("DELETE FROM item_carrinho WHERE id_produto = ?");
    $stmt_carrinho->bind_param("i", $id_produto);
    $stmt_carrinho->execute();

    // B. Apaga os vínculos com ingredientes
    $stmt_ingredientes = $conexao->prepare("DELETE FROM produto_ingrediente WHERE id_produto = ?");
    $stmt_ingredientes->bind_param("i", $id_produto);
    $stmt_ingredientes->execute();

    // C. Apaga as associações com categorias
    $stmt_categoria = $conexao->prepare("DELETE FROM produto_categoria WHERE id_produto = ?");
    $stmt_categoria->bind_param("i", $id_produto);
    $stmt_categoria->execute();

    // D. Apaga as imagens no banco
    $stmt_imagens_db = $conexao->prepare("DELETE FROM produto_imagem WHERE id_produto = ?");
    $stmt_imagens_db->bind_param("i", $id_produto);
    $stmt_imagens_db->execute();

    // 3. Finalmente, apaga o produto
    $stmt_produto = $conexao->prepare("DELETE FROM produto WHERE id_produto = ?");
    $stmt_produto->bind_param("i", $id_produto);
    $stmt_produto->execute();

    // Confirma a transação
    $conexao->commit();

    // Redireciona para a listagem com sucesso
    header("Location: ver_pratos.php?msg=excluido");
    exit;

} catch (mysqli_sql_exception $e) {
    // Reverte operações em caso de erro
    $conexao->rollback();

    // Redireciona com mensagem detalhada (apenas para debug em dev)
    $erro_msg = urlencode("Erro ao excluir o produto: " . $e->getMessage());
    header("Location: ver_pratos.php?msg=erro&detalhe={$erro_msg}");
    exit;
}
?>
