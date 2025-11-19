<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

// Verifica se foi passado um ID válido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "ID do ingrediente não especificado.";
    exit;
}

$id_ingrediente = intval($_GET['id']);

// Busca as imagens relacionadas para remover do servidor
$stmt = $conexao->prepare("SELECT caminho_imagem FROM ingrediente_imagem WHERE id_ingrediente = ?");
$stmt->bind_param("i", $id_ingrediente);
$stmt->execute();
$resultado = $stmt->get_result();

while ($img = $resultado->fetch_assoc()) {
    $caminho = $img['caminho_imagem'];
    if (file_exists($caminho)) {
        unlink($caminho); // Remove o arquivo da pasta
    }
}

// Exclui as imagens do banco de dados
$conexao->query("DELETE FROM ingrediente_imagem WHERE id_ingrediente = $id_ingrediente");

// Exclui o produto da tabela
$stmt = $conexao->prepare("DELETE FROM ingrediente WHERE id_ingrediente = ?");
$stmt->bind_param("i", $id_ingrediente);
$stmt->execute();

// Redireciona para a listagem com mensagem
header("Location: ver_ingredientes.php?msg=excluido");
exit;
if (isset($_GET['msg']) && $_GET['msg'] == 'excluido'): ?>
    <p style="color:#155724; background-color: #d4edda; max-width: 500px; margin: 20px auto;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
    ">✅Produto excluído com sucesso!</p>
<?php endif; ?>
