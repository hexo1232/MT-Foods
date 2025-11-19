<?php
// session_start();

// if (!isset($_SESSION['usuario'])) {
//     // Captura a URL de destino
//     $urlAtual = $_SERVER['REQUEST_URI'];
//     $_SESSION['url_destino'] = $urlAtual;

//     header("Location: login.php");
//     exit;
// }
// // Define a variável $usuario com os dados da sessão
// $usuario = $_SESSION['usuario'];


session_start();

if (!isset($_SESSION['usuario'])) {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Se for chamada AJAX, devolve JSON em vez de redirecionar
    if ($isAjax || strpos($_SERVER['REQUEST_URI'], '.php') !== false) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['erro' => 'Não autenticado']);
        exit;
    }

    // Caso contrário, redireciona normalmente
    $_SESSION['url_destino'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
