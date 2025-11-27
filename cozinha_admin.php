<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Marcar notificações como lidas
$stmt = $conexao->prepare("UPDATE notificacao SET lida = 1 WHERE lida = 0");
$stmt->execute();
$stmt->close();

// CONSULTAR PEDIDOS - AGORA INCLUI idtipo_origem_pedido
$sql_pedidos = "
    SELECT
        p.id_pedido,
        p.data_pedido,
        p.telefone,
        p.total,
        p.idtipo_entrega,
        p.idtipo_origem_pedido,
        p.bairro,
        p.ponto_referencia,
        u.nome AS nome_cliente,
        u.apelido AS apelido_cliente,
        t.nome_tipo_entrega,
        tp.tipo_pagamento,
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
    WHERE p.status_pedido IN ('pendente', 'Em preparação', 'Saiu Para Entrega', 'Pronto para Retirada','Pago') 
      AND p.status_pedido != 'entregue'
      AND p.status_pedido != 'servido'
    ORDER BY p.data_pedido DESC
";

$result = mysqli_query($conexao, $sql_pedidos);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Painel da Cozinha</title>

    <script src="logout_auto.js"></script>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/dropdown2.js"></script>
    
    <style>
        /* Estilos para personalizações */
        .personalizacoes-list {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 10px;
            margin-top: 8px;
            font-size: 0.9em;
        }
        
        .personalizacoes-list strong {
            display: block;
            margin-bottom: 5px;
            color: #495057;
        }
        
        .personalizacao-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            margin: 4px;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .personalizacao-item img {
            width: 28px;
            height: 28px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .personalizacao-extra {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .personalizacao-extra::before {
            content: "➕ ";
            font-weight: bold;
        }
        
        .personalizacao-removido {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .personalizacao-removido::before {
            content: "➖ ";
            font-weight: bold;
        }
        
        .produto-item {
            background: white;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .produto-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .produto-header img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 6px;
            border: 2px solid #dee2e6;
        }
        
        .produto-info {
            flex: 1;
        }
        
        .produto-nome {
            font-weight: bold;
            color: #212529;
            font-size: 1.05em;
        }
        
        .produto-quantidade {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 2px;
        }
        
        /* Badge para Pedido Presencial */
        .badge-presencial {
            display: inline-block;
            padding: 4px 10px;
            background: #17a2b8;
            color: white;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
            margin-left: 8px;
        }
        
        /* Botão Servir */
        .btn-servir {
            background: #6f42c1;
            color: white;
        }
        
        .btn-servir:hover {
            background: #5a32a3;
        }
    </style>
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
                <a href='editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>'>
                <img class="icone" src="icones/user1.png" alt="Editar" title="Editar">    
                Editar Dados Pessoais</a>
                <a href="alterar_senha2.php">
                <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar">     
                Alterar Senha</a>
                <a href="logout.php">
                <img class="iconelogout" src="icones/logout1.png" alt="Logout" title="Sair">    
                Sair</a>
            </div>
        </div>

        <img class="dark-toggle" id="darkToggle"
             src="icones/lua.png"
             alt="Modo Escuro"
             title="Alternar modo escuro">
    </div>
</sidebar>

<div class="conteudo">
    <h1>Painel de Pedidos da Cozinha</h1>

    <div class="pedido-list" id="pedido-list">

        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($pedido = mysqli_fetch_assoc($result)):
                $status_display = $pedido['status_detalhado'] ?? 'pendente';
                $id_tipo_entrega = intval($pedido['idtipo_entrega']);
                $id_tipo_origem = intval($pedido['idtipo_origem_pedido']);
                $eh_presencial = ($id_tipo_origem === 3);
            ?>

            <div class="pedido-item" id="pedido-<?= $pedido['id_pedido'] ?>">

                <div class="pedido-header">
                    <span>
                        Pedido #<?= $pedido['id_pedido'] ?>
                        <?php if ($eh_presencial): ?>
                            <span class="badge-presencial">PRESENCIAL</span>
                        <?php endif; ?>
                    </span>
                    <span class="status status-<?= str_replace(' ', '_', strtolower($status_display)) ?>">
                        <?= htmlspecialchars($status_display) ?>
                    </span>
                </div>

                <div class="pedido-details">
                    <p><b>Cliente:</b> <?= $pedido['nome_cliente'] . ' ' . $pedido['apelido_cliente'] ?></p>
                    <p><b>Entrega:</b> <?= $pedido['nome_tipo_entrega'] ?></p>

                    <?php if ($id_tipo_entrega === 2): ?>
                        <p class="endereco-delivery">
                            <b>Bairro:</b> <?= $pedido['bairro'] ?><br>
                            <b>Referência:</b> <?= $pedido['ponto_referencia'] ?>
                        </p>
                    <?php endif; ?>

                    <b>Telefone:</b> <?= $pedido['telefone'] ?><br>
                    <p><b>Método de Pagamento:</b> <?= $pedido['tipo_pagamento'] ?></p>
                    <p><b>Horário:</b> <?= (new DateTime($pedido['data_pedido']))->format('H:i') ?></p>
                    <strong>Total pago:</strong> <?= number_format($pedido['total'], 2, ',', '.') ?> MZN
                </div>

                <!-- Produtos com Personalizações -->
                <div class="produtos-list">
                    <?php
                    $sql_itens = "
                        SELECT ip.id_item_pedido, ip.quantidade, ip.subtotal, pr.nome_produto,
                            (SELECT caminho_imagem FROM produto_imagem 
                             WHERE id_produto = pr.id_produto AND imagem_principal = 1 LIMIT 1) AS imagem_principal
                        FROM item_pedido ip
                        JOIN produto pr ON ip.id_produto = pr.id_produto
                        WHERE ip.id_pedido = {$pedido['id_pedido']}
                    ";
                    $res_itens = mysqli_query($conexao, $sql_itens);

                    while ($item = mysqli_fetch_assoc($res_itens)):
                        $id_item = $item['id_item_pedido'];
                        
                        // Buscar personalizações do item COM IMAGENS
                        $sql_pers = "
                            SELECT 
                                ipp.ingrediente_nome,
                                ipp.tipo,
                                COUNT(*) AS qtd,
                                i.id_ingrediente,
                                (SELECT caminho_imagem 
                                 FROM ingrediente_imagem 
                                 WHERE id_ingrediente = i.id_ingrediente 
                                 AND imagem_principal = 1 
                                 LIMIT 1) AS imagem_ingrediente
                            FROM item_pedido_personalizacao ipp
                            LEFT JOIN ingrediente i ON i.nome_ingrediente = ipp.ingrediente_nome
                            WHERE ipp.id_item_pedido = ?
                            GROUP BY ipp.ingrediente_nome, ipp.tipo, i.id_ingrediente
                            ORDER BY ipp.tipo DESC
                        ";
                        $stmt_pers = $conexao->prepare($sql_pers);
                        $stmt_pers->bind_param("i", $id_item);
                        $stmt_pers->execute();
                        $res_pers = $stmt_pers->get_result();
                        
                        $tem_personalizacoes = ($res_pers->num_rows > 0);
                    ?>
                        <div class="produto-item">
                            <div class="produto-header">
                                <img src="<?= $item['imagem_principal'] ?? 'imagens/sem_imagem.jpg' ?>" 
                                     alt="<?= htmlspecialchars($item['nome_produto']) ?>">
                                <div class="produto-info">
                                    <div class="produto-nome"><?= htmlspecialchars($item['nome_produto']) ?></div>
                                    <div class="produto-quantidade">Quantidade: <?= $item['quantidade'] ?>x</div>
                                </div>
                            </div>
                            
                            <?php if ($tem_personalizacoes): ?>
                                <div class="personalizacoes-list">
                                    <strong>🔧 Personalizações:</strong>
                                    <?php while ($pers = $res_pers->fetch_assoc()): 
                                        $img_pers = $pers['imagem_ingrediente'] ?? 'imagens/sem_imagem.jpg';
                                    ?>
                                        <span class="personalizacao-item personalizacao-<?= $pers['tipo'] ?>">
                                            <img src="<?= htmlspecialchars($img_pers) ?>" 
                                                 alt="<?= htmlspecialchars($pers['ingrediente_nome']) ?>"
                                                 title="<?= htmlspecialchars($pers['ingrediente_nome']) ?>">
                                            <span>
                                                <?= htmlspecialchars($pers['ingrediente_nome']) ?>
                                                <?php if ($pers['qtd'] > 1): ?>
                                                    (x<?= $pers['qtd'] ?>)
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php 
                        $stmt_pers->close();
                    endwhile; 
                    ?>
                </div>

                <!-- Botões de ação - LÓGICA ATUALIZADA PARA PRESENCIAL -->
                <div class="action-buttons">

                    <?php if ($status_display === 'pendente'): ?>
                        <form class="ajax-status">
                            <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                            <input type="hidden" name="novo_status" value="Em preparação">
                            <input type="hidden" name="eh_presencial" value="<?= $eh_presencial ? '1' : '0' ?>">
                            <button class="btn-preparar">Iniciar Preparo</button>
                        </form>

                    <?php elseif ($status_display === 'Em preparação'): ?>
                        <form class="ajax-status">
                            <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                            <input type="hidden" name="novo_status" value="Pronto para Retirada">
                            <input type="hidden" name="eh_presencial" value="<?= $eh_presencial ? '1' : '0' ?>">
                            <button class="btn-retirar">Pronto para Retirada/Entrega</button>
                        </form>

                    <?php elseif ($status_display === 'Pronto para Retirada'): ?>

                        <?php if ($eh_presencial): ?>
                            <!-- PRESENCIAL: Botão "Servir" -->
                            <form class="ajax-status">
                                <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                                <input type="hidden" name="novo_status" value="servido">
                                <input type="hidden" name="eh_presencial" value="1">
                                <button class="btn-servir">🍽️ Servir ao Cliente</button>
                            </form>

                        <?php elseif ($id_tipo_entrega === 1): ?>
                            <!-- RETIRADA: Botão "Finalizar" -->
                            <form class="ajax-status">
                                <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                                <input type="hidden" name="novo_status" value="entregue">
                                <input type="hidden" name="eh_presencial" value="0">
                                <button class="btn-finalizar">Finalizar Pedido</button>
                            </form>

                        <?php else: ?>
                            <!-- DELIVERY: Botão "Saiu Para Entrega" -->
                            <form class="ajax-status">
                                <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                                <input type="hidden" name="novo_status" value="Saiu Para Entrega">
                                <input type="hidden" name="eh_presencial" value="0">
                                <button class="btn-delivery">Saiu Para Entrega</button>
                            </form>
                        <?php endif; ?>

                    <?php elseif ($status_display === 'Saiu Para Entrega'): ?>
                        <form class="ajax-status">
                            <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                            <input type="hidden" name="novo_status" value="entregue">
                            <input type="hidden" name="eh_presencial" value="0">
                            <button class="btn-finalizar">Finalizar Pedido</button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>

            <?php endwhile; ?>

        <?php else: ?>
            <p class="no-pedidos">Nenhum pedido pendente no momento.</p>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {

    function ativarAjax() {
        document.querySelectorAll("form.ajax-status").forEach(form => {

            if (form.__bound) return;
            form.__bound = true;

            form.addEventListener("submit", e => {
                e.preventDefault();

                const fd = new FormData(form);

                fetch("atualizar_status2.php", {
                    method: "POST",
                    body: fd,
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                })
                .then(r => r.json())
                .then(resp => {

                    if (!resp.sucesso) {
                        alert("Erro: " + resp.erro);
                        return;
                    }

                    const id = resp.id_pedido;
                    const div = document.getElementById("pedido-" + id);

                    // Atualiza status
                    const span = div.querySelector(".status");
                    span.textContent = resp.novo_status;
                    span.className = "status status-" + resp.novo_status.toLowerCase().replace(/ /g, "_");

                    // Substitui botões
                    div.querySelector(".action-buttons").innerHTML = resp.botoes;

                    ativarAjax(); // rebinda novos botões

                })
                .catch(err => console.error("ERRO AJAX:", err));
            });
        });
    }

    ativarAjax();
});
</script>

</body>
</html>