<?php
// Caminho: Restaurante/API/helpers.php
require_once 'config_paysuite.php';

function paysuite_request($endpoint, $data) {
    $ch = curl_init(PAYSUITE_BASE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . PAYSUITE_API_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    paysuite_log("Requisição $endpoint: " . json_encode($data));
    paysuite_log("Resposta: " . $response);

    return json_decode($response, true);
}
?>
