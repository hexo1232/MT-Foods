<?php
session_start();

// ===== 1. Verificar se existe sessão ativa =====
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(204); // Sem conteúdo, nada a fazer
    exit;
}

// ===== 2. Obter origem da requisição =====
$referer = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : '';
$host = $_SERVER['HTTP_HOST'];

// ===== 3. Permitir localhost, 127.0.0.1 e host real =====
$hostsPermitidos = [
    $host,
    'localhost',
    '127.0.0.1'
];

// ===== 4. Aceitar requisições sem Referer (caso sendBeacon não envie) =====
$refererValido = (empty($referer) || in_array($referer, $hostsPermitidos));

// ===== 5. Bloquear requisições externas =====
if (!$refererValido) {
    http_response_code(403); // Acesso negado
    exit('Acesso inválido.');
}

// ===== 6. Executar logout =====
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// ===== 7. Retornar resposta silenciosa =====
http_response_code(200);
exit;
