<?php
// Tenta pegar as variáveis de ambiente do Railway (preferencial)
// Se não existirem (teste local), usa os novos valores fixos fornecidos.

// --- Variáveis de Conexão ---
$host = getenv('RAILWAY_TCP_PROXY_DOMAIN') ?: "crossover.proxy.rlwy.net";
$usuario = getenv('MYSQLUSER') ?: "root";
$password = getenv('MYSQLPASSWORD') ?: "ZllVgNdyPpkdkoVOqsgKyhdfASpRqzao";
$bd = getenv('MYSQL_DATABASE') ?: "railway"; // Use o nome do BD fornecido
$port = getenv('RAILWAY_TCP_PROXY_PORT') ?: "28388"; // Use a porta do Proxy TCP

// Garante que a porta seja um INT (necessário para o construtor do mysqli)
$port = (int)$port;

// --- Estabelecimento da Conexão ---
// Ordem correta dos parâmetros: Host, User, Pass, DB, Port
$conexao = new mysqli($host, $usuario, $password, $bd, $port);

if ($conexao->connect_error) {
    // Em produção, evite exibir o erro detalhado para o usuário final
    error_log("Erro de conexão: " . $conexao->connect_error);
    die("Desculpe, estamos com problemas técnicos. Código de erro: " . $conexao->connect_errno);
}

// Define o charset para evitar problemas com caracteres especiais (opcional, mas bom)
$conexao->set_charset("utf8mb4");

// --- CORREÇÃO DO ERRO FATAL DE GROUP BY ---
// Esta linha remove o modo restrito 'ONLY_FULL_GROUP_BY' da sessão atual.
$conexao->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

?>
