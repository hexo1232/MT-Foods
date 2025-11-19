<?php
// include "conexao.php";

// // 1. Receber o ID do pedido do cliente (você deve saber qual pedido o cliente está rastreando)
// $id_pedido = isset($_GET['id']) ? intval($_GET['id']) : 0;

// if ($id_pedido === 0) {
//     http_response_code(400);
//     echo json_encode(['error' => 'ID de pedido inválido.']);
//     exit();
// }

// // 2. Consulta para obter o status detalhado e o status de notificação
// $sql = "
//     SELECT
//         p.notificacao_vista,
//         p.status_pedido,
//         (
//             SELECT status_pedido2
//             FROM rastreamento_pedido
//             WHERE id_pedido = p.id_pedido
//             ORDER BY data_hora DESC
//             LIMIT 1
//         ) AS status_detalhado
//     FROM pedido p
//     WHERE p.id_pedido = ?
//     LIMIT 1
// ";

// $stmt = $conexao->prepare($sql);
// $stmt->bind_param("i", $id_pedido);
// $stmt->execute();
// $result = $stmt->get_result();

// if ($result->num_rows > 0) {
//     $pedido = $result->fetch_assoc();
    
//     // 3. Preparar a resposta JSON
//     $response = [
//         'success' => true,
//         // Usamos o status_detalhado para mostrar a fase (pronto_para_retirada, etc.)
//         'status_detalhado' => $pedido['status_detalhado'], 
//         // notificacao_vista = 0 indica que é nova para o cliente (gatilho do popup)
//         'notificacao_nova' => ($pedido['notificacao_vista'] == 0) 
//     ];

//     // Se a notificação for nova, já a marcamos como lida NO BANCO DE DADOS
//     // para que não apareça novamente no próximo polling.
//     if ($pedido['notificacao_vista'] == 0) {
//         $stmt_update = $conexao->prepare("UPDATE pedido SET notificacao_vista = 1 WHERE id_pedido = ?");
//         $stmt_update->bind_param("i", $id_pedido);
//         $stmt_update->execute();
//     }
    
//     header('Content-Type: application/json');
//     echo json_encode($response);
    
// } else {
//     http_response_code(404);
//     echo json_encode(['error' => 'Pedido não encontrado.']);
// }

// $stmt->close();
// $conexao->close();
?>

<?php
include "conexao.php";

// 1. Receber o ID do pedido do cliente (você deve saber qual pedido o cliente está rastreando)
$id_pedido = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_pedido === 0) {
http_response_code(400);
echo json_encode(['error' => 'ID de pedido inválido.']);
exit();
}

// 2. Consulta para obter o status detalhado e o status de notificação
$sql = "
    SELECT
        p.notificacao_vista,
        p.status_pedido,
        (
            SELECT status_pedido2
            FROM rastreamento_pedido
            WHERE id_pedido = p.id_pedido
            ORDER BY data_hora DESC
            LIMIT 1
        ) AS status_detalhado
    FROM pedido p
    WHERE p.id_pedido = ?
    LIMIT 1
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
$pedido = $result->fetch_assoc();
// 3. Preparar a resposta JSON
$response = [
'success' => true,
// Usamos o status_detalhado para mostrar a fase (pronto_para_retirada, etc.)
'status_detalhado' => $pedido['status_detalhado'], 
// notificacao_vista = 0 indica que é nova para o cliente (gatilho do popup)
'notificacao_nova' => ($pedido['notificacao_vista'] == 0) 
 ];

header('Content-Type: application/json');
echo json_encode($response);
} else {
http_response_code(404);
echo json_encode(['error' => 'Pedido não encontrado.']);
}

$stmt->close();
$conexao->close();
?>