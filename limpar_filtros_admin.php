<?php
// limpar_filtros.php

// Origem esperada (validação simples)
$origem = $_GET['origem'] ?? 'cardapio';
$map = [
    'cardapio'   => 'cardapio.php?modo=admin_pedido',
    'promocoes'  => 'promocoes.php?&modo=admin_pedido'
];

// Apaga os cookies possíveis (faz fallback por segurança)
setcookie('filtros_produtos', '', time() - 3600, "/");
setcookie('filtros_promocoes', '', time() - 3600, "/");

// Se quiser também apagar cookie de aceitou (não recomendado por padrão):
// setcookie('aceitou_cookies', '', time() - 3600, "/");

// Escolhe destino seguro
$dest = $map[$origem] ?? 'cardapio.php?modo=admin_pedido';

// Redireciona para a página sem query string
header("Location: $dest");
exit;
?>


