<?php
// carrinho_contador.php

session_start();
include "conexao.php"; // Certifique-se de que a conexão esteja segura (mysqli ou PDO)

header('Content-Type: application/json');

$total_itens = 0;
$id_usuario = isset($_SESSION['usuario']['id_usuario']) ? (int)$_SESSION['usuario']['id_usuario'] : null;

// 1. Lógica para Usuários Logados (Contagem no Banco de Dados)
if ($id_usuario) {
    try {
        // Query para encontrar o carrinho ativo e somar a quantidade de todos os itens nele.
        $stmt = $conexao->prepare("
            SELECT 
                SUM(ic.quantidade) AS total_itens
            FROM carrinho c
            JOIN item_carrinho ic ON c.id_carrinho = ic.id_carrinho
            WHERE c.id_usuario = ? AND c.status = 'activo'
        ");
        
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $total_itens = (int)$res->fetch_assoc()['total_itens'];
        }
        
    } catch (Exception $e) {
        // Em caso de erro, retorna 0 e loga o erro no servidor.
        error_log("Erro ao buscar contador do carrinho: " . $e->getMessage());
        $total_itens = 0;
    }
}
// Se o usuário não estiver logado, $total_itens permanece 0,
// e a contagem será feita via JavaScript (localStorage).

// 2. Retorna a contagem em formato JSON
echo json_encode(['total_itens' => $total_itens]);

?>