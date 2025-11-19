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

// =========================
//  Marcar notificações como lidas
// =========================
$stmt = $conexao->prepare("UPDATE notificacao SET lida = 1 WHERE lida = 0");
$stmt->execute();
$stmt->close();

// =========================
// CONSULTAR PEDIDOS
// =========================
$sql_pedidos = "
    SELECT
        p.id_pedido,
        p.data_pedido,
        p.telefone,
        p.total,
        p.idtipo_entrega,
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
    WHERE p.status_pedido IN ('pendente', 'Em preparação', 'Saiu Para Entrega', 'Pronto para Retirada') 
      AND p.status_pedido != 'entregue'
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

<div class="conteudo">
    <h1>Painel de Pedidos da Cozinha</h1>

    <div class="pedido-list" id="pedido-list">

        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($pedido = mysqli_fetch_assoc($result)):
                $status_display = $pedido['status_detalhado'] ?? 'pendente';
                $id_tipo_entrega = intval($pedido['idtipo_entrega']);
            ?>

            <div class="pedido-item" id="pedido-<?= $pedido['id_pedido'] ?>">

                <div class="pedido-header">
                    <span>Pedido #<?= $pedido['id_pedido'] ?></span>
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

                <!-- Produtos -->
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
                    ?>
                        <div class="produto-item">
                            <img src="<?= $item['imagem_principal'] ?? 'imagens/sem_imagem.jpg' ?>">
                            <b><?= $item['quantidade'] ?>x <?= $item['nome_produto'] ?></b>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Botões de ação -->
                <div class="action-buttons">

                    <?php if ($status_display === 'pendente'): ?>
                        <form class="ajax-status">
                            <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                            <input type="hidden" name="novo_status" value="Em preparação">
                            <button class="btn-preparar">Iniciar Preparo</button>
                        </form>

                    <?php elseif ($status_display === 'Em preparação'): ?>
                        <form class="ajax-status">
                            <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                            <input type="hidden" name="novo_status" value="Pronto para Retirada">
                            <button class="btn-retirar">Pronto para Retirada/Entrega</button>
                        </form>

                    <?php elseif ($status_display === 'Pronto para Retirada'): ?>

                        <?php if ($id_tipo_entrega === 1): ?>
                            <form class="ajax-status">
                                <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                                <input type="hidden" name="novo_status" value="entregue">
                                <button class="btn-finalizar">Finalizar Pedido</button>
                            </form>

                        <?php else: ?>
                            <form class="ajax-status">
                                <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                                <input type="hidden" name="novo_status" value="Saiu Para Entrega">
                                <button class="btn-delivery">Saiu Para Entrega</button>
                            </form>
                        <?php endif; ?>

                    <?php elseif ($status_display === 'Saiu Para Entrega'): ?>
                        <form class="ajax-status">
                            <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                            <input type="hidden" name="novo_status" value="entregue">
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

                fetch("atualizar_status.php", {
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
