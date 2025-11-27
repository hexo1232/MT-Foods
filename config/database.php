<?php
// config/database.php - VERSÃO CORRIGIDA

require_once __DIR__ . '/env_loader.php';

try {
    $dsn = $_ENV['DB_DSN'];
    $user = $_ENV['DB_USER'];
    $pass = $_ENV['DB_PASS'];

    // Criar conexão PDO
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ========================================
    // ✅ CORREÇÃO DO PROBLEMA DE TIMEZONE
    // ========================================
    
    // 1. Definir timezone do PHP (Moçambique = UTC+2)
    date_default_timezone_set('Africa/Maputo');
    
    // 2. Sincronizar MySQL com o mesmo timezone
    // Isso garante que NOW() retorne a mesma hora em PHP e MySQL
    $pdo->exec("SET time_zone = '+02:00'");
    
    // ALTERNATIVA: Se preferir usar UTC em todo o sistema:
    // date_default_timezone_set('UTC');
    // $pdo->exec("SET time_zone = '+00:00'");
    
    // ========================================
    // ✅ CONFIGURAÇÕES ADICIONAIS DE SEGURANÇA
    // ========================================
    
    // Usar prepared statements emulados (mais seguro)
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Retornar arrays associativos por padrão
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Desabilitar autocommit para permitir transações
    $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
    
    // Definir charset UTF-8
    $pdo->exec("SET NAMES utf8mb4");
    
    
} catch (PDOException $e) {
    // Em produção, não exponha detalhes do erro
    if ($_ENV['APP_ENV'] === 'production') {
        die("❌ Erro ao conectar ao banco de dados. Entre em contato com o suporte.");
    } else {
        die("❌ Erro de conexão com o banco de dados: " . $e->getMessage());
    }
}
?>