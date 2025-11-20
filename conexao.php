<?php
// Tenta pegar as variáveis do ambiente (Railway Variables)
// Se não existirem, usa os valores fixos (apenas para teste local)
$host = getenv('DB_HOST') ?: "turntable.proxy.rlwy.net";
$usuario = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASSWORD') ?: "nnttbKnlsepXHfAwsDOuVOlkkGvngAkN";
$bd = getenv('DB_NAME') ?: "restaurante";
$port = getenv('DB_PORT') ?: "56276"; // Atenção: Porta deve ser INT se possível, mas string funciona na maioria

// Ordem correta dos parâmetros: Host, User, Pass, DB, Port
$conexao = new mysqli($host, $usuario, $password, $bd, $port);

if ($conexao->connect_error) {
    // Em produção, evite exibir o erro detalhado para o usuário final
    error_log("Erro de conexão: " . $conexao->connect_error);
    die("Desculpe, estamos com problemas técnicos.");
}

// --- CORREÇÃO DO ERRO FATAL DE GROUP BY ---
// Esta linha remove o modo restrito 'ONLY_FULL_GROUP_BY' da sessão atual.
// Isso resolve o erro "Expression #3 ... contains nonaggregated column" no index.php.
$conexao->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

?>
