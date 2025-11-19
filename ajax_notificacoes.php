<?php
include "conexao.php";
require_once "require_login.php";

// Buscar todos os pedidos com status 'pendente'
$stmt = $conexao->prepare("
    SELECT 
        p.id_pedido, 
        p.total, 
        u.nome AS nome_cliente, 
        u.apelido AS apelido_cliente, 
        t.nome_tipo_entrega, 
        tp.tipo_pagamento, 
        DATE_FORMAT(p.data_pedido, '%H:%i') AS horario
    FROM pedido p
    JOIN usuario u ON p.id_usuario = u.id_usuario
    JOIN tipo_entrega t ON p.idtipo_entrega = t.idtipo_entrega
    JOIN tipo_pagamento tp ON p.idtipo_pagamento = tp.idtipo_pagamento
    WHERE p.status_pedido = 'pendente'
    ORDER BY p.data_pedido ASC
");
$stmt->execute();
$res = $stmt->get_result();

$notificacoes = [];

while ($row = $res->fetch_assoc()) {
    $pedido = [
        "id_notificacao"   => $row['id_pedido'], // Usando id_pedido como "id da notificação"
        "id_pedido"        => $row['id_pedido'],
        "total"            => $row['total'],
        "nome_cliente"     => $row['nome_cliente'] . " " . $row['apelido_cliente'],
        "nome_tipo_entrega"=> $row['nome_tipo_entrega'],
        "tipo_pagamento"   => $row['tipo_pagamento'],
        "horario"          => $row['horario'],
        "itens"            => []
    ];

    // Buscar itens do pedido
    $stmt_itens = $conexao->prepare("
        SELECT 
            ip.id_item_pedido, 
            ip.quantidade, 
            ip.preco_unitario, 
            pr.nome_produto,
            (
                SELECT caminho_imagem 
                FROM produto_imagem 
                WHERE id_produto = pr.id_produto 
                  AND imagem_principal = 1 
                LIMIT 1
            ) AS imagem_principal
        FROM item_pedido ip
        JOIN produto pr ON ip.id_produto = pr.id_produto
        WHERE ip.id_pedido = ?
    ");
    $stmt_itens->bind_param("i", $row['id_pedido']);
    $stmt_itens->execute();
    $res_itens = $stmt_itens->get_result();

    while ($item = $res_itens->fetch_assoc()) {
        $item_detalhes = [
            "nome_produto"  => $item['nome_produto'],
            "quantidade"    => $item['quantidade'],
            "imagem"        => $item['imagem_principal'],
            "incrementados" => [],
            "reduzidos"     => []
        ];

        // Buscar personalizações de ingredientes para cada item
        $stmt_pers = $conexao->prepare("
            SELECT 
                ip.tipo, 
                ip.ingrediente_nome
            FROM item_pedido_personalizacao ip
            WHERE ip.id_item_pedido = ?
        ");
        $stmt_pers->bind_param("i", $item['id_item_pedido']);
        $stmt_pers->execute();
        $res_pers = $stmt_pers->get_result();

$agrupados_extras = [];
$agrupados_removidos = [];

while ($pers = $res_pers->fetch_assoc()) {
    $nome = $pers['ingrediente_nome'];

    if ($pers['tipo'] === 'extra') {
        if (isset($agrupados_extras[$nome])) {
            $agrupados_extras[$nome]['quantidade']++;
        } else {
            $agrupados_extras[$nome] = [
                "ingrediente_nome" => $nome,
                "quantidade" => 1
            ];
        }
    } elseif ($pers['tipo'] === 'removido') {
        if (isset($agrupados_removidos[$nome])) {
            $agrupados_removidos[$nome]['quantidade']++;
        } else {
            $agrupados_removidos[$nome] = [
                "ingrediente_nome" => $nome,
                "quantidade" => 1
            ];
        }
    }
}

$item_detalhes['incrementados'] = array_values($agrupados_extras);
$item_detalhes['reduzidos']     = array_values($agrupados_removidos);


        $stmt_pers->close();
        $pedido['itens'][] = $item_detalhes;
    }

    $stmt_itens->close();
    $notificacoes[] = $pedido;
}

$stmt->close();

echo json_encode([
    "contagem"     => count($notificacoes),
    "notificacoes" => $notificacoes
]);
?>
