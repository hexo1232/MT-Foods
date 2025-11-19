<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/paypal-config.php';

use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

try {
    $client = PayPalConfig::getClient();

    if (!isset($_POST['orderID'])) {
        throw new Exception("O ID do pedido não foi enviado.");
    }

    $request = new OrdersCaptureRequest($_POST['orderID']);
    $request->prefer('return=representation');

    $response = $client->execute($request);

    header('Content-Type: application/json');
    echo json_encode(['status' => $response->result->status]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Erro em capture-paypal-order.php: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
