<?php
require_once 'config.php';
require_once '../conexao.php'; // conexão ao seu BD
require_once '../includes/notificacao.php';

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$calculated = hash_hmac('sha256', $payload, 'SEU_WEBHOOK_SECRET_AQUI');

if (!hash_equals($signature, $calculated)) {
    http_response_code(401);
    exit('Assinatura inválida');
}

$data = json_decode($payload, true);
$event = $data['event'];
$payment = $data['data'];

if ($event === 'payment.success') {
    $stmt = $conexao->prepare("UPDATE pedido SET status_pedido = 'pago' WHERE referencia = ?");
    $stmt->bind_param("s", $payment['reference']);
    $stmt->execute();

    enviarNotificacaoPedido($payment['reference'], $conexao);
}

http_response_code(200);
