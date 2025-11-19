<?php

include "conexao.php"; 
// NOTA: Certifique-se de que todas as dependências (como 'require_login.php') sejam incluídas conforme necessário.

$alerta_sql = "SELECT nome_ingrediente, quantidade_estoque FROM ingrediente WHERE quantidade_estoque <= 20 ORDER BY quantidade_estoque ASC";
$alerta_resultado = $conexao->query($alerta_sql);

$ingredientes_alerta = [];
$dashboard_alerta_tipo = '';

if ($alerta_resultado->num_rows > 0) {
    $alerta_vermelho_presente = false;
    while ($ing = $alerta_resultado->fetch_assoc()) {
        $nivel = '';
        if ($ing['quantidade_estoque'] < 10) {
            $nivel = 'vermelho';
            $alerta_vermelho_presente = true;
        } elseif ($ing['quantidade_estoque'] <= 20) {
            $nivel = 'laranja';
        }
        
        if ($nivel) {
            $ingredientes_alerta[] = [
                'nome' => $ing['nome_ingrediente'],
                'estoque' => $ing['quantidade_estoque'],
                'nivel' => $nivel
            ];
        }
    }
    $dashboard_alerta_tipo = $alerta_vermelho_presente ? 'vermelho' : 'laranja';
}

header('Content-Type: application/json');
echo json_encode([
    'alertas' => $ingredientes_alerta, 
    'tipo' => $dashboard_alerta_tipo
]);

?>