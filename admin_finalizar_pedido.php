<?php
// 1. Incluir ficheiros de configuração e conexão com o BD
require_once "conexao.php";
require_once "require_login.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'] ?? null;

// -----------------------------------------------------
// 2. Validação do Administrador
// -----------------------------------------------------
if (!$usuario || (int)$usuario['idperfil'] !== 1) {
    header('Location: dashboard.php?erro=acesso_negado');
    exit();
}

// -----------------------------------------------------
// 3. Verificação do Pedido em Curso
// -----------------------------------------------------
if (!isset($_SESSION['admin_pedido_id'])) {
    $_SESSION['alerta_pedido'] = 'Nenhum pedido em montagem para finalizar.';
    header('Location: cardapio.php?modo=admin_pedido&mensagem=' . urlencode('Nenhum pedido em andamento. Que tal iniciar um novo?'));
    exit();
}

$id_pedido_atual = $_SESSION['admin_pedido_id'];
$itens_pedido_formatado = [];
$total_original = 0.00;

// -----------------------------------------------------
// 4. Carregar Itens e Calcular Total
// -----------------------------------------------------
$sql_itens = "
    SELECT 
        ip.id_item_pedido, 
        ip.quantidade, 
        ip.preco_unitario, 
        ip.subtotal,       
        p.nome_produto,
        (SELECT caminho_imagem FROM produto_imagem 
         WHERE id_produto = p.id_produto AND imagem_principal = 1 LIMIT 1) AS imagem_principal
    FROM item_pedido ip
    JOIN produto p ON ip.id_produto = p.id_produto
    WHERE ip.id_pedido = ?
";
$stmt_itens = $conexao->prepare($sql_itens);
if ($stmt_itens === false) {
    $_SESSION['alerta_pedido'] = 'Erro ao preparar consulta de itens: ' . $conexao->error;
    header('Location: cardapio.php?erro_sql=prepare');
    exit();
}
$stmt_itens->bind_param("i", $id_pedido_atual);
$stmt_itens->execute();
$resultado_itens = $stmt_itens->get_result();

while ($item_db = $resultado_itens->fetch_assoc()) {
    $item_id = $item_db['id_item_pedido'];
    $quantidade_produto = (int)$item_db['quantidade'];
    $subtotal = (float)$item_db['subtotal'];
    $total_original += $subtotal;
    $preco_final_unitario = (float)$item_db['preco_unitario'];

    $item_formatado = [
        'id_item_pedido' => $item_id,
        'nome_produto' => $item_db['nome_produto'],
        'quantidade' => $quantidade_produto,
        'subtotal' => $subtotal,
        'preco_unitario_final' => $preco_final_unitario,
        'imagem_principal' => $item_db['imagem_principal'] ?? 'sem_foto.png',
        'ingredientes_incrementados' => [],
        'ingredientes_reduzidos' => [],
    ];

    $sql_ing = "
        SELECT 
            ipp.ingrediente_nome,
            ipp.tipo,
            ii.caminho_imagem,
            COUNT(*) AS qtd_por_item
        FROM item_pedido_personalizacao ipp
        JOIN ingrediente i ON ipp.ingrediente_nome = i.nome_ingrediente
        LEFT JOIN ingrediente_imagem ii 
            ON i.id_ingrediente = ii.id_ingrediente AND ii.imagem_principal = 1
        WHERE ipp.id_item_pedido = ?
        GROUP BY ipp.ingrediente_nome, ipp.tipo, ii.caminho_imagem
    ";
    $stmt_ing = $conexao->prepare($sql_ing);
    $stmt_ing->bind_param("i", $item_id);
    $stmt_ing->execute();
    $res_ing = $stmt_ing->get_result();

    $grouped_extras = [];
    $grouped_removidos = [];

    while ($ing = $res_ing->fetch_assoc()) {
        $nome_ingrediente = $ing['ingrediente_nome'];
        $tipo = $ing['tipo'];
        $qtd_ingrediente = (int)$ing['qtd_por_item'];

        // ✅ Aqui removemos a lógica de soma; cada ingrediente aparece apenas uma vez
        if ($tipo === 'extra') {
            $grouped_extras[$nome_ingrediente] = [
                'nome_ingrediente' => $nome_ingrediente,
                'quantidade' => $qtd_ingrediente, 
                'caminho_imagem' => $ing['caminho_imagem'] ?? 'imagens/sem_foto_ingrediente.png',
            ];
        } elseif ($tipo === 'removido') {
            $grouped_removidos[$nome_ingrediente] = [
                'nome_ingrediente' => $nome_ingrediente,
                'quantidade' => $qtd_ingrediente,
                'caminho_imagem' => $ing['caminho_imagem'] ?? 'imagens/sem_foto_ingrediente.png',
            ];
        }
    }

    $item_formatado['ingredientes_incrementados'] = array_values($grouped_extras);
    $item_formatado['ingredientes_reduzidos'] = array_values($grouped_removidos);

    $stmt_ing->close();
    $itens_pedido_formatado[] = $item_formatado;
}


$stmt_itens->close();



// -----------------------------------------------------
// 5. Preparação dos Dados do Cliente
// -----------------------------------------------------
$nome_cliente = '';
$telefone_cliente = '';
$endereco_entrega = '';
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
       <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Pedido Manual #<?= $id_pedido_atual ?></title>
    <link rel="stylesheet" href="css/cliente.css">
    <script src="js/darkmode1.js"></script>
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
    <a href="cardapio.php?modo=admin_pedido">Voltar aos Produtos</a>
  <?php else: ?>
    <a href="login.php">Fazer Login</a>
    <a href="cardapio.php">Voltar aos Produtos</a>
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

<!-- 🔹 MENU MOBILE SIDEBAR -->
<nav id="mobileMenu" class="nav-mobile-sidebar hidden">
  <div class="sidebar-header">
    <button class="close-btn" id="closeMobileMenu">&times;</button>
  </div>
  <ul class="sidebar-links">
  <?php if ($usuario): ?>
    <a href="cardapio.php?modo=admin_pedido">Voltar aos Produtos</a>
  <?php else: ?>
    <a href="login.php">Fazer Login</a>
    <a href="cardapio.php">Voltar aos Produtos</a>
  <?php endif; ?>
  </ul>

  <?php if ($usuario): ?>
    <div class="sidebar-user-section">
      <div id="sidebarProfileDropdown" class="sidebar-profile-dropdown">
        <a href="editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>">Editar Dados Pessoais</a>
        <a href="alterar_senha2.php">Alterar Senha</a>
        <a href="logout.php" class="logout-link">Sair</a>
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

<div class="conteudo finalizar-container">
         <!-- <?php if (count($itens_pedido_formatado) === 0): ?>
              <p  class="message-empty"><a href="cardapio.php?modo=admin_pedido"
               style='text-decoration:none; color:black;'>Sem Pedidos POr finalizar no momento.</a></p>
<?php else:?> -->
    <h2 class="titulo-principal">Finalizar Pedido #<?= $id_pedido_atual ?> (Cadastro Manual)</h2>
    <form method="post" action="admin_registra_pagamento.php">
        <h3>Itens no Pedido:</h3>
      
        <?php foreach ($itens_pedido_formatado as $item): ?>
            <div class="card">
                <img src="<?= htmlspecialchars($item['imagem_principal'] ?? 'imagens/sem_imagem.jpg') ?>" alt="Imagem do produto">
                <div class="info">
                    <h3><?= htmlspecialchars($item['quantidade']) ?>x <?= htmlspecialchars($item['nome_produto']) ?></h3>

                    <?php if (!empty($item['ingredientes_incrementados'])): ?>
                        <div class="ingredientes-personalizados_extra">
                            <h4>Ingredientes Extra</h4>
                            <div class="ingredientes-container">
                                <?php foreach ($item['ingredientes_incrementados'] as $ing_inc): ?>
                                    <div class='ingrediente-card'>
                                        <img src="<?= htmlspecialchars($ing_inc['caminho_imagem'] ?? 'imagens/sem_foto_ingrediente.png') ?>" alt="Ingrediente extra">
                                        <div class='ingrediente-info'>
                                            <?= htmlspecialchars($ing_inc['nome_ingrediente']) ?>
                                            <?php if ($ing_inc['quantidade'] > 0): ?>
                                                (x<?= htmlspecialchars($ing_inc['quantidade']) ?>)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($item['ingredientes_reduzidos'])): ?>
                        <div class="ingredientes-personalizado_removidos">
                            <h4>Ingredientes Removidos</h4>
                            <div class="ingredientes-container">
                                <?php foreach ($item['ingredientes_reduzidos'] as $ing_red): ?>
                                    <div class='ingrediente-card'>
                                        <img src="<?= htmlspecialchars($ing_red['caminho_imagem'] ?? 'imagens/sem_foto_ingrediente.png') ?>" alt="Ingrediente removido">
                                        <div class='ingrediente-info'>
                                            <?= htmlspecialchars($ing_red['nome_ingrediente']) ?>
                                            <?php if ($ing_red['quantidade'] > 0): ?>
                                                (x<?= htmlspecialchars($ing_red['quantidade']) ?>)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <p class="subtotal" style="margin-top: 10px;">
                        <b>Total do Item:</b> <?= number_format($item['subtotal'], 2, ',', '.') ?> MZN
                    </p>
                </div>
            </div>
        <?php endforeach; ?>

        <p class="subtotal subtotal-finalizar">
            Total a Pagar: <span id="total_final_valor" style="font-size: 1.5em; color: #28a745;">
                <?= number_format($total_original, 2, ',', '.') ?>
            </span> MZN
        </p>

        <hr>

        <h3>Dados do Cliente (Se Necessário)</h3>
        <div class="form-dados-entrega-finalizar">
            <label for="nome_cliente">Nome do Cliente:</label>
            <input type="text" id="nome_cliente" name="nome_cliente" value="<?= htmlspecialchars($nome_cliente) ?>" required>

            <label for="telefone_cliente">Telefone:</label>
            <input type="text" id="telefone_cliente" name="telefone_cliente" value="<?= htmlspecialchars($telefone_cliente) ?>">

            <label for="endereco_entrega">Endereço de Entrega:</label>
            <textarea id="endereco_entrega" name="endereco_entrega"><?= htmlspecialchars($endereco_entrega) ?></textarea>
        </div>

        <hr>

        <h3>Registro de Pagamento Presencial</h3>
        <div class="form-registro-pagamento-manual">
            <label for="valor_pago">Valor Recebido (MZN):</label>
            <input type="number" step="0.01" id="valor_pago" name="valor_pago" required
                placeholder="Ex: <?= number_format($total_original, 2, '.', '') ?>">
            <label for="troco_admin">Troco (Auxiliar):</label>
            <input type="text" id="troco_admin" disabled placeholder="0,00 MZN" style="color: blue;">
            <input type="hidden" name="id_pedido" value="<?= $id_pedido_atual ?>">
            <input type="hidden" id="total_pedido_hidden" name="total_pedido" value="<?= $total_original ?>">
        </div>

        <button class="btn-finalizar btn-finalizar-pedido" type="submit">
            Concluir e Registrar Pagamento
        </button>
    </form>
</div>

                   <!-- <?php endif; ?> -->

<script>
document.getElementById('valor_pago').addEventListener('input', function() {
    const total = parseFloat(document.getElementById('total_pedido_hidden').value);
    const valorPago = parseFloat(this.value);
    let troco = 0;
    if (!isNaN(valorPago) && valorPago >= total) {
        troco = valorPago - total;
    }
    document.getElementById('troco_admin').value = troco.toFixed(2).replace('.', ',') + ' MZN';
});
</script>
</body>
</html>
