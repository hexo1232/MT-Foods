<?php
// pedidos_finalizados_contador.php - CORRIGIDO

session_start();
include "conexao.php"; 

header('Content-Type: application/json');

$total_nao_vistos = 0;
// Verificamos o id_usuario de forma segura
$id_usuario = isset($_SESSION['usuario']['id_usuario']) ? (int)$_SESSION['usuario']['id_usuario'] : 0;

if ($id_usuario === 0) {
    // Retorna 0 e evita código de erro, pois pode ser chamado por um cliente não logado
    // mas o badge deve ficar oculto.
    echo json_encode(['total_finalizados_nao_vistos' => 0]);
    exit();
}

try {
    // CORREÇÃO 1: Usar '?' como placeholder e adicionar um alias (AS total_nao_vistos)
    $stmt = $conexao->prepare("
        SELECT COUNT(id_pedido) AS total_nao_vistos FROM pedido 
        WHERE id_usuario = ? 
        AND status_pedido = 'entregue' 
        AND notificacao_vista = 0
    ");
 
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    
    // CORREÇÃO 2: Ler o resultado corretamente usando o alias 'total_nao_vistos'
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $total_nao_vistos = (int)$row['total_nao_vistos'];
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Erro ao buscar contador de finalizados: " . $e->getMessage());
    http_response_code(500);
    $total_nao_vistos = 0;
}

echo json_encode(['total_finalizados_nao_vistos' => $total_nao_vistos]);
?>