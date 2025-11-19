<?php
include "conexao.php";
require_once "require_login.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$modo_admin = ($_GET['modo'] ?? '') === 'admin_pedido';
$admin_query_param = $modo_admin ? '?modo=admin_pedido' : '';

// A variável de sessão pode ser um array ou um valor simples, então é mais seguro extrair o ID.
$usuario = $_SESSION['usuario'] ?? null;
$id_usuario = $usuario['id_usuario'] ?? 0;
$is_admin = ($usuario && ($usuario['idperfil'] ?? 0) == 1); 

if ($id_usuario) {
    try {
        // Marca todos os pedidos 'entregue' do usuário como VISTOS (1)
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


// --- NOVO: LÓGICA DE FILTRAGEM DE DATAS ---
$filtro_data = $_GET['filtro'] ?? 'todos';
$sql_filtro = "";
$data_hoje = date('Y-m-d');
$data_semana_passada = date('Y-m-d', strtotime('-7 days'));
$data_mes_passado = date('Y-m-d', strtotime('first day of last month'));
$data_tres_meses = date('Y-m-d', strtotime('-3 months')); // NOVO
$data_seis_meses = date('Y-m-d', strtotime('-6 months')); // NOVO

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
    case 'tres_meses': 
        $sql_filtro = " AND p.data_pedido >= '$data_tres_meses'";
        break;
    case 'seis_meses': 
        $sql_filtro = " AND p.data_pedido >= '$data_seis_meses'";
        break;
    case 'todos':
    default:
        $sql_filtro = "";
        $filtro_data = 'todos';
        break;
}
// --- FIM LÓGICA FILTRO ---


$pedidos = [];
$ids_pedidos = [];
$ids_itens = [];
$itens_por_pedido = [];
$personalizacoes_por_item = [];

try {
    // 1. Busca todos os pedidos do usuário (visíveis) com o filtro de data
    $sql_pedidos = "
        SELECT p.*, tp.tipo_pagamento, t.nome_tipo_entrega
        FROM pedido p
        JOIN tipo_pagamento tp ON p.idtipo_pagamento = tp.idtipo_pagamento
        JOIN tipo_entrega t ON p.idtipo_entrega = t.idtipo_entrega
        WHERE p.id_usuario = ? 
        AND p.oculto_cliente = FALSE 
        AND p.status_pedido = 'entregue'
        $sql_filtro 
        ORDER BY p.data_pedido DESC
    ";

    $stmt_pedidos = $conexao->prepare($sql_pedidos);
    $stmt_pedidos->bind_param("i", $id_usuario);
    $stmt_pedidos->execute();
    $result_pedidos = $stmt_pedidos->get_result();

    while ($pedido = $result_pedidos->fetch_assoc()) {
        $pedidos[$pedido['id_pedido']] = $pedido;
        $ids_pedidos[] = $pedido['id_pedido'];
    }

    // 2. Busca todos os itens
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

    // 3. Busca todas as personalizações
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

    // 4. Organiza todos os dados na estrutura final para o HTML
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Compras</title>
    <script src="logout_auto.js"></script>
    <link rel="stylesheet" href="css/cliente.css">
    <script src="js/darkmode1.js"></script>
    <script src="js/dropdown.js"></script> 
    <script src="js/sidebar2.js"></script> 
    <style>
        /* Estilos adicionais para os filtros e botões de impressão */
        .filtros {
            margin: 20px 20px 10px 20px;
            padding: 10px;
            background-color: var(--card-background-color);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filtros label {
             font-weight: bold;
             color: var(--text-color);
        }
        .filtros button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            color: var(--text-color);
            background-color: var(--button-bg-color);
        }
        .filtros button.active {
            background-color: #4CAF50; /* Cor primária */
            color: white;
        }
        .filtros button:not(.active):hover {
            background-color: var(--button-hover-color);
        }
        .print-btn-lote {
            background-color: #007bff; /* Cor para impressão em lote */
            color: white !important;
        }
        .print-btn-lote:hover {
            background-color: #0056b3 !important;
        }
        .print-btn-individual {
            background-color: #ffc107;
            color: #333 !important;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .print-btn-individual:hover {
            background-color: #e0a800;
        }
        .pedido-footer {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed var(--border-color);
            text-align: right;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .acoes {
            margin: 10px 20px; /* Ajusta a margem para não interferir com os filtros */
        }
    </style>
</head>
<body>
    
<header class="topbar">
    <div class="container">
        <button class="menu-btn-mobile" id="menuBtnMobile">&#9776;</button>
        <div class="logo">
            <a href="index.php">
                <img src="icones/logo.png" alt="Logo do Restaurante" class="logo-img">
            </a>
        </div>
        <div class="links-menu">
            <?php if ($usuario): ?>
                <?php if ($is_admin): ?>
                    <a href="cardapio.php?modo=admin_pedido"><img class="icone2" src="icones/voltar1.png" alt="Voltar" title="Voltar">Voltar</a>
                <?php else: ?>
                    <a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Voltar" title="Voltar">Voltar</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="login.php">Fazer Login</a>
                <a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Voltar" title="Voltar">Voltar ao Cardápio</a>
            <?php endif; ?>
        </div>

        <div class="acoes-usuario">
            <img id="darkToggle" class="dark-toggle" src="icones/lua.png" alt="Modo escuro">
            <?php if ($usuario): ?>
                <?php
                    $nome2 = $usuario['nome'] ?? '';
                    $apelido = $usuario['apelido'] ?? '';
                    $iniciais = strtoupper(substr($nome2,0,1) . substr($apelido,0,1));
                    $nomeCompleto = "$nome2 $apelido";
                    function gerarCor($t){ $h=md5($t); return "rgb(".hexdec(substr($h,0,2)).",".hexdec(substr($h,2,2)).",".hexdec(substr($h,4,2)).")"; }
                    $corAvatar = gerarCor($nomeCompleto);
                ?>
                <div class="usuario-info usuario-desktop" id="usuarioDropdown">
                    <div class="usuario-dropdown">
                        <div class="usuario-iniciais" style="background-color:<?= $corAvatar ?>;"><?= $iniciais ?></div>
                        <div class="usuario-nome"><?= $nomeCompleto ?></div>
                        <div class="menu-perfil" id="menuPerfil">
                            <a href="editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>">Editar Dados Pessoais</a>
                            <a href="alterar_senha2.php">Alterar Senha</a>
                            <a href="logout.php"><img class="iconelogout" src="icones/logout1.png"> Sair</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</header>

<nav id="mobileMenu" class="nav-mobile-sidebar hidden">
    <div class="sidebar-header">
        <button class="close-btn" id="closeMobileMenu">&times;</button>
    </div>
    <ul class="sidebar-links">
       <?php if ($is_admin): ?>
                     <li><a href="cardapio.php?modo=admin_pedido"><img class="icone2" src="icones/voltar1.png" alt="Voltar" title="Voltar">Voltar</a> </li>
                <?php else: ?>

        <li><a href="cardapio.php"> <img class="icone2" src="icones/voltar1.png" alt="Voltar" title="Voltar"> Voltar</a></li>
    </ul>
      <?php endif; ?>

    <?php if ($usuario): ?>
        <div class="sidebar-user-section">
            <div id="sidebarProfileDropdown" class="sidebar-profile-dropdown">
                <a href='editarusuario.php?id_usuario=<?= $usuario["id_usuario"] ?>'>
                    <img class="icone" src="icones/user1.png" alt="Editar" title="Editar"> Editar Dados Pessoais
                </a>
                <a href="alterar_senha2.php">
                    <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar"> Alterar Senha
                </a>
                <a href="logout.php" class="logout-link">
                    <img class="iconelogout" src="icones/logout1.png" alt="Logout" title="Sair"> Sair
                </a>
            </div>
            <div id="sidebarUserProfile" class="sidebar-user-profile">
                <div class="sidebar-user-avatar" style="background-color: <?= $corAvatar ?>;"><?= $iniciais ?></div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= $nome2 ?></div>
                    <div class="sidebar-user-email"><?= $usuario["email"] ?></div>
                </div>
                <span id="sidebarArrow" class="dropdown-arrow">▲</span>
            </div>
        </div>
    <?php endif; ?>
</nav>

<div id="menuOverlay" class="menu-overlay hidden"></div>


<div class="conteudo">

    <h2 style="padding:0 20px;">Histórico de Compras<?= $modo_admin ? ' (Modo Admin)' : '' ?></h2>
    
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
             <button class="print-btn-lote" onclick="imprimirFaturaLote('<?= $filtro_data ?>')">
                Imprimir Faturas (<?= count($pedidos) ?>)
            </button>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($pedidos)): ?>
        <div class="select-all">
            <input type="checkbox" id="selectAll"> <label for="selectAll">Selecionar Todos</label>
        </div>
        <div class="acoes">
            <button type="button" onclick="ocultarSelecionados()">Eliminar Selecionados</button>
        </div>
        <form id="formPedidos">
            
            <?php foreach ($pedidos as $pedido): ?>
                <div class="pedido-card" data-id="<?= htmlspecialchars($pedido['id_pedido']) ?>">
                    <div class="pedido-header">
                        <div>
                            <input type="checkbox" name="pedidos[]" value="<?= $pedido['id_pedido'] ?>">
                            <strong>Pedido número <?= $pedido['id_pedido'] ?></strong> - <?= htmlspecialchars($pedido['status_pedido']) ?>
                        </div>
                        <div><?= (new DateTime($pedido['data_pedido']))->format('d/m/Y H:i') ?></div>
                    </div>
                
                    <div>
                        <?php if (isset($pedido['itens']) && !empty($pedido['itens'])): ?>
                            <?php foreach ($pedido['itens'] as $item): ?>
                                <div class="item-card">
                                    <img src="<?= htmlspecialchars($item['imagem_principal'] ?? 'imagens/sem_imagem.jpg') ?>" alt="Imagem do produto">
                                    <div class="item-details">
                                        <h3><?= htmlspecialchars($item['nome_produto']) ?></h3>
                                        <p>Quantidade: <?= htmlspecialchars($item['quantidade']) ?></p>
                                        <p><b>Entrega:</b> <?= htmlspecialchars($pedido['nome_tipo_entrega']) ?></p>
                                        <strong>Pagamento:</strong> <?= htmlspecialchars($pedido['tipo_pagamento']) ?><br>
                                        <p>Preço do item: <?= number_format($item['subtotal'], 2, ',', '.') ?> MT</p>

                                        <?php if (!empty($item['ingredientes_incrementados'])): ?>
                                            <div class="personalizacoes">
                                                <h4>Ingredientes Extra</h4>
                                                <div class="ingredientes-container">
                                                    <?php
                                                    $counted_ingredientes = array_count_values(array_column($item['ingredientes_incrementados'], 'ingrediente_nome'));
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
                                                    <?php
                                                    $counted_ingredientes = array_count_values(array_column($item['ingredientes_reduzidos'], 'ingrediente_nome'));
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
                        <strong>Total pago:</strong> <?= number_format($pedido['total'], 2, ',', '.') ?> MZN
                        <button type="button" class="print-btn-individual" 
                            onclick="imprimirFaturaIndividual(<?= $pedido['id_pedido'] ?>)">
                            Imprimir Fatura
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
        </form>
    <?php else: ?>
        <p class="message-empty">Não há pedidos no histórico para este período.</p>
    <?php endif; ?>
    </div>

    <script>
        // Selecionar todos os pedidos (Mantido)
        document.getElementById("selectAll").addEventListener("change", function() {
            const checkboxes = document.querySelectorAll("input[name='pedidos[]']");
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Função para ocultar pedidos (Mantido)
        function ocultarSelecionados() {
            const form = document.getElementById("formPedidos");
            const formData = new FormData(form);

            if (!confirm('Tem certeza que deseja ocultar os pedidos selecionados do seu histórico?')) {
                return;
            }

            fetch("ocultar_pedidos.php", {
                method: "POST",
                body: formData
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Atualiza página para refletir mudanças
                } else {
                    alert("Erro ao ocultar pedidos!");
                }
            })
            .catch(err => console.error(err));
        }

        /**
         * Abre a janela de impressão para uma fatura individual.
         * @param {number} id_pedido O ID do pedido a ser impresso.
         */
        function imprimirFaturaIndividual(id_pedido) {
            const url = `gerar_fatura.php?id_pedido=${id_pedido}`;
            // Abre em uma nova janela para forçar a impressão
            window.open(url, '_blank', 'width=800,height=600'); 
        }

        /**
         * Abre a janela de impressão para um lote de faturas, baseado no filtro de data.
         * @param {string} filtro O filtro de data.
         */
        function imprimirFaturaLote(filtro) {
            const url = `gerar_fatura.php?filtro=${filtro}&lote=true`;
            // Abre em uma nova janela para forçar a impressão
            window.open(url, '_blank', 'width=800,height=600'); 
        }
    </script>
</body>
</html>