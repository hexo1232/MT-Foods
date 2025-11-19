
<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

// Verifica se o ID foi enviado
if (!isset($_GET['id_usuario'])) {
    die("ID do produto não informado.");
}

$id = $_GET['id_usuario'];

// Prepara e executa o DELETE com segurança
$stmt = $conexao->prepare("DELETE FROM usuario WHERE id_usuario = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Usuario excluído com sucesso!";
} else {
    echo "Usuario não encontrado ou já foi excluído.";
}

   // Redireciona para a listagem com mensagem de sucesso
    header("Location: ver_pratos.php?msg=excluido");
    exit;

$conexao->close();
?>
