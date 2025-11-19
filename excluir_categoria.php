
<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

// Verifica se o ID foi enviado
if (!isset($_GET['id_categoria'])) {
    die("ID da categoria não informado.");
}

$id = $_GET['id_categoria'];

// Inicia uma transação para garantir a atomicidade das operações
$conexao->begin_transaction();

try {
    // 1. Encontra e exclui os produtos associados a esta categoria.
    // Observação: este código exclui os produtos da tabela `produto`.
    // Caso um produto pertença a múltiplas categorias, o correto seria
    // excluir apenas a associação na tabela `produto_categoria`.
    $sql_produtos = "
        SELECT id_produto 
        FROM produto_categoria 
        WHERE id_categoria = ?
    ";
    $stmt_produtos = $conexao->prepare($sql_produtos);
    $stmt_produtos->bind_param("i", $id);
    $stmt_produtos->execute();
    $resultado_produtos = $stmt_produtos->get_result();
    $stmt_produtos->close();

    // Exclui as associações primeiro para evitar erros de chave estrangeira
    $sql_delete_associacoes = "DELETE FROM produto_categoria WHERE id_categoria = ?";
    $stmt_delete_associacoes = $conexao->prepare($sql_delete_associacoes);
    $stmt_delete_associacoes->bind_param("i", $id);
    $stmt_delete_associacoes->execute();
    $stmt_delete_associacoes->close();

    if ($resultado_produtos->num_rows > 0) {
        $sql_delete_produto = "DELETE FROM produto WHERE id_produto = ?";
        $stmt_delete_produto = $conexao->prepare($sql_delete_produto);

        while ($produto = $resultado_produtos->fetch_assoc()) {
            $stmt_delete_produto->bind_param("i", $produto['id_produto']);
            $stmt_delete_produto->execute();
        }
        $stmt_delete_produto->close();
    }

    // 2. Exclui a própria categoria
    $sql_delete_categoria = "DELETE FROM categoria WHERE id_categoria = ?";
    $stmt_categoria = $conexao->prepare($sql_delete_categoria);
    $stmt_categoria->bind_param("i", $id);
    $stmt_categoria->execute();

    if ($stmt_categoria->affected_rows > 0) {
        $conexao->commit();
        // Redireciona com mensagem de sucesso
        header("Location: ver_categorias.php?msg=excluido");
        exit;
    } else {
        throw new Exception("Categoria não encontrada ou já foi excluída.");
    }

} catch (Exception $e) {
    // Se algo deu errado, desfaz todas as alterações
    $conexao->rollback();
    // Redireciona com mensagem de erro
    header("Location: ver_categorias.php?msg=erro");
    exit;
}

$conexao->close();
?>
