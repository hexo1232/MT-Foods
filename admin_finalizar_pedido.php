<?php
// admin_finalizar_pedido.php - VERSÃO PAGAMENTO PÓS-CONSUMO
require_once "conexao.php";
require_once "require_login.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

$usuario = $_SESSION['usuario'] ?? null;

if (!$usuario || (int)$usuario['idperfil'] !== 1) {
    header('Location: dashboard.php?erro=acesso_negado');
    exit();
}

// Obter ID do pedido (da URL ou da sessão)
$id_pedido_atual = $_GET['id_pedido'] ?? $_SESSION['admin_pedido_id'] ?? null;

if (!$id_pedido_atual) {
    $_SESSION['alerta_pedido'] = 'Nenhum pedido selecionado para finalizar.';
    header('Location: admin_lista_pedidos_finalizar.php');
    exit();
}

// Validar que o pedido existe e está pendente
$sql_valida = "SELECT total, status_pedido FROM pedido WHERE id_pedido = ? AND 

 status_pedido IN ('pendente', 'Em preparação', 'Saiu Para Entrega', 'Pronto para Retirada','servido') 
      AND status_pedido != 'Pago'
";
$stmt_valida = $conexao->prepare($sql_valida);
$stmt_valida->bind_param("i", $id_pedido_atual);
$stmt_valida->execute();
$resultado_valida = $stmt_valida->get_result();

if ($resultado_valida->num_rows === 0) {
    $_SESSION['erro'] = 'Pedido não encontrado ou já foi finalizado.';
    header('Location: admin_lista_pedidos_finalizar.php');
    exit();
}

$pedido_info = $resultado_valida->fetch_assoc();
$total_original = floatval($pedido_info['total']);
$stmt_valida->close();

$itens_pedido_formatado = [];

// Carregar itens do pedido
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
$stmt_itens->bind_param("i", $id_pedido_atual);
$stmt_itens->execute();
$resultado_itens = $stmt_itens->get_result();

while ($item_db = $resultado_itens->fetch_assoc()) {
    $item_id = $item_db['id_item_pedido'];
    $quantidade_produto = (int)$item_db['quantidade'];
    $subtotal = (float)$item_db['subtotal'];
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

    // Buscar personalizações
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
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Pedido #<?= $id_pedido_atual ?></title>
    <link rel="stylesheet" href="css/cliente.css">
    <script src="js/darkmode1.js"></script>
    <script src="js/sidebar2.js"></script>
</head>
<body>

<header class="topbar">
  <div class="container">
    <button class="menu-btn-mobile" id="menuBtnMobile">&#9776;</button>
    
    <div class="logo">
      <a href="index.php">
        <img src="icones/logo.png" alt="Logo" class="logo-img">
      </a>
    </div>

    <div class="links-menu">
      <a href="admin_lista_pedidos_finalizar.php">Voltar à Lista</a>
      <a href="cardapio.php?modo=admin_pedido">Novo Pedido</a>
    </div>

    <div class="acoes-usuario">
      <img id="darkToggle" class="dark-toggle" src="icones/lua.png" alt="Modo escuro">
      <?php if ($usuario): 
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
              <a href="logout.php"><img class="iconelogout" src="icones/logout1.png"> Sair</a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="conteudo finalizar-container">
    <h2 class="titulo-principal">Registrar Pagamento - Pedido #<?= $id_pedido_atual ?></h2>
    
    <form method="post" action="admin_registra_pagamento.php">
        <h3>Itens no Pedido:</h3>
      
        <?php foreach ($itens_pedido_formatado as $item): ?>
            <div class="card">
                <img src="<?= htmlspecialchars($item['imagem_principal'] ?? 'imagens/sem_imagem.jpg') ?>" alt="Imagem">
                <div class="info">
                    <h3><?= htmlspecialchars($item['quantidade']) ?>x <?= htmlspecialchars($item['nome_produto']) ?></h3>

                    <?php if (!empty($item['ingredientes_incrementados'])): ?>
                        <div class="ingrjedientes-personalizados_extra">
                            <h4>Ingredientes Extra</h4>
                            <div class="ingredientes-container">
                                <?php foreach ($item['ingredientes_incrementados'] as $ing_inc): ?>
                                    <div class='ingrediente-card'>
                                        <img src="<?= htmlspecialchars($ing_inc['caminho_imagem']) ?>" alt="Extra">
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
                                        <img src="<?= htmlspecialchars($ing_red['caminho_imagem']) ?>" alt="Removido">
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

                    <p class="subtotal">
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

        <h3>Método de Pagamento</h3>
        <div class="form-registro-pagamento-manual">
            <label for="metodo_pagamento">Selecione o Método:</label>
            <select id="metodo_pagamento" name="metodo_pagamento" required onchange="toggleValorRecebido()">
                <option value=""> Escolha </option>
                <option value="Dinheiro em espécie">  Dinheiro Em Espécie</option>
                <option value="VISA"> VISA</option>
                <option value="E-mola"> eMola</option>
                <option value="M-pesa"> M-Pesa</option>
                <option value="Mkesh"> Mkesh</option>
            </select>

            <div id="campo_valor_recebido" style="display: none; margin-top: 15px;">
                <label for="valor_recebido">Valor Recebido (MZN):</label>
                <input type="number" step="0.01" id="valor_recebido" name="valor_recebido"
                    placeholder="Ex: <?= number_format($total_original, 2, '.', '') ?>">
                
                <label for="troco_admin">Troco:</label>
                <input type="text" id="troco_admin" disabled placeholder="0,00 MZN" style="color: blue; font-weight: bold;">
            </div>

            <input type="hidden" name="id_pedido" value="<?= $id_pedido_atual ?>">
            <input type="hidden" id="total_pedido_hidden" name="total_pedido" value="<?= $total_original ?>">
        </div>

        <button class="btn-finalizar btn-finalizar-pedido" type="submit">
             Confirmar Pagamento
        </button>
    </form>
</div>

<script>
function toggleValorRecebido() {
    const metodo = document.getElementById('metodo_pagamento').value;
    const campoValor = document.getElementById('campo_valor_recebido');
    const inputValor = document.getElementById('valor_recebido');
    
    if (metodo === 'Dinheiro em espécie') {
        campoValor.style.display = 'block';
        inputValor.required = true;
    } else {
        campoValor.style.display = 'none';
        inputValor.required = false;
        inputValor.value = '';
        document.getElementById('troco_admin').value = '0,00 MZN';
    }
}

document.getElementById('valor_recebido').addEventListener('input', function() {
    const total = parseFloat(document.getElementById('total_pedido_hidden').value);
    const valorRecebido = parseFloat(this.value);
    let troco = 0;
    
    if (!isNaN(valorRecebido) && valorRecebido >= total) {
        troco = valorRecebido - total;
    }
    
    document.getElementById('troco_admin').value = troco.toFixed(2).replace('.', ',') + ' MZN';
});
</script>
</body>
</html>