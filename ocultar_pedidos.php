<?php
session_start();
include "conexao.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedidos'])) {
    $ids = $_POST['pedidos'];

    // Sanitizar IDs (apenas números)
    $ids = array_map('intval', $ids);
    $ids_str = implode(',', $ids);

    $sql = "UPDATE pedido SET oculto_cliente = TRUE WHERE id_pedido IN ($ids_str)";
    if ($conexao->query($sql)) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $conexao->error]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Nenhum pedido selecionado"]);
}
