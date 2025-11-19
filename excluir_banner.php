<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Busca para deletar imagem do servidor
    $sql = "SELECT caminho_imagem FROM banner_site WHERE id_banner = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $banner = $res->fetch_assoc();

    if ($banner && file_exists($banner['caminho_imagem'])) {
        unlink($banner['caminho_imagem']);
    }

    // Exclui do banco
    $sql_del = "DELETE FROM banner_site WHERE id_banner = ?";
    $stmt_del = $conexao->prepare($sql_del);
    $stmt_del->bind_param("i", $id);
    $stmt_del->execute();
}

header("Location: gerenciar_banner.php");
exit;
?>
