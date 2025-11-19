<?php
// pedidos_ativos_contador.php

session_start();
include "conexao.php"; 

header('Content-Type: application/json');

$total_pedidos_ativos = 0;
$id_usuario = isset($_SESSION['usuario']['id_usuario']) ? (int)$_SESSION['usuario']['id_usuario'] : null;

// Garante que apenas usuários logados possam obter a contagem
if (!$id_usuario) {
    http_response_code(401); // Não autorizado
    exit(json_encode(['erro' => 'Usuário não logado.']));
}

try {
    // Consulta segura para contar pedidos com status "pendente" OU "em preparação"
    $stmt = $conexao->prepare("
        SELECT 
            COUNT(id_pedido) AS total_pedidos_ativos
        FROM pedido
        WHERE id_usuario = ? AND status_pedido IN ('pendente', 'Em preparação', 'Saiu Para Entrega', 'Pronto para Retirada')");
    
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $total_pedidos_ativos = (int)$res->fetch_assoc()['total_pedidos_ativos'];
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar contador de pedidos ativos: " . $e->getMessage());
    http_response_code(500);
    $total_pedidos_ativos = 0;
}

// Retorna a contagem total em formato JSON
echo json_encode(['total_pedidos_ativos' => $total_pedidos_ativos]);
?>