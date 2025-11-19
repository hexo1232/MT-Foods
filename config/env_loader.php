<?php
// config/env_loader.php

require_once __DIR__ . '/../vendor/autoload.php'; // ← carrega as dependências

use Dotenv\Dotenv;

// Carrega o .env na raiz do projeto
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
