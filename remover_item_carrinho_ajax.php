<?php
session_start();
header("Content-Type: application/json");

if (!isset($_GET['uuid'])) {
    echo json_encode(["ok" => false, "erro" => "UUID não informado"]);
    exit;
}

$uuid = $_GET['uuid'];

include "conexao.php";

// ======================================================
// USUÁRIO LOGADO → REMOVER DO BANCO
// ======================================================
if (!empty($_SESSION['usuario']['id_usuario'])) {
    $id_usuario = $_SESSION['usuario']['id_usuario'];

    $sql = "SELECT id_carrinho FROM carrinho WHERE id_usuario=? AND status='activo'";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $id_carrinho = $res['id_carrinho'];

        $sql = "DELETE FROM item_carrinho WHERE uuid=? AND id_carrinho=?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("si", $uuid, $id_carrinho);
        $stmt->execute();

        echo json_encode(["ok" => true]);
        exit;
    }
}

// ======================================================
// VISITANTE → REMOVER DO COOKIE
// ======================================================
if (!empty($_COOKIE['carrinho'])) {

    $carrinho = json_decode(urldecode($_COOKIE['carrinho']), true);

    if (!is_array($carrinho)) {
        $carrinho = [];
    }

    // Remove item pelo UUID
    $carrinho = array_filter($carrinho, function($item) use ($uuid) {
        return ($item['uuid'] ?? null) !== $uuid;
    });

    // Regrava cookie atualizado
    setcookie(
        "carrinho",
        urlencode(json_encode(array_values($carrinho))),
        time() + 86400 * 30,
        "/"
    );

    echo json_encode(["ok" => true]);
    exit;
}

echo json_encode(["ok" => false, "erro" => "Carrinho não encontrado"]);
exit;
