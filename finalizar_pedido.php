<?php

require_once "conexao.php";
include "verifica_login_opcional.php";

// Mostrar status se vier ?ref=REFERENCIA (return_url da PaySuite deverá incluir ?ref=...)
if (isset($_GET['ref'])) {
    $referencia = $_GET['ref'];
    $msg_status = null;
    $rowRef = null; // Inicializa para garantir que está disponível fora do if

    $stmtRef = $conexao->prepare("
        SELECT t.status AS trans_status, p.status_pedido, p.id_pedido
        FROM transacoes t
        LEFT JOIN pedido p ON p.id_pedido = t.id_pedido
        WHERE t.referencia = ?
        LIMIT 1
    ");
    $stmtRef->bind_param("s", $referencia);
    $stmtRef->execute();
    $resRef = $stmtRef->get_result();

    if ($rowRef = $resRef->fetch_assoc()) {

        // ==========================================================
        // LÓGICA DE STATUS DO PAGAMENTO E REFRESH AUTOMÁTICO
        // ==========================================================

        // 1. Pagamento Sucesso E Pedido JÁ criado. Redirecionar. (FIM DO FLUXO)
        if ($rowRef['trans_status'] === 'sucesso' && $rowRef['id_pedido'] !== null) {
            header("Location: sucesso.php?id_pedido=" . $rowRef['id_pedido']);
            exit;
        }

        // 2. Transação Incompleta (Sucesso sem ID OU Pendente). APLICAR REFRESH.
        // CORREÇÃO CRÍTICA: Aplica-se o refresh para dar tempo ao callback.
        elseif ($rowRef['trans_status'] === 'sucesso' || $rowRef['trans_status'] === 'pendente') {
            header("Refresh: 3; url=finalizar_pedido.php?ref=" . $referencia);

            if ($rowRef['trans_status'] === 'sucesso') {
                $msg_status = "<div class='alert-warning'>⏳ Pagamento confirmado. A processar o pedido. Por favor, aguarde 3 segundos... (Referência: {$referencia})</div>";
            } else {
                // Estado inicial quando o utilizador volta rápido demais.
                $msg_status = "<div class='alert-warning'>⏳ Pagamento pendente. Aguarde 3 segundos enquanto confirmamos. (Referência: {$referencia})</div>";
            }
        }

        // 3. Pagamento Falhou. Exibir mensagem.
        elseif ($rowRef['trans_status'] === 'falhou') {
            $msg_status = "<div class='alert-danger'>❌ Pagamento falhou. Se foi cobrado, contacte o suporte. (Referência: {$referencia})</div>";
        }

        // 4. Status Inesperado.
        else {
            $msg_status = "<div class='alert-warning'>Status da transação desconhecido. (Referência: {$referencia})</div>";
        }
    } else {
        $msg_status = "<div class='alert-secondary'>Referência de transação não encontrada.</div>";
    }

    // ==========================================================
    // IMPEDIMENTO CRÍTICO: PARAR A EXECUÇÃO SE HOUVER UM STATUS DE ESPERA/SUCESSO
    // O Refresh automático só funciona se o script parar de carregar a página.
    // ==========================================================
    if (isset($rowRef) && $rowRef['trans_status'] !== 'falhou') {
        // Exibe o HTML completo para que o Refresh funcione.
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Processando Pagamento</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            <div class="container" style="text-align: center; padding: 50px;">
                <h1>Processando o seu Pedido</h1>
                <?php echo $msg_status; ?>
                <p>Por favor, aguarde. Não feche esta página. Será redirecionado automaticamente.</p>
            </div>
        </body>
        </html>
        <?php
        exit; // Termina a execução do script para que o Refresh header funcione.
    }
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$usuario_logado = !empty($_SESSION['usuario']['id_usuario']);



// ==========================================================
// VERIFICAÇÃO DE LOGIN
// ==========================================================
if (!isset($_SESSION['usuario']['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['usuario']['id_usuario'];
// CORREÇÃO: Usando apenas o status 'activo' conforme a sua tabela
$stmtCarrinho = $conexao->prepare("SELECT * FROM carrinho WHERE id_usuario = ? AND status = 'activo'");
$stmtCarrinho->bind_param("i", $id_usuario);
$stmtCarrinho->execute();
$resultCarrinho = $stmtCarrinho->get_result();

if ($resultCarrinho->num_rows == 0) {
    echo "<p style='color:red;'>Seu carrinho está vazio.</p>";
    exit;
}

$carrinho = $resultCarrinho->fetch_assoc();
$id_carrinho = $carrinho['id_carrinho'];

// ==========================================================
// BUSCA E AGRUPAMENTO DOS ITENS DO CARRINHO
// ==========================================================
$sql_itens = "
    SELECT 
        ic.id_item_carrinho, ic.id_produto, ic.quantidade, ic.subtotal, ic.uuid, ic.id_tipo_item_carrinho,
        p.nome_produto, p.preco,
        (SELECT caminho_imagem FROM produto_imagem 
            WHERE id_produto = p.id_produto AND imagem_principal = 1 LIMIT 1
        ) AS imagem_principal
    FROM item_carrinho ic
    JOIN produto p ON ic.id_produto = p.id_produto
    WHERE ic.id_carrinho = ?
    ORDER BY ic.id_item_carrinho DESC
";

$stmt_itens = $conexao->prepare($sql_itens);
$stmt_itens->bind_param("i", $id_carrinho);
$stmt_itens->execute();
$res_itens = $stmt_itens->get_result();

$itens_carrinho_temp = [];
while ($row = $res_itens->fetch_assoc()) {
    $uuid = $row['uuid'];
    $estoque_calculado = 100;

    $itens_carrinho_temp[$uuid] = [
        'id_item_carrinho' => $row['id_item_carrinho'],
        'id_produto' => $row['id_produto'],
        'quantidade' => $row['quantidade'],
        'subtotal' => $row['subtotal'],
        'uuid' => $row['uuid'],
        'id_tipo_item_carrinho' => $row['id_tipo_item_carrinho'],
        'nome_produto' => $row['nome_produto'],
        'quantidade_estoque' => $estoque_calculado,
        'preco' => $row['preco'],
        'imagem_principal' => $row['imagem_principal'],
        'ingredientes_incrementados' => [],
        'ingredientes_reduzidos' => [],
    ];

    // Agrupamento de ingredientes (incrementados e removidos)
    $sql_ing = "
        SELECT 
            ci.id_ingrediente, ci.tipo, ci.quantidade_ajuste, 
            i.nome_ingrediente, ii.caminho_imagem
        FROM carrinho_ingrediente ci
        JOIN ingrediente i ON ci.id_ingrediente = i.id_ingrediente
        LEFT JOIN ingrediente_imagem ii ON i.id_ingrediente = ii.id_ingrediente 
            AND ii.imagem_principal = 1
        WHERE ci.id_item_carrinho = ?
    ";

    $stmt_ing = $conexao->prepare($sql_ing);
    $stmt_ing->bind_param("i", $row['id_item_carrinho']);
    $stmt_ing->execute();
    $res_ing = $stmt_ing->get_result();

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
                    'quantidade' => $quantidade_ajuste,
                    'caminho_imagem' => $caminho_imagem
                ];
            }
        } elseif ($ing['tipo'] === 'removido') {
            if (isset($grouped_removidos[$nome_ingrediente])) {
                $grouped_removidos[$nome_ingrediente]['quantidade'] += $quantidade_ajuste;
            } else {
                $grouped_removidos[$nome_ingrediente] = [
                    'nome_ingrediente' => $nome_ingrediente,
                    'quantidade' => $quantidade_ajuste,
                    'caminho_imagem' => $caminho_imagem
                ];
            }
        }
    }

    $itens_carrinho_temp[$uuid]['ingredientes_incrementados'] = array_values($grouped_extras);
    $itens_carrinho_temp[$uuid]['ingredientes_reduzidos'] = array_values($grouped_removidos);
}
$stmt_itens->close();

$itens_carrinho = array_values($itens_carrinho_temp);
$total = array_sum(array_column($itens_carrinho, 'subtotal'));

// ==========================================================
// LÓGICA DO SISTEMA DE FIDELIDADE (ADICIONADO)
// ==========================================================
$pontos_fidelidade = 0;
$data_expiracao_pontos = null;

$stmt_fidelidade = $conexao->prepare("SELECT pontos, data_ultima_compra, data_expiracao FROM fidelidade WHERE id_usuario = ?");
$stmt_fidelidade->bind_param("i", $id_usuario);
$stmt_fidelidade->execute();
$result_fidelidade = $stmt_fidelidade->get_result();

if ($result_fidelidade->num_rows > 0) {
    $fidelidade_data = $result_fidelidade->fetch_assoc();
    $pontos_fidelidade = $fidelidade_data['pontos'];
    $data_expiracao_pontos = $fidelidade_data['data_expiracao'] ?? null;

    // Se a data de expiração já passou, zera os pontos
    if ($data_expiracao_pontos && (new DateTime() > new DateTime($data_expiracao_pontos))) {
        $pontos_fidelidade = 0;
        $stmt_reset = $conexao->prepare("UPDATE fidelidade SET pontos = 0 WHERE id_usuario = ?");
        $stmt_reset->bind_param("i", $id_usuario);
        $stmt_reset->execute();
        $stmt_reset->close();
    }
}
$stmt_fidelidade->close();

$desconto_aplicado = 0;
$total_a_pagar = $total;
$pontos_gastos = 0;

// ==========================================================
// PROCESSAMENTO DO FORMULÁRIO DE PEDIDO
// ==========================================================
// ... (Linhas 283 a 310)

// ==========================================================
// PROCESSAMENTO DO FORMULÁRIO DE PEDIDO (CORRIGIDO)
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {
        // 1. CAPTURAR E VALIDAR DADOS DO FORMULÁRIO
        $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $metodo_pagamento = filter_input(INPUT_POST, 'metodo', FILTER_VALIDATE_INT);
        $idtipo_entrega = filter_input(INPUT_POST, 'entrega', FILTER_VALIDATE_INT);
        $bairro = filter_input(INPUT_POST, 'bairro', FILTER_SANITIZE_STRING); // Mudado para STRING
        $ponto_referencia = filter_input(INPUT_POST, 'ponto_referencia', FILTER_SANITIZE_STRING); // Mudado para STRING

        // Dados de fidelidade (resgate)
        $pontos_aplicar_raw = filter_input(INPUT_POST, 'pontos_aplicar', FILTER_VALIDATE_INT) ?? 0;
        
        if (!$email || !$metodo_pagamento || !$idtipo_entrega) {
            throw new Exception("Dados de pagamento ou endereço incompletos/inválidos.");
        }
        if ($idtipo_entrega == 2) {
            if (empty($bairro) || empty($ponto_referencia)) {
                throw new Exception("Para o tipo de entrega 'Delivery', o Bairro e o Ponto de Referência são obrigatórios.");
            }
        }
        // 2. BUSCAR PREÇOS E MONTAR DADOS
        
        // A. Preço de Entrega
        $stmt_entrega = $conexao->prepare("SELECT preco_adicional FROM tipo_entrega WHERE idtipo_entrega = ?");
        $stmt_entrega->bind_param("i", $idtipo_entrega);
        $stmt_entrega->execute();
        $preco_entrega = $stmt_entrega->get_result()->fetch_assoc()['preco_adicional'] ?? 0;
        $stmt_entrega->close();

        // B. Cálculo do Desconto de Fidelidade (Cálculo no SERVIDOR para segurança)
        $pontos_gastos_efetivos = min($pontos_aplicar_raw, $pontos_fidelidade); // Limitar ao que o usuário tem
        if ($pontos_gastos_efetivos < 100) $pontos_gastos_efetivos = 0; // Resgate mínimo
        
        $desconto_aplicado_server = ($pontos_gastos_efetivos / 100) * 50;
        
        // C. Total Final (Subtotal - Desconto + Entrega)
        $total_calculado = (float)$total - $desconto_aplicado_server + (float)$preco_entrega;
        $total_pedido_final = max(0.00, $total_calculado); // Garante que o total não seja negativo

        // D. Montar JSON de Endereço (para a coluna 'endereco_info' no pedido_temp)
        $endereco_json = json_encode([
            'telefone' => $telefone,
            'email' => $email,
            'bairro' => $bairro,
            'ponto_referencia' => $ponto_referencia,
            // Adicione mais campos de endereço se houver (Ex: rua, número)
        ]);
        
        // E. Montar JSON de Itens do Carrinho (para a coluna 'itens')
        $itens_json = json_encode($itens_carrinho); // O array já tem toda a info (ingredientes, etc.)

        // 3. INSERIR NA PEDIDO_TEMP E GERAR REFERÊNCIA
        $reference = strtoupper(bin2hex(random_bytes(6)));
        $returnUrl = "https://undebated-man-unrelating.ngrok-free.dev/Restaurante/finalizar_pedido.php?ref=" . $reference;
        
        $stmt_temp = $conexao->prepare("
            INSERT INTO pedido_temp (id_usuario, reference, idtipo_pagamento, idtipo_entrega, total, pontos_gastos, endereco_info, itens, bairro, ponto_referencia, telefone,data_criacao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?,?,?,?, NOW())
        ");
        // Tipos: i(usuario), s(reference), i(pagamento), i(entrega), d(total), i(pontos), s(endereco_info), s(itens)
       // Localizado na linha 327 do seu código (aproximadamente)

$stmt_temp->bind_param("isiidissssi", // CORRIGIDO: Agora tem 8 caracteres
    $id_usuario, 
    $reference, 
    $metodo_pagamento, 
    $idtipo_entrega, 
    $total_pedido_final, 
    $pontos_gastos_efetivos, 
    $endereco_json,
    $itens_json,
    $bairro,
    $ponto_referencia,
    $telefone

);
        if (!$stmt_temp->execute()) {
             throw new Exception("Falha ao criar pedido temporário: " . $stmt_temp->error);
        }
        $stmt_temp->close();


        // 4. INICIAR PAGAMENTO NA PAYSUITE (com a referência gerada)
        require_once __DIR__ . "/API/paysuite_iniciar_pagamento.php";

        // CORREÇÃO CRÍTICA: Passa o total e a referência do pedido_temp, e o returnUrl
        $resposta = iniciarPagamentoPaySuite(
            $id_usuario, 
            $total_pedido_final, 
            $metodo_pagamento, 
            $telefone, 
            $email,
            $reference, // Novo parâmetro
            $returnUrl // Novo parâmetro
        ); 

        if ($resposta['status'] === 'success') {
            // Redireciona para o checkout da PaySuite
            header("Location: " . $resposta['redirectUrl']);
            exit;
        } else {
             // Em caso de falha da API, deve-se limpar o registro temporário para evitar lixo.
             $conexao->query("DELETE FROM pedido_temp WHERE reference = '$reference'");
             echo "<p style='color:red;'>Falha ao iniciar pagamento: " . htmlspecialchars($resposta['mensagem']) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Finalizar Pedido</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <a href="ver_carrinho.php">Voltar ao Carrinho</a>
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
     <a href="ver_carrinho.php">         <img class="" src="icones/voltar1.png" alt="Logout" title="voltar">   Voltar ao Carrinho</a>
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


       

        


<div class="conteudo finalizar-container">

    <h2 class="titulo-principal">Finalizar Pedido</h2>
    
<?php if (!empty($msg_status)) echo $msg_status; ?>

    <form method="post">
        <!-- Lista de itens -->
        <?php foreach ($itens_carrinho as $item): ?>
            <div class="card-item card-item-finalizar">
                <img class="item-img item-img-finalizar" src="<?= $item['imagem_principal'] ?? 'imagens/sem_imagem.jpg' ?>" alt="Imagem">
                <div class="item-info item-info-finalizar">
                    <h3><?= htmlspecialchars($item['nome_produto']) ?></h3>
                    <p>Quantidade: <strong><?= $item['quantidade'] ?></strong></p>
                    <p class="total-item ">
                        Total: <strong><?= number_format($item['subtotal'], 2, ',', '.') ?> MZN</strong>
                    </p>

                    <!-- Ingredientes Extra -->
                    <?php if (!empty($item['ingredientes_incrementados'])): ?>
                        <div class="ingredientes-box extra ingredientes-box-finalizar">
                            <h4>Ingredientes Extra</h4>
                            <div class="ingredientes-lista ingredientes-lista-finalizar">
                                <?php foreach ($item['ingredientes_incrementados'] as $ing_inc): ?>
                                    <div class="ingrediente-card ingrediente-card-finalizar">
                                        <img src="<?= htmlspecialchars($ing_inc['caminho_imagem'] ?? 'sem_foto_ingrediente.png') ?>" 
                                             alt="<?= htmlspecialchars($ing_inc['nome_ingrediente']) ?>">
                                        <span><?= htmlspecialchars($ing_inc['nome_ingrediente']) ?> (x<?= htmlspecialchars($ing_inc['quantidade']) ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Ingredientes Removidos -->
                    <?php if (!empty($item['ingredientes_reduzidos'])): ?>
                        <div class="ingredientes-box removidos ingredientes-box-finalizar">
                            <h4>Ingredientes Removidos</h4>
                            <div class="ingredientes-lista ingredientes-lista-finalizar">
                                <?php foreach ($item['ingredientes_reduzidos'] as $ing_red): ?>
                                    <div class="ingrediente-card removido ingrediente-card-finalizar">
                                        <img src="<?= htmlspecialchars($ing_red['caminho_imagem'] ?? 'sem_foto_ingrediente.png') ?>" 
                                             alt="<?= htmlspecialchars($ing_red['nome_ingrediente']) ?>">
                                        <span><?= htmlspecialchars($ing_red['nome_ingrediente']) ?> (x<?= htmlspecialchars($ing_red['quantidade']) ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Subtotal -->
        <p class="subtotal subtotal-finalizar">
             Total original da compra: <span id="subtotal_valor_display"><?= number_format($total, 2, ',', '.') ?></span> MZN
        </p>

        <!-- Fidelidade -->
        <div class="fidelidade-section fidelidade-section-finalizar">
            <h3> Programa de Fidelidade</h3>

            <?php if ($pontos_fidelidade > 0): ?>
                <?php if ($pontos_fidelidade < 100): ?>
                    <p class="msg-alerta">
                        Você tem <?= $pontos_fidelidade ?> pontos (< 100). O resgate mínimo é 100 pontos.
                    </p>
                <?php else: ?>
                    <p class="msg-sucesso">
                        Você tem <?= $pontos_fidelidade ?> pontos disponíveis.
                    </p>

                    <div class="fidelidade-opcoes fidelidade-opcoes-finalizar">
                        <label for="pontos_aplicar">Aplicar pontos:</label>
                        <input type="number" id="pontos_aplicar" name="pontos_aplicar"
                               min="0" max="<?= $pontos_fidelidade ?>" value="0"
                               oninput="aplicarDesconto()">
                        <p> Desconto: <strong><span id="desconto_valor">0,00</span> MZN</strong></p>
                    </div>
                <?php endif; ?>

                <p class="info-secundaria">
                    100 pontos = 50 MZN de desconto.  
                    <!-- Pontos expiram em: <?= $data_expiracao_pontos ?> -->
                     Pontos expiram em: <?= date('Y-m-d', strtotime('+12 months')) ?>

                </p>
            <?php else: ?>
                <p class="msg-neutro">
                    Você não tem pontos no momento. Continue comprando para acumular!
                </p>
            <?php endif; ?>
        </div>

        <!-- Total Final -->
        <p class="total-final total-final-finalizar">
     Total atualizado com o desconto dos seus pontos: <span id="total_final_valor"><?= number_format($total, 2, ',', '.') ?></span> MZN
    </p>

        <!-- Pontos ganhos -->
        <?php $pontos_ganhos = floor($total / 10); ?>
        <p class="msg-sucesso">
             Nesta compra você ganhará <span id="pontos_ganhos"><?= $pontos_ganhos ?></span> pontos de fidelidade.
        </p>

        <!-- Dados Entrega -->
        <h3>Dados para Entrega</h3>
       <div class="form-dados-entrega-finalizar">
  <div>
    <label for="telefone">Telefone:</label>
    <input type="text" name="telefone" id="telefone" required placeholder="84/83/87">
  </div>

  <div>
    <label for="email">Email:</label>
    <input type="email" name="email" id="email" required placeholder="xyz@gmail.com">
  </div>

  <div>
    <label for="entrega">Tipo de entrega:</label>
    <select name="entrega" id="entrega" required>
      <option value="">Selecione</option>
      <?php
      $entrega2 = $conexao->query("SELECT * FROM tipo_entrega");
      while ($entre = $entrega2->fetch_assoc()) {
          echo "<option value='{$entre['idtipo_entrega']}' data-preco-adicional='{$entre['preco_adicional']}'>{$entre['nome_tipo_entrega']}</option>";
      }
      ?>
    </select>
  </div>

  <div>
    <label for="metodo">Método de Pagamento:</label>
    <select name="metodo" id="metodo" onchange="mostrarFormularioPagamento()" required>
      <option value="">Selecione</option>
      <?php
      $met = $conexao->query("SELECT * FROM tipo_pagamento");
      while ($m = $met->fetch_assoc()) {
          echo "<option value='{$m['idtipo_pagamento']}'>{$m['tipo_pagamento']}</option>";
      }
      ?>
    </select>
  </div>

  <!-- Campos exibidos apenas se entrega = delivery -->
  <div id="delivery_fields" style="display:none;">
    <label for="bairro">Bairro:</label>
    <input type="text" name="bairro" id="bairro" placeholder="Insira o nome do seu Bairro">

    <label for="ponto_referencia">Ponto de Referência:</label>
    <input type="text" name="ponto_referencia" id="ponto_referencia" placeholder="Ex: Na entrada da cidadela académica, Cruzamento Zâmbia">
  </div>
</div>


       

        <button class="btn-finalizar btn-finalizar-pedido" type="submit">Finalizar Pedido</button>
    </form>
</div>

<!-- Modal de Aviso -->
<div id="modal-alerta" class="modal-alerta" style="display:none;">
  <div class="modal-conteudo">
    <span class="fechar-modal" id="fechar-modal">&times;</span>
    <h3>Atenção</h3>
    <p>A opção de delivery tem custos adicionais, que serão somados ao seu total á pagar.</p>
  </div>
</div>


<script>
// ==========================================================
// 1. Variáveis e Constantes Globais (Definidas Apenas Uma Vez)
// ==========================================================
const subtotalOriginal = <?= $total ?>;
const selectEntrega = document.getElementById("entrega");
const deliveryFields = document.getElementById("delivery_fields");
const inputBairro = document.getElementById("bairro");
const inputPontoReferencia = document.getElementById("ponto_referencia");
const modal = document.getElementById("modal-alerta");
const fecharModal = document.getElementById("fechar-modal");

const taxasEntrega = {}; // Armazena taxas do PHP
let subtotalComEntrega = subtotalOriginal; // Variável de cálculo

// ==========================================================
// 2. Funções de Interação e Cálculo
// ==========================================================

function toggleDeliveryFields() {
    // Garante que o select tem um valor válido
    if (!selectEntrega.value) return; 

    const idtipoEntrega = parseInt(selectEntrega.value);
    
    if (idtipoEntrega === 2) { // 2 = Delivery
        deliveryFields.style.display = 'block';
        // Torna os campos obrigatórios
        inputBairro.setAttribute('required', 'required');
        inputPontoReferencia.setAttribute('required', 'required');
    } else {
        deliveryFields.style.display = 'none';
        // Remove a obrigatoriedade
        inputBairro.removeAttribute('required');
        inputPontoReferencia.removeAttribute('required');
        // Limpa os campos para evitar envio de dados vazios/inválidos
        inputBairro.value = '';
        inputPontoReferencia.value = '';
    }
}

function atualizarTotalComEntrega() {
    const selectedOption = selectEntrega.options[selectEntrega.selectedIndex];
    // Usa o atributo data-preco-adicional do HTML
    const precoAdicional = parseFloat(selectedOption.getAttribute('data-preco-adicional') || 0);
    
    subtotalComEntrega = subtotalOriginal + precoAdicional;
    
    // Atualiza o display do subtotal (que agora inclui a entrega)
    document.getElementById('subtotal_valor_display').textContent = subtotalComEntrega.toFixed(2).replace('.', ',');
    
    aplicarDesconto(); // Recalcula o total final
}

function aplicarDesconto() {
    const pontosInput = document.getElementById('pontos_aplicar');
    // Verifica se o input de pontos existe (só aparece se o usuário tiver pontos >= 100)
    if (!pontosInput) { 
        let totalFinal = subtotalComEntrega;
        document.getElementById('total_final_valor').textContent = totalFinal.toFixed(2).replace('.', ',');
        const pontosGanhos = Math.floor(totalFinal / 10);
        document.getElementById('pontos_ganhos').textContent = pontosGanhos;
        return;
    }

    const descontoSpan = document.getElementById('desconto_valor');
    const totalFinalSpan = document.getElementById('total_final_valor');
    const pontosGanhosSpan = document.getElementById('pontos_ganhos');

    let pontos = parseFloat(pontosInput.value);
    if (isNaN(pontos) || pontos < 0) pontos = 0;

    const pontosDisponiveis = <?= $pontos_fidelidade ?>;
    if (pontos > pontosDisponiveis) {
        pontos = pontosDisponiveis;
        pontosInput.value = pontosDisponiveis;
    }

    const desconto = (pontos / 100) * 50;
    descontoSpan.textContent = desconto.toFixed(2).replace('.', ',');

    let totalFinal = subtotalComEntrega - desconto;
    if (totalFinal < 0) totalFinal = 0;
    totalFinalSpan.textContent = totalFinal.toFixed(2).replace('.', ',');

    const pontosGanhos = Math.floor(totalFinal / 10);
    pontosGanhosSpan.textContent = pontosGanhos;
}

// function mostrarFormularioPagamento() { ... } // Comentada, mantida por consistência

// ==========================================================
// 3. Event Listeners e Inicialização
// ==========================================================

// Inicializa taxas de entrega (bloco PHP)
<?php
$entrega3 = $conexao->query("SELECT idtipo_entrega, preco_adicional FROM tipo_entrega");
while ($entre = $entrega3->fetch_assoc()) {
    echo "taxasEntrega[{$entre['idtipo_entrega']}] = {$entre['preco_adicional']};";
}
?>

// Ações ao carregar a página
document.addEventListener('DOMContentLoaded', () => {
    // 1. Inicializa o estado dos campos de entrega (escondido ou visível)
    toggleDeliveryFields(); 
    // 2. Calcula e exibe o total inicial (com entrega padrão, se houver, e desconto padrão 0)
    atualizarTotalComEntrega(); 
}); 

// Listener para Mudança na Seleção de Entrega
selectEntrega.addEventListener('change', toggleDeliveryFields);
selectEntrega.addEventListener('change', atualizarTotalComEntrega);

// Listener para Mudança na Aplicação de Pontos
const pontosInput = document.getElementById('pontos_aplicar');
if (pontosInput) {
    pontosInput.addEventListener('input', aplicarDesconto); 
}

// Lógica do Modal de Aviso (mantida)
selectEntrega.addEventListener('focus', function () {
    if (!this.getAttribute('data-alerted')) {
        modal.style.display = "flex"; // Mostra o modal
        this.setAttribute('data-alerted', 'true');
    }
});

// Fecha modal ao clicar no X
fecharModal.addEventListener("click", () => modal.style.display = "none");

// Fecha modal ao clicar fora do conteúdo
window.addEventListener("click", (e) => {
    if (e.target === modal) modal.style.display = "none";
});

</script>
</body>
</html>