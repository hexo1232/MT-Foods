<?php
// config/database.php

require_once __DIR__ . '/env_loader.php'; // ← garante que o .env está carregado

try {
    $dsn = $_ENV['DB_DSN'];
    $user = $_ENV['DB_USER'];
    $pass = $_ENV['DB_PASS'];

    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("❌ Erro de conexão com o banco de dados: " . $e->getMessage());
}
