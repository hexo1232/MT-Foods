<?php

//ver_carrinho.php
session_start();

include "conexao.php";
include "verifica_login_opcional.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
// Variáveis iniciais
$itens_carrinho = [];
$total = 0.00;
$usuario_logado = !empty($_SESSION['usuario']['id_usuario']);

/* ==========================================================
   FUNÇÕES UTILITÁRIAS
   ========================================================= */

/**
 * Busca e formata ingredientes com imagem.
 */
function buscarIngredientes($ids_ingredientes, $conexao) {
    if (empty($ids_ingredientes)) {
        return [];
    }

    $ids_str = implode(',', array_map('intval', $ids_ingredientes));

    $sql = "SELECT i.id_ingrediente, i.nome_ingrediente, iim.caminho_imagem
            FROM ingrediente i
            LEFT JOIN ingrediente_imagem iim
                ON i.id_ingrediente = iim.id_ingrediente AND iim.imagem_principal = 1
            WHERE i.id_ingrediente IN ($ids_str)";

    $result = $conexao->query($sql);
    $ingredientes_db = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ingredientes_db[$row['id_ingrediente']] = $row;
        }
    }
    return $ingredientes_db;
}

/**
 * Calcula a quantidade máxima de um produto com base no estoque
 * dos ingredientes necessários.
 *
 * @param int $id_produto
 * @param mysqli $conexao
 * @return int
 */
function calcularEstoqueProduto($id_produto, $conexao) {

    $sql = "
        SELECT 
            pi.quantidade_ingrediente AS quantidade_necessaria,
            i.quantidade_estoque AS estoque_ingrediente
        FROM produto_ingrediente pi
        JOIN ingrediente i ON pi.id_ingrediente = i.id_ingrediente
        WHERE pi.id_produto = ?
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_produto);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        return 0; // Se não houver ingredientes, não pode ser produzido
    }

    $limite_estoque = PHP_INT_MAX;

    while ($row = $res->fetch_assoc()) {
        $estoque = (int)$row['estoque_ingrediente'];
        $necessario = (int)$row['quantidade_necessaria'];

        if ($necessario > 0) {
            $unidades_possiveis = floor($estoque / $necessario);
            $limite_estoque = min($limite_estoque, $unidades_possiveis);
        }
    }
    return $limite_estoque;
}

/* ==========================================================
   USUÁRIO LOGADO (CARRINHO NO BANCO DE DADOS)
   ========================================================= */
if ($usuario_logado) {
    $id_usuario = (int) $_SESSION['usuario']['id_usuario'];

    // Busca carrinho ativo
    $sql = "SELECT id_carrinho FROM carrinho WHERE id_usuario = ? AND status = 'activo'";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $id_carrinho = (int) $res->fetch_assoc()['id_carrinho'];

        /* Atualização de quantidades */
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['quantidades'])) {
            foreach ($_POST['quantidades'] as $uuid_item => $qtd) {
                $qtd_valida = max(1, (int) $qtd);
                $uuid_item_valido = htmlspecialchars($uuid_item);

                $sql_preco_original = "SELECT subtotal, quantidade 
                                        FROM item_carrinho 
                                        WHERE ic.uuid = ? 
  AND ic.id_carrinho IN (SELECT id_carrinho FROM carrinho WHERE id_usuario = ? AND status = 'activo')";
                $stmt_preco = $conexao->prepare($sql_preco_original);
                $stmt_preco->bind_param("si", $uuid_item_valido, $id_carrinho);
                $stmt_preco->execute();
                $item_db = $stmt_preco->get_result()->fetch_assoc();

                if ($item_db) {
                    $preco_unitario = (float)$item_db['subtotal'] / max(1, (int)$item_db['quantidade']); 
                    $novo_subtotal = $preco_unitario * $qtd_valida;

                    $sql_up = "UPDATE item_carrinho 
                               SET quantidade = ?, subtotal = ? 
                             WHERE ic.uuid = ? 
  AND ic.id_carrinho IN (SELECT id_carrinho FROM carrinho WHERE id_usuario = ? AND status = 'activo')";

                    $stmt_up = $conexao->prepare($sql_up);
                    $stmt_up->bind_param("idsi", $qtd_valida, $novo_subtotal, $uuid_item_valido, $id_carrinho);
                    $stmt_up->execute();
                }
            }
        }

        /* ==========================================================
           BUSCA E AGRUPAMENTO DOS ITENS (USUÁRIO LOGADO)
           ========================================================== */
        $sql_itens = "SELECT ic.id_item_carrinho, ic.id_produto, ic.quantidade, ic.subtotal, ic.uuid, ic.id_tipo_item_carrinho,
                             p.nome_produto, p.preco,
                             (SELECT caminho_imagem FROM produto_imagem 
                              WHERE id_produto = p.id_produto AND imagem_principal = 1 LIMIT 1) AS imagem_principal
                      FROM item_carrinho ic
                      JOIN produto p ON ic.id_produto = p.id_produto
                      WHERE ic.id_carrinho = ?
                      ORDER BY ic.id_item_carrinho DESC";

        $stmt_itens = $conexao->prepare($sql_itens);
        $stmt_itens->bind_param("i", $id_carrinho);
        $stmt_itens->execute();
        $res_itens = $stmt_itens->get_result();

        $itens_carrinho_temp = [];
        while ($row = $res_itens->fetch_assoc()) {
            $uuid = $row['uuid'];
            $estoque_calculado = calcularEstoqueProduto($row['id_produto'], $conexao);

            $itens_carrinho_temp[$uuid] = [
                'id_item_carrinho'          => $row['id_item_carrinho'],
                'id_produto'                => $row['id_produto'],
                'quantidade'                => $row['quantidade'],
                'subtotal'                  => $row['subtotal'],
                'uuid'                      => $row['uuid'],
                'id_tipo_item_carrinho'     => $row['id_tipo_item_carrinho'],
                'nome_produto'              => $row['nome_produto'],
                'quantidade_estoque'        => $estoque_calculado,
                'preco'                     => $row['preco'],
                'imagem_principal'          => $row['imagem_principal'],
                'ingredientes_incrementados'=> [],
                'ingredientes_reduzidos'    => [],
            ];

            // Busca ingredientes extras/removidos
            $sql_ing = "SELECT ci.id_ingrediente, ci.tipo, ci.quantidade_ajuste, 
                                i.nome_ingrediente, ii.caminho_imagem
                        FROM carrinho_ingrediente ci
                        JOIN ingrediente i ON ci.id_ingrediente = i.id_ingrediente
                        LEFT JOIN ingrediente_imagem ii ON i.id_ingrediente = ii.id_ingrediente 
                                                          AND ii.imagem_principal = 1
                        WHERE ci.id_item_carrinho = ?";
            
            $stmt_ing = $conexao->prepare($sql_ing);
            $stmt_ing->bind_param("i", $row['id_item_carrinho']);
            $stmt_ing->execute();
            $res_ing = $stmt_ing->get_result();
            
            // Lógica para agrupar ingredientes adicionais
            $grouped_extras = [];
            $grouped_removidos = [];
            while ($ing = $res_ing->fetch_assoc()) {
                $nome_ingrediente = $ing['nome_ingrediente'];
                $quantidade_ajuste = (int)$ing['quantidade_ajuste'];
                $caminho_imagem = $ing['caminho_imagem'] ?? 'sem_foto_ingrediente.png';
            
                if ($ing['tipo'] === 'extra') {
                    if (isset($grouped_extras[$nome_ingrediente])) {
                        $grouped_extras[$nome_ingrediente]['quantidade'] += $quantidade_ajuste;
                    } else {
                        $grouped_extras[$nome_ingrediente] = [
                            'nome_ingrediente' => $nome_ingrediente,
                            'quantidade'       => $quantidade_ajuste,
                            'caminho_imagem'   => $caminho_imagem
                        ];
                    }
                } elseif ($ing['tipo'] === 'removido') {
                    if (isset($grouped_removidos[$nome_ingrediente])) {
                        $grouped_removidos[$nome_ingrediente]['quantidade'] += $quantidade_ajuste;
                    } else {
                        $grouped_removidos[$nome_ingrediente] = [
                            'nome_ingrediente' => $nome_ingrediente,
                            'quantidade'       => $quantidade_ajuste,
                            'caminho_imagem'   => $caminho_imagem
                        ];
                    }
                }
            }
            
            // Atribui os arrays agrupados ao item do carrinho
            $itens_carrinho_temp[$uuid]['ingredientes_incrementados'] = array_values($grouped_extras);
            $itens_carrinho_temp[$uuid]['ingredientes_reduzidos'] = array_values($grouped_removidos);
        }

        $itens_carrinho = array_values($itens_carrinho_temp);
        $total = array_sum(array_column($itens_carrinho, 'subtotal'));

    }
}

/* ==========================================================
   VISITANTE (CARRINHO NO COOKIE)
   ========================================================== */
elseif (!empty($_COOKIE['carrinho'])) {
    $carrinho_cookie = json_decode(urldecode($_COOKIE['carrinho']), true);
    if (!is_array($carrinho_cookie)) {
        $carrinho_cookie = [];
    }

    foreach ($carrinho_cookie as $item) {
        $id_produto = (int)$item['id_produto'];
        $estoque_calculado = calcularEstoqueProduto($id_produto, $conexao);

        $stmt = $conexao->prepare("SELECT nome_produto, preco,
                                             (SELECT caminho_imagem FROM produto_imagem 
                                              WHERE id_produto = ? AND imagem_principal = 1 LIMIT 1) AS imagem_principal
                                       FROM produto 
                                       WHERE id_produto = ?");
        $stmt->bind_param("ii", $id_produto, $id_produto);
        $stmt->execute();
        $resP = $stmt->get_result()->fetch_assoc();

        $item_formatado = [
            'uuid'                      => $item['uuid'],
            'id_produto'                => $id_produto,
            'id_tipo_item_carrinho'     => $item['id_tipo_item_carrinho'] ?? 1,
            'nome_produto'              => $resP['nome_produto'] ?? 'Produto indisponível',
            'quantidade'                => (int)($item['quantidade'] ?? 1),
            'preco'                     => (float)($resP['preco'] ?? 0),
            'subtotal'                  => (float)($item['subtotal'] ?? 0),
            'imagem_principal'          => $resP['imagem_principal'] ?? 'sem_foto.png',
            'quantidade_estoque'        => $estoque_calculado,
            'ingredientes_incrementados'=> [],
            'ingredientes_reduzidos'    => [],
        ];

        if ((int)($item['id_tipo_item_carrinho'] ?? 1) === 2 
            && (!empty($item['ingredientes_incrementados']) || !empty($item['ingredientes_reduzidos']))) {
            
            $ids_incrementados = array_column($item['ingredientes_incrementados'] ?? [], 'id_ingrediente');
            $ids_reduzidos     = array_column($item['ingredientes_reduzidos'] ?? [], 'id_ingrediente');
            $ids_ingredientes  = array_unique(array_merge($ids_incrementados, $ids_reduzidos));

            $ingredientes_db = buscarIngredientes($ids_ingredientes, $conexao);

            // Lógica para agrupar ingredientes adicionais do cookie
            $grouped_extras = [];
            foreach ($item['ingredientes_incrementados'] ?? [] as $ing) {
                $id = (int)$ing['id_ingrediente'];
                if (isset($ingredientes_db[$id])) {
                    $nome_ing = $ingredientes_db[$id]['nome_ingrediente'];
                    $qtd_ing = (int)($ing['qtd'] ?? 1);
                    $img_ing = $ingredientes_db[$id]['caminho_imagem'] ?? 'sem_foto.png';
                    if (isset($grouped_extras[$nome_ing])) {
                        $grouped_extras[$nome_ing]['quantidade'] += $qtd_ing;
                    } else {
                        $grouped_extras[$nome_ing] = [
                            'nome_ingrediente' => $nome_ing,
                            'quantidade' => $qtd_ing,
                            'caminho_imagem' => $img_ing
                        ];
                    }
                }
            }
            $item_formatado['ingredientes_incrementados'] = array_values($grouped_extras);

            // Lógica para agrupar ingredientes removidos do cookie
            $grouped_removidos = [];
            foreach ($item['ingredientes_reduzidos'] ?? [] as $ing) {
                $id = (int)$ing['id_ingrediente'];
                if (isset($ingredientes_db[$id])) {
                    $nome_ing = $ingredientes_db[$id]['nome_ingrediente'];
                    $qtd_ing = (int)($ing['qtd'] ?? 1);
                    $img_ing = $ingredientes_db[$id]['caminho_imagem'] ?? 'sem_foto.png';
                    if (isset($grouped_removidos[$nome_ing])) {
                        $grouped_removidos[$nome_ing]['quantidade'] += $qtd_ing;
                    } else {
                        $grouped_removidos[$nome_ing] = [
                            'nome_ingrediente' => $nome_ing,
                            'quantidade' => $qtd_ing,
                            'caminho_imagem' => $img_ing
                        ];
                    }
                }
            }
            $item_formatado['ingredientes_reduzidos'] = array_values($grouped_removidos);
        }
        $itens_carrinho[] = $item_formatado;
    }
    $total = array_sum(array_column($itens_carrinho, 'subtotal'));
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Carrinho de Compras</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
         <link rel="stylesheet" href="css/cliente.css">

    
    
     <?php if ($usuario): ?>
        <script src="logout_auto.js"></script>
    <?php endif; ?>
   
 <link rel="stylesheet" href="css/cliente.css">
          <script src="js/darkmode1.js"></script>
            <script src="js/dropdown.js"></script> 
         <script src="js/sidebar2.js"></script> 
</head>
<body>


    

<header class="topbar">
  <div class="container">

    <!-- 🔹 BOTÃO MENU MOBILE -->
    <button class="menu-btn-mobile" id="menuBtnMobile">&#9776;</button>

    <!-- 🟠 LOGO -->
    <div class="logo">
      <a href="index.php">
        <img src="icones/logo.png" alt="Logo do Restaurante" class="logo-img">
      </a>
    </div>

    <!-- 🔹 LINKS DESKTOP -->
<div class="links-menu">
  <?php if ($usuario): ?>
    <a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar">Voltar</a>
  <?php else: ?>
    <a href="login.php">Fazer Login</a>
    <a href="cardapio.php">Continuar a Comprar</a>
  <?php endif; ?>
</div>


    <!-- 🔹 AÇÕES DO USUÁRIO -->
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
              <a href="editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>">
              <img class="icone" src="icones/user1.png" alt="Editar" title="Editar">  
              Editar Dados Pessoais</a>
              <a href="alterar_senha2.php">
              <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar">   
              Alterar Senha</a>
              <a href="logout.php"><img class="iconelogout" src="icones/logout1.png"> Sair</a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

  </div>
</header>

<!-- 🔹 MENU MOBILE SIDEBAR -->
<nav id="mobileMenu" class="nav-mobile-sidebar hidden">
  <div class="sidebar-header">
    <button class="close-btn" id="closeMobileMenu">&times;</button>
  </div>
  <ul class="sidebar-links">
     <?php if ($usuario): ?>
    <li><a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar"> Voltar</a></li>
  <?php else: ?>
    <li><a href="login.php">Fazer Login</a></li>
    <li><a href="cardapio.php">Continuar a Comprar</a></li>
  <?php endif; ?>
  </ul>

  <?php if ($usuario): ?>
    <div class="sidebar-user-section">

  <div id="sidebarProfileDropdown" class="sidebar-profile-dropdown">
      <a href='editarusuario.php?id_usuario=<?= $usuario["id_usuario"] ?>'>
           <img class="icone" src="icones/user1.png" alt="Editar" title="Editar"> Editar Dados Pessoais
      </a>

      <a href="alterar_senha2.php">
                  <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar">   Alterar Senha
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
    <h2>Carrinho de Compras</h2>
    <?php if (count($itens_carrinho) === 0): ?>
        <p  class="message-empty"><a href="cardapio.php" style='text-decoration:none; color:black;'>Adicione a sua Refeição ao Carrinho</a></p>
    <?php else: ?>
        <form id="formCarrinho" method="post">
            <?php if (count($itens_carrinho) === 0): ?>
                <p>Adicione a sua Refeição ao Carrinho</p>
            <?php else: ?>
                <?php foreach ($itens_carrinho as $i => $item): ?>
                    <?php
                        // Lógica unificada para calcular o preço unitário
                        $quantidade_atual = (int)($item['quantidade'] ?? 1);
                        $preco_unitario = (float)($item['subtotal'] ?? 0) / max(1, $quantidade_atual);

                        // UID único do item
                        $uid = $item['uuid'] ?? uniqid('item_', true);
                    ?>

                    <div class="card">
                        <img src="<?= htmlspecialchars($item['imagem_principal'] ?? 'sem_foto.png') ?>" 
                             onclick="window.location='detalhesproduto.php?id=<?= htmlspecialchars($item['id_produto']) ?>'">

                        <div class="info">
                            <h3><?= htmlspecialchars($item['nome_produto']) ?></h3>
                            <input type="hidden" name="uids[<?= $i ?>]" value="<?= htmlspecialchars($uid) ?>">

                            <!-- Ingredientes Extra -->
                            <?php if (!empty($item['ingredientes_incrementados'])): ?>
                                <div class="ingredientes-personalizados_extra">
                                    <h4>Ingredientes Extra</h4>
                                    <div class="ingredientes-container">
                                        <?php foreach ($item['ingredientes_incrementados'] as $ing_inc): ?>
                                            <div class='ingrediente-card'>
                                                <img src="<?= htmlspecialchars($ing_inc['caminho_imagem'] ?? 'sem_foto_ingrediente.png') ?>" 
                                                     alt="<?= htmlspecialchars($ing_inc['nome_ingrediente']) ?>">
                                                <div class='ingrediente-info'>
                                                    <?= htmlspecialchars($ing_inc['nome_ingrediente']) ?> 
                                                    (x<?= htmlspecialchars($ing_inc['quantidade']) ?>)
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Ingredientes Removidos -->
                            <?php if (!empty($item['ingredientes_reduzidos'])): ?>
                                <div class="ingredientes-personalizado_removidos">
                                    <h4>Ingredientes Removidos</h4>
                                    <div class="ingredientes-container">
                                        <?php foreach ($item['ingredientes_reduzidos'] as $ing_red): ?>
                                            <div class='ingrediente-card'>
                                                <img src="<?= htmlspecialchars($ing_red['caminho_imagem'] ?? 'sem_foto_ingrediente.png') ?>" 
                                                     alt="<?= htmlspecialchars($ing_red['nome_ingrediente']) ?>">
                                                <div class='ingrediente-info'>
                                                    <?= htmlspecialchars($ing_red['nome_ingrediente']) ?> 
                                                    (x<?= htmlspecialchars($ing_red['quantidade']) ?>)
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?> 
                            <br>

                            <!-- Quantidade -->
                            <div class="quantidade">
                                <label>Quantidade:</label>
                                <button type="button" 
                                        onclick="alterarQuantidade('<?= htmlspecialchars($uid) ?>', <?= htmlspecialchars($preco_unitario) ?>, <?= htmlspecialchars($item['quantidade_estoque']) ?>, -1)">
                                    -
                                </button>
                                
                                <input type="number" 
                                        name="quantidades[<?= htmlspecialchars($uid) ?>]" 
                                        id="qtd-<?= htmlspecialchars($uid) ?>" 
                                        value="<?= htmlspecialchars($item['quantidade']) ?>" 
                                        min="1" 
                                        max="<?= htmlspecialchars($item['quantidade_estoque']) ?>" 
                                        onchange="atualizarSubtotal('<?= htmlspecialchars($uid) ?>', <?= htmlspecialchars($preco_unitario) ?>, <?= htmlspecialchars($item['quantidade_estoque']) ?>)">
                                
                                <button type="button" 
                                        onclick="alterarQuantidade('<?= htmlspecialchars($uid) ?>', <?= htmlspecialchars($preco_unitario) ?>, <?= htmlspecialchars($item['quantidade_estoque']) ?>, 1)">
                                    +
                                </button>
                            </div>

                            <!-- Subtotal -->
                            <p class="subtotal" id="subtotal-<?= htmlspecialchars($uid) ?>">
                                <b>Total: </b><?= number_format($item['subtotal'], 2, ',', '.') ?> MZN
                            </p>

                            <!-- Ações -->
                            <div class="acoes">
                           <?php if ($usuario_logado): ?>
    <button type="button" class="remove" onclick="removerItemAJAX('<?= htmlspecialchars($uid) ?>', this)">
        Remover do Carrinho
    </button>
<?php else: ?>
    <button type="button" class="remove" onclick="removerItemAJAX('<?= htmlspecialchars($uid) ?>', this)">
        Remover do Carrinho
    </button>
<?php endif; ?>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Total Global -->
                <div class="total">
                    <strong>Subtotal: <span id="total"><?= number_format($total, 2, ',', '.') ?> MZN</span></strong>
                </div>

                <br>

                <!-- Botões -->
                <?php if ($usuario_logado): ?>
                    <button class="save" type="submit">Salvar Carrinho</button>
                    <a href="finalizar_pedido.php"><button class="end" type="button">Fazer Pedido</button></a>
                <?php else: ?>
                    <a href="finalizar_pedido.php"><button class="end" type="button">Fazer Pedido</button></a>
                <?php endif; ?>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<script>

    function removerItemAJAX(uuid, botao) {

    fetch("remover_item_carrinho_ajax.php?uuid=" + uuid)
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                // remover visualmente o card sem reload
                const card = botao.closest(".card");
                card.style.transition = "0.3s";
                card.style.opacity = "0";

                setTimeout(() => {
                    card.remove();
                    atualizarTotalGlobal();

                    // se carrinho ficou vazio → mostrar mensagem
                    if (document.querySelectorAll(".card").length === 0) {
                        document.querySelector(".conteudo").innerHTML =
                            "<p class='message-empty'><a href='cardapio.php' style='text-decoration:none; color:black;'>Adicione a sua Refeição ao Carrinho</a></p>";
                    }
                }, 300);

            } else {
                alert("Erro ao remover item.");
            }
        })
        .catch(() => alert("Erro ao comunicar com o servidor."));
}

    const precosUnitarios = {};

    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.quantidade input[type="number"]').forEach(input => {
            const index = input.id.replace('qtd-', '');
            precosUnitarios[index] = parseFloat(
                input.closest('.card')
                     .querySelector('[onclick^="alterarQuantidade"]')
                     .getAttribute('onclick')
                     .match(/, ([\d.]+),/)[1]
            );
        });
        atualizarTotalGlobal();
    });

    function atualizarSubtotal(index, preco, estoque) {
        const qtdInput = document.getElementById('qtd-' + index);
        let qtd = parseInt(qtdInput.value);

        if (qtd > estoque) {
            console.log("Aviso: Só temos " + estoque + " unidades em estoque.");
            qtdInput.value = estoque;
            qtd = estoque;
        }

        const subtotal = preco * qtd;
        document.getElementById('subtotal-' + index).innerText = "Total: " + subtotal.toFixed(2) + " MZN";
        atualizarTotalGlobal();
    }

    function atualizarTotalGlobal() {
        let total = 0;
        document.querySelectorAll('.quantidade input[type="number"]').forEach(input => {
            const index = input.id.replace('qtd-', '');
            const qtd = parseInt(input.value);
            const preco = precosUnitarios[index];

            if (!isNaN(qtd) && !isNaN(preco)) {
                total += qtd * preco;
            } else {
                console.error(`Erro: Quantidade ou preço inválido para o item ${index}`);
            }
        });
        document.getElementById('total').innerText = total.toFixed(2) + " MZN";
    }

    function alterarQuantidade(index, preco, estoque, delta) {
        const input = document.getElementById('qtd-' + index);
        let valor = parseInt(input.value);
        valor += delta;

        if (valor < 1) valor = 1;
        if (valor > estoque) {
            console.log("Aviso: Impossível adicionar mais unidades, só pode adicionar " + estoque + " unidades.");
            valor = estoque;
        }

        input.value = valor;
        atualizarSubtotal(index, preco, estoque);
    }

    function removerDoCarrinhoCookie(uuid) {
        let carrinho = [];
        try {
            const cookieData = decodeURIComponent(
                document.cookie.split('; ').find(row => row.startsWith('carrinho='))?.split('=')[1]
            );
            carrinho = JSON.parse(cookieData) || [];
        } catch (e) {
            carrinho = [];
        }

        carrinho = carrinho.filter(item => item.uuid !== uuid);
        document.cookie = `carrinho=${encodeURIComponent(JSON.stringify(carrinho))}; path=/; max-age=604800`;
        location.reload();
    }
</script>
</body>
</html>

