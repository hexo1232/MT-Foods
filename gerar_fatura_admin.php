<?php
// Inclui os ficheiros de conexão e segurança
include "conexao.php";
require_once "require_login.php";

// Define o cabeçalho para PDF/impressão
header("Content-Type: text/html; charset=UTF-8");

// Verifica se o usuário tem permissão de administrador
if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['idperfil'] ?? 0) != 1) {
    echo "Acesso não autorizado.";
    exit;
}

// --- Variáveis de Filtro ---
$id_pedido = filter_input(INPUT_GET, 'id_pedido', FILTER_SANITIZE_NUMBER_INT);
$filtro_data = filter_input(INPUT_GET, 'filtro', FILTER_SANITIZE_STRING);
$is_lote = isset($_GET['lote']) && $_GET['lote'] === 'true';

// Inicialização de variáveis
$pedidos = [];
$ids_pedidos = [];
$titulo_fatura = "Fatura de Pedido";

try {
    // --- 1. CONSTRUÇÃO DA QUERY BASEADA NO FILTRO (Incluindo 3 e 6 meses) ---
    $sql_filtro = "";
    $data_hoje = date('Y-m-d');
    $data_semana_passada = date('Y-m-d', strtotime('-7 days'));
    $data_mes_passado = date('Y-m-d', strtotime('first day of last month'));
    $data_tres_meses = date('Y-m-d', strtotime('-3 months'));
    $data_seis_meses = date('Y-m-d', strtotime('-6 months'));

    if ($id_pedido) {
        $sql_filtro = " AND p.id_pedido = " . $conexao->real_escape_string($id_pedido);
    } elseif ($is_lote) {
        switch ($filtro_data) {
            case 'diario':
                $sql_filtro = " AND DATE(p.data_pedido) = '$data_hoje'";
                $titulo_fatura = "Faturas do Dia: " . (new DateTime())->format('d/m/Y');
                break;
            case 'semanal':
                $sql_filtro = " AND p.data_pedido >= '$data_semana_passada'";
                $titulo_fatura = "Faturas dos Últimos 7 dias";
                break;
            case 'mensal':
                $sql_filtro = " AND p.data_pedido >= '$data_mes_passado'";
                $titulo_fatura = "Faturas do Último Mês";
                break;
            case 'tres_meses':
                $sql_filtro = " AND p.data_pedido >= '$data_tres_meses'";
                $titulo_fatura = "Faturas dos Últimos 3 Meses";
                break;
            case 'seis_meses':
                $sql_filtro = " AND p.data_pedido >= '$data_seis_meses'";
                $titulo_fatura = "Faturas dos Últimos 6 Meses";
                break;
            case 'todos':
            default:
                $sql_filtro = "";
                $titulo_fatura = "Todas as Faturas Entregues";
                break;
        }
    } else {
        echo "Parâmetros de fatura inválidos.";
        exit;
    }

    $sql_pedidos = "
        SELECT
            p.id_pedido, p.data_pedido, p.total, p.telefone, p.email, p.bairro, p.ponto_referencia,
            p.endereco_json,
            u.nome AS nome_cliente, u.apelido AS apelido_cliente,
            t.nome_tipo_entrega, tp.tipo_pagamento
        FROM pedido p
        JOIN usuario u ON p.id_usuario = u.id_usuario
        JOIN tipo_entrega t ON p.idtipo_entrega = t.idtipo_entrega
        JOIN tipo_pagamento tp ON p.idtipo_pagamento = tp.idtipo_pagamento
        WHERE p.status_pedido = 'entregue'
        $sql_filtro
        ORDER BY p.data_pedido DESC
    ";

    $result_pedidos = $conexao->query($sql_pedidos);

    while ($pedido = $result_pedidos->fetch_assoc()) {
        $pedidos[$pedido['id_pedido']] = $pedido;
        $ids_pedidos[] = $pedido['id_pedido'];
    }

    if (empty($pedidos)) {
        echo "Nenhum pedido encontrado para gerar a fatura.";
        exit;
    }

    // --- 2. BUSCAR ITENS E PERSONALIZAÇÕES (Lógica mantida) ---
    $itens_por_pedido = [];
    $personalizacoes_por_item = [];
    $ids_itens = [];

    if (!empty($ids_pedidos)) {
        $placeholders_pedidos = implode(',', array_fill(0, count($ids_pedidos), '?'));
        
        // Buscar Itens
        $sql_itens = "
            SELECT ip.id_item_pedido, ip.id_pedido, ip.quantidade, ip.subtotal, ip.preco_unitario AS preco,
                    p.nome_produto
            FROM item_pedido ip
            JOIN produto p ON ip.id_produto = p.id_produto
            WHERE ip.id_pedido IN ($placeholders_pedidos)
        ";
        $stmt_itens = $conexao->prepare($sql_itens);
        $types_pedidos = str_repeat('i', count($ids_pedidos));
        $stmt_itens->bind_param($types_pedidos, ...$ids_pedidos);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();

        while ($item = $result_itens->fetch_assoc()) {
            $itens_por_pedido[$item['id_pedido']][] = $item;
            $ids_itens[] = $item['id_item_pedido'];
        }
        
        // Buscar Personalizações
        if (!empty($ids_itens)) {
            $placeholders_itens = implode(',', array_fill(0, count($ids_itens), '?'));
            $sql_pers = "
                SELECT ipp.id_item_pedido, ipp.ingrediente_nome, ipp.tipo
                FROM item_pedido_personalizacao ipp
                WHERE ipp.id_item_pedido IN ($placeholders_itens)
            ";
            $stmt_pers = $conexao->prepare($sql_pers);
            $types_itens = str_repeat('i', count($ids_itens));
            $stmt_pers->bind_param($types_itens, ...$ids_itens);
            $stmt_pers->execute();
            $result_pers = $stmt_pers->get_result();

            while ($pers = $result_pers->fetch_assoc()) {
                if (!isset($personalizacoes_por_item[$pers['id_item_pedido']])) {
                    $personalizacoes_por_item[$pers['id_item_pedido']] = [
                        'extra' => [],
                        'removido' => []
                    ];
                }
                if ($pers['tipo'] === 'extra') {
                    $personalizacoes_por_item[$pers['id_item_pedido']]['extra'][] = $pers;
                } elseif ($pers['tipo'] === 'removido') {
                    $personalizacoes_por_item[$pers['id_item_pedido']]['removido'][] = $pers;
                }
            }
        }
    }
    
    // 3. ORGANIZAR OS DADOS (Lógica mantida)
    foreach ($pedidos as $id_pedido => &$pedido) {
        $pedido['itens'] = $itens_por_pedido[$id_pedido] ?? [];
        foreach ($pedido['itens'] as &$item) {
            $item['ingredientes_incrementados'] = $personalizacoes_por_item[$item['id_item_pedido']]['extra'] ?? [];
            $item['ingredientes_reduzidos'] = $personalizacoes_por_item[$item['id_item_pedido']]['removido'] ?? [];
        }
    }
    unset($pedido);
    unset($item);

} catch (Exception $e) {
    echo "Erro ao gerar fatura: " . $e->getMessage();
    exit;
}

// --- 4. GERAÇÃO DO HTML PARA IMPRESSÃO ---
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title><?= $titulo_fatura ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .invoice-container { 
            width: 100%; 
            max-width: 800px; 
            margin: 20px auto; 
            background: #fff; 
            border: 1px solid #ddd; 
            padding: 20px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            page-break-after: always;
        }
        .invoice-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .invoice-title { font-size: 24px; color: #333; }
        .invoice-details p { margin: 5px 0; font-size: 14px; }
        .invoice-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .invoice-table th, .invoice-table td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 14px; }
        .invoice-table th { background-color: #eee; }
        .invoice-total { text-align: right; margin-top: 20px; }
        .invoice-total strong { font-size: 18px; }
        .item-details small { display: block; margin-top: 5px; color: #666; font-style: italic; }
        .lote-title { font-size: 28px; text-align: center; margin-bottom: 30px; }
        
        @media print {
            body { background: none; padding: 0; }
            .invoice-container { margin: 0; border: none; box-shadow: none; padding: 0; }
            .lote-title { page-break-after: avoid; }
        }
    </style>
</head>
<body onload="window.print()">
    <?php if ($is_lote): ?>
        <div class="lote-title"><?= htmlspecialchars($titulo_fatura) ?></div>
    <?php endif; ?>

    <?php foreach ($pedidos as $pedido): ?>
        <div class="invoice-container">
            <div class="invoice-header">
                <div class="invoice-logo">
                    <img src="icones/logo2.png" alt="Logo da Empresa" style="height: 50px; margin-right: 15px;">
                    <div class="invoice-title">FACTURA | Pedido #<?= htmlspecialchars($pedido['id_pedido']) ?></div>
                </div>
                <div class="invoice-details" style="text-align: right;">
                    <p>Data do Pedido: <?= (new DateTime($pedido['data_pedido']))->format('d/m/Y H:i') ?></p>
                    <p>Gerado em: <?= (new DateTime())->format('d/m/Y H:i') ?></p>
                </div>
            </div>

            <div class="invoice-info">
                <p><strong>Cliente:</strong> <?= htmlspecialchars($pedido['nome_cliente'] . ' ' . $pedido['apelido_cliente']) ?></p>
                <p><strong>Contacto:</strong> <?= htmlspecialchars($pedido['email'] ?? 'N/A') ?> / <?= htmlspecialchars($pedido['telefone'] ?? 'N/A') ?></p>
                <p><strong>Entrega:</strong> <?= htmlspecialchars($pedido['nome_tipo_entrega']) ?></p>
                <?php if ($pedido['nome_tipo_entrega'] === 'Delivery'):
                    $endereco_completo = $pedido['bairro'];
                    if (!empty($pedido['ponto_referencia'])) {
                        $endereco_completo .= " (Ponto Ref: " . $pedido['ponto_referencia'] . ")";
                    }
                ?>
                    <p><strong>Morada:</strong> <?= htmlspecialchars($endereco_completo) ?></p>
                <?php endif; ?>
                <p><strong>Método de Pagamento:</strong> <?= htmlspecialchars($pedido['tipo_pagamento']) ?></p>
            </div>

            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qtd</th>
                        <th>Preço Unit.</th>
                        <th>Personalização</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subtotal_pedido = 0;
                    foreach ($pedido['itens'] as $item): 
                        $subtotal_pedido += $item['subtotal'];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($item['nome_produto']) ?></td>
                            <td style="text-align: center;"><?= htmlspecialchars($item['quantidade']) ?></td>
                            <td style="text-align: right;"><?= number_format($item['preco'], 2, ',', '.') ?> MT</td>
                            <td>
                                <?php
                                $personalizacoes = [];
                                if (!empty($item['ingredientes_incrementados'])) {
                                    $counted_extra = array_count_values(array_column($item['ingredientes_incrementados'], 'ingrediente_nome'));
                                    foreach($counted_extra as $nome => $count) {
                                        $personalizacoes[] = "Extra: " . htmlspecialchars($nome) . ($count > 1 ? " (x$count)" : "");
                                    }
                                }
                                if (!empty($item['ingredientes_reduzidos'])) {
                                    $removed_names = array_column($item['ingredientes_reduzidos'], 'ingrediente_nome');
                                    $personalizacoes[] = "Removido: " . htmlspecialchars(implode(', ', $removed_names));
                                }
                                echo empty($personalizacoes) ? 'Nenhuma' : implode('<br>', $personalizacoes);
                                ?>
                            </td>
                            <td style="text-align: right;"><?= number_format($item['subtotal'], 2, ',', '.') ?> MT</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="invoice-total">
                <p>Subtotal (Produtos): <strong><?= number_format($subtotal_pedido, 2, ',', '.') ?> MT</strong></p>
                <p>Taxa de Entrega: <strong>0.00 MT</strong> </p>
                <p style="border-top: 1px solid #333; padding-top: 10px;">
                    Total Pago: <strong><?= number_format($pedido['total'], 2, ',', '.') ?> MT</strong>
                </p>
            </div>
            
             <div style="text-align: center; margin-top: 40px; font-size: 12px; color: #666;">
                Obrigado pela sua preferência!
            </div>
        </div>
    <?php endforeach; ?>
</body>
</html>