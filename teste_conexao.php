
<?php
// URL de teste do PayPal (endpoint público)
$url = "https://api-m.sandbox.paypal.com/v1/oauth2/token";

// Inicia cURL
$ch = curl_init($url);

// Define opções
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // garante conexão segura
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Accept: application/json",
    "Accept-Language: en_US",
    "Content-Type: application/x-www-form-urlencoded"
]);

// Simula envio de dados (como se estivesse autenticando)
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

// Executa requisição
$response = curl_exec($ch);

// Captura informações da resposta
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);

// Fecha cURL
curl_close($ch);

// Exibe resultado
if ($error) {
    echo "❌ Erro na conexão: " . $error;
} else {
    echo "✅ Conexão estabelecida com o PayPal!\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Resposta:\n" . $response;
}
?>
