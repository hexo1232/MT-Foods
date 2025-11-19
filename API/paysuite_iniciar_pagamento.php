<?php
// Caminho: Restaurante/API/paysuite_iniciar_pagamento.php

require_once __DIR__ . "/../conexao.php";
require_once __DIR__ . "/config_paysuite.php";

// A função agora recebe a referência (reference) e o return_url gerados no pedido_temp
function iniciarPagamentoPaySuite($id_usuario, $valor, $metodo, $telefone, $email, $referencia, $returnUrl) {
    global $conexao;

    // A referência única e o UUID já deveriam ter sido gerados em finalizar_pedido.php
    // Usaremos a $referencia do pedido_temp

    // CORREÇÃO CRÍTICA 1: O UUID é gerado aqui, mas a REFERENCE já vem do pedido_temp.
    $uuid = uniqid('tx_', true); 
    
    // CORREÇÃO CRÍTICA 2: Cria o registro inicial em transacoes usando a REFERENCE do pedido_temp
    $stmt = $conexao->prepare("
        INSERT INTO transacoes (uuid, referencia, valor, metodo, status, id_usuario, data_criacao)
        VALUES (?, ?, ?, ?, 'pendente', ?, NOW())
    ");
    // Tipos: s(uuid), s(referencia), d(valor), s(metodo), i(id_usuario)
    $stmt->bind_param("ssdsi", $uuid, $referencia, $valor, $metodo, $id_usuario);

    if (!$stmt->execute()) {
        return [
            'status' => 'error',
            'mensagem' => 'Falha ao registrar transação (referência ' . $referencia . '): ' . $stmt->error
        ];
    }

    $stmt->close();

    // URL do endpoint de pagamento da PaySuite
    $endpoint = "https://paysuite.tech/api/v1/payments";
    
    // O $returnUrl já vem formatado de finalizar_pedido.php
    $callbackUrl = "https://undebated-man-unrelating.ngrok-free.dev/Restaurante/API/paysuite_callback.php";

    // Monta payload da PaySuite
    $payload = [
        "amount" => $valor,
        "currency" => "MZN",
        "reference" => $referencia, // Usa a referência do pedido_temp
        "callback_url" => $callbackUrl,
        "return_url" => $returnUrl,
        "customer" => [
            "email" => $email,
            "phone" => $telefone
        ],
        "payment_method" => $metodo
    ];

    // Envia requisição à PaySuite
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . PAYSUITE_API_TOKEN
        ]
    ]);
    $response = curl_exec($ch);
    file_put_contents(__DIR__ . "/paysuite_log.txt", date('Y-m-d H:i:s') . " RESPONSE: " . $response . PHP_EOL, FILE_APPEND);


    if (curl_errno($ch)) {
        return [
            'status' => 'error',
            'mensagem' => 'Erro de comunicação com PaySuite: ' . curl_error($ch)
        ];
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resData = json_decode($response, true);

    // Aceita HTTP 200 ou 201 e procura o checkout_url
    if (($http_code === 200 || $http_code === 201) && isset($resData['data']['checkout_url'])) {
        return [
            'status' => 'success',
            'redirectUrl' => $resData['data']['checkout_url'],
            'referencia' => $referencia
        ];
    } else {
        // Se falhar, é crucial retornar o erro e não tentar criar o pedido.
        return [
            'status' => 'error',
            'mensagem' => "Falha ao iniciar pagamento: HTTP $http_code | Resposta inesperada: " . $response
        ];
    }
}