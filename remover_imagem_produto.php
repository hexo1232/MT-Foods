<?php
include "conexao.php";
ob_start(); // Evita erros de cabeçalho
session_start();

$id_imagem = intval($_GET['id_imagem']);
$id_produto = intval($_GET['id_produto']);

$stmt = $conexao->prepare("SELECT caminho_imagem FROM produto_imagem WHERE id_imagem = ?");
$stmt->bind_param("i", $id_imagem);
$stmt->execute();
$result = $stmt->get_result();
$img = $result->fetch_assoc();

if ($img && file_exists($img['caminho_imagem'])) {
    unlink($img['caminho_imagem']); // Remove o arquivo do servidor
}

$conexao->query("DELETE FROM produto_imagem WHERE id_imagem = $id_imagem");

header("Location: editarproduto.php?id=$id_produto&imagemRemovida=1");
exit;

?>