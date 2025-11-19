<?php
include "conexao.php";
require_once "require_login.php";

header('Content-Type: application/json');

if (isset($_POST['id_notificacao'])) {
    $id_notificacao = intval($_POST['id_notificacao']);

    $stmt = $conexao->prepare("UPDATE notificacao SET lida = 1 WHERE id_notificacao = ?");
    $stmt->bind_param("i", $id_notificacao);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Erro ao atualizar."]);
    }
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "ID da notificação ausente."]);
}
?>