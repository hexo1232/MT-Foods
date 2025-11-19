<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/paypal-config.php';

use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

try {
    $client = PayPalConfig::getClient();

    // Captura valor total vindo de POST ou JSON
    $total = $_POST['total'] ?? null;

    if ($total === null) {
        // Tenta pegar do corpo JSON
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['total'])) {
            $total = $input['total'];
        }
    }

    if ($total === null) {
        throw new Exception("O valor total não foi enviado.");
    }

    $request = new OrdersCreateRequest();
    $request->prefer('return=representation');
    $request->body = [
        "intent" => "CAPTURE",
        "purchase_units" => [
            [
                "amount" => [
                    "currency_code" => "USD", // USD no sandbox
                    "value" => number_format($total, 2, '.', '')
                ]
            ]
        ]
    ];

    $response = $client->execute($request);

    header('Content-Type: application/json');
    echo json_encode(['id' => $response->result->id]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Erro em create-paypal-order.php: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
