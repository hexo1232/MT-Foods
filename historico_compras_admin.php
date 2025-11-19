<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// A variável de sessão pode ser um array ou um valor simples, então é mais seguro extrair o ID.
$usuario = $_SESSION['usuario'] ?? null;
$id_usuario = $usuario['id_usuario'] ?? 0;
$is_admin = ($usuario && ($usuario['idperfil'] ?? 0) == 1); 

if (!$is_admin) {
    // Redireciona se não for administrador
    header("Location: dashboard.php");
    exit;
}

// --- LÓGICA DE FILTRAGEM DE DATAS ATUALIZADA ---
$filtro_data = $_GET['filtro'] ?? 'todos';
$sql_filtro = "";
$data_hoje = date('Y-m-d');
$data_semana_passada = date('Y-m-d', strtotime('-7 days'));
$data_mes_passado = date('Y-m-d', strtotime('first day of last month'));
$data_tres_meses = date('Y-m-d', strtotime('-3 months')); // Novo
$data_seis_meses = date('Y-m-d', strtotime('-6 months')); // Novo

switch ($filtro_data) {
    case 'diario':
        $sql_filtro = " AND DATE(p.data_pedido) = '$data_hoje'";
        break;
    case 'semanal':
        $sql_filtro = " AND p.data_pedido >= '$data_semana_passada'";
        break;
    case 'mensal':
        $sql_filtro = " AND p.data_pedido >= '$data_mes_passado'";
        break;
    case 'tres_meses': // Novo Filtro
        $sql_filtro = " AND p.data_pedido >= '$data_tres_meses'";
        break;
    case 'seis_meses': // Novo Filtro
        $sql_filtro = " AND p.data_pedido >= '$data_seis_meses'";
        break;
    case 'todos':
    default:
        $sql_filtro = "";
        $filtro_data = 'todos';
        break;
}

if ($id_usuario) {
    try {
        $stmt = $conexao->prepare("
            UPDATE pedido 
            SET notificacao_vista = 1 
            WHERE id_usuario = ? AND status_pedido = 'entregue' AND notificacao_vista = 0
        ");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Erro ao marcar pedidos como vistos: " . $e->getMessage());
    }
}

$pedidos = [];
$ids_pedidos = [];
$ids_itens = [];
$itens_por_pedido = [];
$personalizacoes_por_item = [];

try {

$sql_pedidos = "
    SELECT
        p.id_pedido,
        p.data_pedido,
        p.total,
        p.idtipo_entrega,
        u.nome AS nome_cliente,
        u.apelido AS apelido_cliente,
        t.nome_tipo_entrega,
        tp.tipo_pagamento,
        p.endereco_json,
        p.bairro,
        p.ponto_referencia,
        (
            SELECT status_pedido2
            FROM rastreamento_pedido
            WHERE id_pedido = p.id_pedido
            ORDER BY data_hora DESC
            LIMIT 1
        ) AS status_detalhado
    FROM pedido p
    JOIN usuario u ON p.id_usuario = u.id_usuario
    JOIN tipo_entrega t ON p.idtipo_entrega = t.idtipo_entrega
    JOIN tipo_pagamento tp ON p.idtipo_pagamento = tp.idtipo_pagamento
    WHERE p.status_pedido = 'entregue'
    $sql_filtro
    ORDER BY p.data_pedido DESC
";

$stmt_pedidos = $conexao->prepare($sql_pedidos);
$stmt_pedidos->execute();
$result_pedidos = $stmt_pedidos->get_result();

    while ($pedido = $result_pedidos->fetch_assoc()) {
        $pedidos[$pedido['id_pedido']] = $pedido;
        $ids_pedidos[] = $pedido['id_pedido'];
    }

    // 2. Busca todos os itens (lógica mantida)
    if (!empty($ids_pedidos)) {
        $placeholders_pedidos = implode(',', array_fill(0, count($ids_pedidos), '?'));
        $sql_itens = "
            SELECT ip.id_item_pedido, ip.id_pedido, ip.quantidade, ip.subtotal, ip.preco_unitario AS preco,
                    p.nome_produto, (SELECT caminho_imagem FROM produto_imagem 
                    WHERE id_produto = p.id_produto AND imagem_principal = 1 LIMIT 1) AS imagem_principal
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
    }

    // 3. Busca todas as personalizações (lógica mantida)
    if (!empty($ids_itens)) {
        $placeholders_itens = implode(',', array_fill(0, count($ids_itens), '?'));
        $sql_pers = "
            SELECT ipp.id_item_pedido, ipp.ingrediente_nome, ipp.tipo, ii.caminho_imagem
            FROM item_pedido_personalizacao ipp
            JOIN ingrediente i ON ipp.ingrediente_nome = i.nome_ingrediente
            LEFT JOIN ingrediente_imagem ii 
                ON i.id_ingrediente = ii.id_ingrediente AND ii.imagem_principal = 1
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

    // 4. Organiza todos os dados (lógica mantida)
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
    echo "<p style='color:red;'>Erro ao carregar histórico de compras: " . $e->getMessage() . "</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Histórico de Compras - Admin</title>
    <link rel="stylesheet" href="css/cliente.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .filtros {
            margin: 20px;
            padding: 10px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filtros button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .filtros button.active {
            background-color: #4CAF50; /* Cor primária */
            color: white;
        }
        .filtros button:not(.active):hover {
            background-color: #e2e8f0;
        }
        .print-btn {
             background-color: #007bff; /* Cor para impressão */
             color: white;
        }
        .print-btn:hover {
             background-color: #0056b3 !important;
        }
        .pedido-footer {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #ccc;
            text-align: right;
        }
        .print-btn-individual {
            background-color: #ffc107;
            color: #333;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .print-btn-individual:hover {
            background-color: #e0a800;
        }
    </style>
    <script src="logout_auto.js"></script>
    <script src="js/darkmode2.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/dropdown2.js"></script>
</head>
<body>

<button class="menu-btn">☰</button>

<div class="sidebar-overlay"></div>
    
<sidebar class="sidebar">
    <br><br>
    <a href="dashboard.php">Voltar ao Menu Principal</a>
    <div class="sidebar-user-wrapper">
        <div class="sidebar-user" id="usuarioDropdown">
            <div class="usuario-avatar" style="background-color: <?= $corAvatar ?>;">
                <?= $iniciais ?>
            </div>
            <div class="usuario-dados">
                <div class="usuario-nome"><?= $nome ?></div>
                <div class="usuario-apelido"><?= $apelido ?></div>
            </div>
            <div class="usuario-menu" id="menuPerfil">
                <a href='editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>'>Editar Dados Pessoais</a>
                <a href="alterar_senha2.php">Alterar Senha</a>
                <a href="logout.php">Sair</a>
            </div>
        </div>
        <img class="dark-toggle" id="darkToggle"
            src="icones/lua.png"
            alt="Modo Escuro"
            title="Alternar modo escuro">
    </div>
</sidebar>


<div class="conteudo2">

    <h2 style="padding:0 20px;">Histórico de Compras</h2>
    
    <div class="filtros">
        <label>Filtrar por Período:</label>
        <button onclick="window.location.href='?filtro=todos'" 
            class="<?= $filtro_data === 'todos' ? 'active' : '' ?>">
            Todos
        </button>
        <button onclick="window.location.href='?filtro=diario'" 
            class="<?= $filtro_data === 'diario' ? 'active' : '' ?>">
            Hoje
        </button>
        <button onclick="window.location.href='?filtro=semanal'" 
            class="<?= $filtro_data === 'semanal' ? 'active' : '' ?>">
            Últimos 7 dias
        </button>
        <button onclick="window.location.href='?filtro=mensal'" 
            class="<?= $filtro_data === 'mensal' ? 'active' : '' ?>">
            Último Mês
        </button>
        <button onclick="window.location.href='?filtro=tres_meses'" 
            class="<?= $filtro_data === 'tres_meses' ? 'active' : '' ?>">
            Últimos 3 Meses
        </button>
        <button onclick="window.location.href='?filtro=seis_meses'" 
            class="<?= $filtro_data === 'seis_meses' ? 'active' : '' ?>">
            Últimos 6 Meses
        </button>

        <?php if (!empty($pedidos)): ?>
             <button class="print-btn" onclick="imprimirFaturaLote('<?= $filtro_data ?>')">
                Imprimir Faturas (<?= count($pedidos) ?>)
            </button>
        <?php endif; ?>
    </div>
    <?php if (!empty($pedidos)): ?>
        
        <form id="formPedidos">
            <?php foreach ($pedidos as $pedido): ?>
                <div class="pedido-card" data-id="<?= htmlspecialchars($pedido['id_pedido']) ?>">
                    <div class="pedido-header">
                        <div>
                            <strong>Pedido número <?= $pedido['id_pedido'] ?> </strong> 
                        </div>
                        <div><?= (new DateTime($pedido['data_pedido']))->format('d/m/Y H:i') ?></div>
                    </div>

                    <div class="pedido-details">
                        <p><b>Cliente:</b> <?= htmlspecialchars($pedido['nome_cliente'] . ' ' . $pedido['apelido_cliente']) ?></p>
                        <p><b>Entrega:</b> <?= htmlspecialchars($pedido['nome_tipo_entrega']) ?></p>
                        <p><b>Pagamento:</b> <?= htmlspecialchars($pedido['tipo_pagamento']) ?></p>
                        <p><b>Total pago:</b> <?= number_format($pedido['total'], 2, ',', '.') ?> MZN</p>
                    </div>

                    
                    <div>
                        <?php if (isset($pedido['itens']) && !empty($pedido['itens'])): ?>
                            <?php foreach ($pedido['itens'] as $item): ?>
                                <div class="item-card">
                                    <img src="<?= htmlspecialchars($item['imagem_principal'] ?? 'imagens/sem_imagem.jpg') ?>" alt="Imagem do produto">
                                    <div class="item-details">
                                        <h3><?= htmlspecialchars($item['nome_produto']) ?></h3>
                                        <p>Quantidade: <?= htmlspecialchars($item['quantidade']) ?></p>
                                        <p>Preço do item: <?= number_format($item['subtotal'], 2, ',', '.') ?> MT</p>

                                        <?php if (!empty($item['ingredientes_incrementados'])): ?>
                                            <div class="personalizacoes">
                                                <h4>Ingredientes Extra</h4>
                                                <div class="ingredientes-container">
                                                    <?php $counted_ingredientes = array_count_values(array_column($item['ingredientes_incrementados'], 'ingrediente_nome'));
                                                          $ingredientes_imagens = array_column($item['ingredientes_incrementados'], 'caminho_imagem', 'ingrediente_nome');
                                                    ?>
                                                    <?php foreach ($counted_ingredientes as $ing_nome => $count): ?>
                                                        <div class="ingrediente-card extra">
                                                            <img src="<?= htmlspecialchars($ingredientes_imagens[$ing_nome] ?? 'imagens/sem_foto_ingrediente.png') ?>" alt="Foto do ingrediente">
                                                            <span><?= htmlspecialchars($ing_nome) ?> (x<?= $count ?>)</span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['ingredientes_reduzidos'])): ?>
                                            <div class="personalizacoes">
                                                <h4>Ingredientes Removidos</h4>
                                                <div class="ingredientes-container">
                                                    <?php $counted_ingredientes = array_count_values(array_column($item['ingredientes_reduzidos'], 'ingrediente_nome'));
                                                          $ingredientes_imagens = array_column($item['ingredientes_reduzidos'], 'caminho_imagem', 'ingrediente_nome');
                                                    ?>
                                                    <?php foreach ($counted_ingredientes as $ing_nome => $count): ?>
                                                        <div class="ingrediente-card removido">
                                                            <img src="<?= htmlspecialchars($ingredientes_imagens[$ing_nome] ?? 'imagens/sem_foto_ingrediente.png') ?>" alt="Foto do ingrediente">
                                                            <span><?= htmlspecialchars($ing_nome) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pedido-footer">
                        <button type="button" class="print-btn-individual" 
                            onclick="imprimirFaturaIndividual(<?= $pedido['id_pedido'] ?>)">
                            Imprimir Fatura
                        </button>
                    </div>

                </div>
            <?php endforeach; ?>
        </form>
    <?php else: ?>
        <p class="message-empty">Não há pedidos entregues neste período.</p>
    <?php endif; ?>
    </div>

    <script>
        /**
         * Abre a janela de impressão para uma fatura individual.
         * @param {number} id_pedido O ID do pedido a ser impresso.
         */
        function imprimirFaturaIndividual(id_pedido) {
            const url = `gerar_fatura_admin.php?id_pedido=${id_pedido}`;
            // Abre em uma nova janela para forçar a impressão
            window.open(url, '_blank', 'width=800,height=600'); 
        }

        /**
         * Abre a janela de impressão para um lote de faturas, baseado no filtro de data.
         * @param {string} filtro O filtro de data ('diario', 'semanal', 'mensal', 'tres_meses', 'seis_meses', 'todos').
         */
        function imprimirFaturaLote(filtro) {
            const url = `gerar_fatura_admin.php?filtro=${filtro}&lote=true`;
            // Abre em uma nova janela para forçar a impressão
            window.open(url, '_blank', 'width=800,height=600'); 
        }
        
    </script>
</body>
</html>