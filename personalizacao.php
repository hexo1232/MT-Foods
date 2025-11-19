<?php
session_start();
include "conexao.php";
include "verifica_login_opcional.php"; 

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_GET['id_produto']) || empty($_GET['id_produto'])) {
    echo "Produto não encontrado.";
    exit;
}

$id_produto = intval($_GET['id_produto']);

// -----------------------------------------------------
// 🎯 NOVO: 1. Detecta o modo de pedido manual (admin)
// -----------------------------------------------------
$modo_admin = ($_GET['modo'] ?? '') === 'admin_pedido';
$admin_query_param = $modo_admin ? '?modo=admin_pedido' : '';


$usuario = $_SESSION['usuario'] ?? null;
$id_usuario = $usuario['id_usuario'] ?? 0;

// -----------------------------------------------------
// 🎯 NOVO: 2. Define o destino do formulário
// -----------------------------------------------------
if ($modo_admin) {
    $destino_processamento = 'admin_processa_item_personalizado.php'; 
    $redirecionamento_carrinho = 'admin_finalizar_pedido.php';
} else {
    $destino_processamento = 'adicionar_carrinho_personalizado.php'; 
    $redirecionamento_carrinho = 'ver_carrinho.php';
}


// Busca produto com o preco_promocional
$stmt = $conexao->prepare("
    SELECT 
        p.*,p.preco_promocional, p.preco,
        GROUP_CONCAT(c.nome_categoria SEPARATOR ', ') AS categorias_nomes,
        img.caminho_imagem AS imagem_principal
    FROM
        produto p
    LEFT JOIN
        produto_categoria pc ON p.id_produto = pc.id_produto
    LEFT JOIN
        categoria c ON pc.id_categoria = c.id_categoria
    LEFT JOIN
        produto_imagem img ON img.id_produto = p.id_produto AND img.imagem_principal = 1
    WHERE
        p.id_produto = ?
    GROUP BY p.id_produto
");
$stmt->bind_param("i", $id_produto);
$stmt->execute();
$produto = $stmt->get_result()->fetch_assoc();

if (!$produto) {
    echo "Produto não encontrado.";
    exit;
}

// // verificar se está em promoção (exemplo, tu podes ter outra lógica)
// $is_promocao = ($produto['preco_promocional'] && $produto['preco_promocional'] < $produto['preco']);

// // calcular preço base
// $preco_normal = $produto['preco'];
// $preco_promocional = $produto['preco_promocional'] ?: $produto['preco'];
// $preco_base = $is_promocao ? $preco_promocional : $preco_normal;
  
// calcular preço base - substitua a lógica anterior por esta
$preco_normal = isset($produto['preco']) ? floatval($produto['preco']) : 0.0;

// Se houver um preço promocional válido (>0) e menor que o normal, usamos como promocional
$preco_promocional = (isset($produto['preco_promocional']) && floatval($produto['preco_promocional']) > 0)
    ? floatval($produto['preco_promocional'])
    : $preco_normal;

// Determina se está em promoção (apenas quando há valor promocional válido e inferior)
$is_promocao = ($preco_promocional < $preco_normal);

// Preço base (para envio / uso inicial) é o preço atual: promocional quando em promoção, senão o normal
$preco_base = $is_promocao ? $preco_promocional : $preco_normal;






// -------------------------------------------------------------
// NOVA LÓGICA PARA BUSCAR AS CATEGORIAS DE INGREDIENTE CORRETAS
// (Mantido inalterado)
// -------------------------------------------------------------

// 1. Busca todas as IDs de categoria associadas ao produto
$stmt_prod_cats = $conexao->prepare("SELECT id_categoria FROM produto_categoria WHERE id_produto = ?");
$stmt_prod_cats->bind_param("i", $id_produto);
$stmt_prod_cats->execute();
$result_prod_cats = $stmt_prod_cats->get_result();

$ids_categorias_produto = [];
while ($row = $result_prod_cats->fetch_assoc()) {
    $ids_categorias_produto[] = $row['id_categoria'];
}

// Se o produto não tem categorias, não pode ter ingredientes associados por categoria.
if (empty($ids_categorias_produto)) {
    $ids_categoriadoingrediente = [];
} else {
    // 2. Busca todas as IDs de categoria de ingrediente associadas às categorias do produto
    $placeholders = implode(',', array_fill(0, count($ids_categorias_produto), '?'));
    $sql_ingr_cats = "SELECT DISTINCT id_categoriadoingrediente FROM categoria_produto_ingrediente WHERE id_categoria IN ($placeholders)";
    $stmt_ingr_cats = $conexao->prepare($sql_ingr_cats);
    
    // Bind dinâmico dos parâmetros
    $types = str_repeat('i', count($ids_categorias_produto));
    $stmt_ingr_cats->bind_param($types, ...$ids_categorias_produto);
    $stmt_ingr_cats->execute();
    $result_ingr_cats = $stmt_ingr_cats->get_result();
    
    $ids_categoriadoingrediente = [];
    while ($row = $result_ingr_cats->fetch_assoc()) {
        $ids_categoriadoingrediente[] = $row['id_categoriadoingrediente'];
    }
}
// -------------------------------------------------------------
// FIM DA LÓGICA
// -------------------------------------------------------------


// Busca imagens do produto
$imagens = $conexao->query("SELECT * FROM produto_imagem WHERE id_produto = $id_produto");
$galeria = [];
$principal = null;
while ($img = $imagens->fetch_assoc()) {
    if ($img['imagem_principal']) {
        $principal = $img;
    } else {
        $galeria[] = $img;
    }
}





// Lógica de verificação de estoque baseada nos ingredientes
// Define uma quantidade máxima inicial muito alta
$quantidade_maxima_possivel = PHP_INT_MAX;

// Busca os ingredientes associados ao produto para calcular o estoque limitante
$stmt_ingr_assoc = $conexao->prepare("
    SELECT i.quantidade_estoque, pi.quantidade_ingrediente
    FROM produto_ingrediente pi
    JOIN ingrediente i ON pi.id_ingrediente = i.id_ingrediente
    WHERE pi.id_produto = ?
");
$stmt_ingr_assoc->bind_param("i", $id_produto);
$stmt_ingr_assoc->execute();
$res_ingr_assoc = $stmt_ingr_assoc->get_result();

// Se não houver ingredientes associados, o produto não pode ser feito.
if ($res_ingr_assoc->num_rows === 0) {
    $quantidade_maxima_possivel = 0;
} else {
    while ($ingr = $res_ingr_assoc->fetch_assoc()) {
        $estoque = intval($ingr['quantidade_estoque']);
        $uso = floatval($ingr['quantidade_ingrediente']);
        
        // Evita divisão por zero
        if ($uso > 0) {
            $max_por_ingrediente = floor($estoque / $uso);
            // O ingrediente limitante define a quantidade máxima
            if ($max_por_ingrediente < $quantidade_maxima_possivel) {
                $quantidade_maxima_possivel = $max_por_ingrediente;
            }
        }
    }
}
$produto_disponivel = ($quantidade_maxima_possivel > 0);

// Busca ingredientes associados ao produto (mantido para a lógica de exibição)
$ingredientes_associados = [];
$res = $conexao->query("
    SELECT pi.id_ingrediente, i.quantidade_estoque 
    FROM produto_ingrediente pi 
    JOIN ingrediente i ON pi.id_ingrediente = i.id_ingrediente 
    WHERE pi.id_produto = $id_produto
");
while ($linha = $res->fetch_assoc()) {
    $ingredientes_associados[] = $linha;
}




?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produto['nome_produto']) ?> - Personalização</title>
    
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
        <img src="icones/logo.png" class="logo-img" alt="Logo">
      </a>
    </div>

    <!-- 🔹 LINKS DESKTOP -->
    <div class="links-menu">
      <?php if ($usuario): ?>
        <a href="cardapio.php<?= $admin_query_param ?>"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar">Voltar</a>
      <?php else: ?>
        <a href="login.php">Fazer Login</a>
        <a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar">Voltar</a>
      <?php endif; ?>
    </div>

    <!-- 🔹 AÇÕES DO USUÁRIO -->
    <div class="acoes-usuario">

      <!-- Dark Mode -->
      <img id="darkToggle" class="dark-toggle" src="icones/lua.png" alt="Modo escuro">

      <!-- Perfil Desktop -->
      <?php if ($usuario): ?>
        <?php
          $nome2 = $usuario['nome'] ?? '';
          $apelido = $usuario['apelido'] ?? '';
          $iniciais = strtoupper(substr($nome2,0,1) . substr($apelido,0,1));
          $nomeCompleto = "$nome2 $apelido";

          function gerarCor($t){
            $h = md5($t);
            return "rgb(".hexdec(substr($h,0,2)).",".hexdec(substr($h,2,2)).",".hexdec(substr($h,4,2)).")";
          }
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
      <li><a href="cardapio.php<?= $admin_query_param ?>"><img class="" src="icones/voltar1.png" alt="Logout" title="voltar"> Voltar ao Cardápio</a></li>
    <?php else: ?>
      <li><a href="login.php">Fazer Login</a></li>
      <li><a href="cardapio.php"><img class="" src="icones/voltar1.png" alt="Logout" title="voltar"> Voltar</a></li>
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
        <div class="sidebar-user-avatar" style="background-color: <?= $corAvatar ?>;">
          <?= $iniciais ?>
        </div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= $nome2 ?></div>
          <div class="sidebar-user-email"><?= $usuario["email"] ?></div>
        </div>
        <span id="sidebarArrow" class="dropdown-arrow">▲</span>
      </div>
    </div>
  <?php endif; ?>
</nav>

<!-- Overlay -->
<div id="menuOverlay" class="menu-overlay hidden"></div>



<div class="conteudo">
    <h2>Personalizar <?= htmlspecialchars($produto['nome_produto']) ?> <?= $modo_admin ? ' (Modo Admin)' : '' ?></h2>
    <div class="produto">
        <div>
            <div class="imagem-principal">
                <img id="img-principal" src="<?= $principal ? $principal['caminho_imagem'] : 'sem_foto.png' ?>" alt="Imagem principal">
            </div>
            <?php if (count($galeria) > 0): ?>
                <div class="galeria">
                    <?php foreach ($galeria as $img): ?>
                        <div>
                            <img src="<?= $img['caminho_imagem'] ?>" onclick="trocarImagem(this.src)">
                            <div class="legenda"><?= htmlspecialchars($img['legenda']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="info">
            <p><strong>Categoria:</strong> <?= htmlspecialchars($produto['categorias_nomes']) ?></p>
            <p><strong>Descrição:</strong><br><?= nl2br(htmlspecialchars($produto['descricao'])) ?></p>
            <?php if ($produto_disponivel): ?>
                <p> <span style="color:green;">Disponível</span></p>
            <?php else: ?>
                <p> <span style="color:red;">Indisponível</span></p>
            <?php endif; ?>
            
           <form id="form-personalizacao" method="post" action="<?= $destino_processamento ?>"onsubmit="<?= $modo_admin ? 'return true;' : 'enviarFormulario(event)' ?>">
            
            <input type="hidden" name="id_produto" value="<?= $id_produto ?>">
            <input type="hidden" name="nome" value="<?= htmlspecialchars($produto['nome_produto']) ?>">
            <input type="hidden" id="preco-hidden" name="preco" value="<?= number_format($preco_base, 2, '.', '') ?>">
            <input type="hidden" id="valor-adicional" name="valor_adicional" value="0">
            <?php if ($modo_admin): ?>
                <input type="hidden" name="modo_admin" value="admin_pedido">
            <?php endif; ?>
                
                <label><strong>Ingredientes Associados:</strong></label><br>

                <div class="ingredientes-container">
                <?php
                if (!empty($ids_categoriadoingrediente)) {
                    $placeholders_ingr = implode(',', array_fill(0, count($ids_categoriadoingrediente), '?'));
                    $sql = "SELECT 
                        i.id_ingrediente, 
                        i.nome_ingrediente,
                        i.preco_adicional,
                        i.quantidade_estoque,
                        iim.caminho_imagem,
                        pi.quantidade_ingrediente,
                        i.descricao
                    FROM ingrediente i
                    JOIN categoriadoingrediente_ingrediente ci 
                        ON i.id_ingrediente = ci.id_ingrediente 
                    LEFT JOIN ingrediente_imagem iim 
                        ON i.id_ingrediente = iim.id_ingrediente 
                        AND iim.imagem_principal = 1
                    LEFT JOIN produto_ingrediente pi 
                        ON i.id_ingrediente = pi.id_ingrediente 
                        AND pi.id_produto = ?
                    WHERE ci.id_categoriadoingrediente IN ($placeholders_ingr)
                    ORDER BY i.id_ingrediente DESC";

                    $stmt = $conexao->prepare($sql);
                    $types = 'i' . str_repeat('i', count($ids_categoriadoingrediente));
                    $params = array_merge([$id_produto], $ids_categoriadoingrediente);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $resultado = $stmt->get_result();

                    while ($ingrediente = $resultado->fetch_assoc()) {
                        $imagem = !empty($ingrediente['caminho_imagem']) ? $ingrediente['caminho_imagem'] : 'uploads/sem_imagem.png';
                        $isAssociado = in_array($ingrediente['id_ingrediente'], array_column($ingredientes_associados, 'id_ingrediente'));
                        $precoExtra = $ingrediente['preco_adicional'] > 0 ? floatval($ingrediente['preco_adicional']) : 0;
                        $estoque = intval($ingrediente['quantidade_estoque']);
                        $qtdInicial = $ingrediente['quantidade_ingrediente'] ?? 0;
                        $descricao = htmlspecialchars($ingrediente['descricao'] ?? 'Sem descrição');

                        echo "<div class='ingrediente-card' data-tooltip='{$descricao}' data-preco='{$precoExtra}' data-inicial='{$qtdInicial}' data-estoque='{$estoque}'>";
                        echo "    <div class='ingrediente-preco'>+ " . number_format($precoExtra * $qtdInicial, 2, '.', '') . " MZN</div>";
                        echo "    <img src='{$imagem}' alt='Imagem do Ingrediente'>";
                        echo "    <div class='ingrediente-nome'>{$ingrediente['nome_ingrediente']}</div>";
                        echo "    <div class='quantidade-control'>";
                        echo "      <button type='button' class='menos'>−</button>";
                        echo "      <input type='text' class='quantidade' value='{$qtdInicial}' readonly>";
                        echo "      <button type='button' class='mais'>+</button>";
                        echo "    </div>";
                        echo "    <input type='hidden' name='ingredientes[{$ingrediente['id_ingrediente']}]' class='ingrediente-hidden' value='{$qtdInicial}'>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>Nenhum ingrediente de personalização encontrado para este produto.</p>";
                }
                ?> 
                </div>

                <h3>Preço Total:</h3>



  <!-- <?php if ($is_promocao): ?>
        <p class="total-price">
            <b>Antes:</b> 
            <span id="preco-total-normal" style="text-decoration: line-through; color: #e04343ff;">
                <?= number_format($produto['preco'], 2, ',', '.') ?>
            </span> MZN <br>

            <b>Agora:</b> 
            <span id="preco-total" style="color: #28a745;">
                <?= number_format($preco_promocional, 2, ',', '.') ?>
            </span> MZN
        </p>
    <?php else: ?>
        <p class="preco-total-normal">
            <b>Total:</b> 
            <span id="total-preco-normal">
                <?= number_format($preco_normal, 2, ',', '.') ?>
            </span> MZN
        </p>
    <?php endif; ?> -->

    <?php
// Normalizamos os formatos para exibição
$preco_normal_format = number_format($preco_normal, 2, ',', '.');
$preco_promocional_format = number_format($preco_promocional, 2, ',', '.');
$preco_base_format = number_format($preco_base, 2, ',', '.');
?>

<!-- Área de preços: sempre renderizamos os elementos que o JS espera -->
<?php if ($is_promocao): ?>
    <p class="total-price">
        <b>Antes:</b>
        <span id="preco-total-normal" style="text-decoration: line-through; color: #e04343ff;">
            <?= $preco_normal_format ?>
        </span> MZN
        <br>
        <b>Agora:</b>
        <span id="preco-total" style="color: #28a745;">
            <?= $preco_promocional_format ?>
        </span> MZN
    </p>
<?php else: ?>
    <p class="total-price">
        <!-- Para produtos sem promoção mostramos só "Total" mas também deixamos o elemento #preco-total
             para o JS atualizar dinamicamente sem erros -->
        <b>Total:</b>
        <span id="preco-total">
            <?= $preco_base_format ?>
        </span> MZN
        <!-- Mantemos também preco-total-normal caso algum código espere por ele -->
        <span id="preco-total-normal" style="display:none;">
            <?= $preco_normal_format ?>
        </span>
    </p>
<?php endif; ?>


<br><br>
                <?php if ($produto_disponivel): ?>
                    <label>Quantidade:</label>
                    <div class="quantidade-control2">
                        <button type="button" class="menos-produto">−</button>
                        <input type="number" id="quantidade-input" name="quantidade" min="1" max="<?= $quantidade_maxima_possivel ?>" value="1" required>
                        <button type="button" class="mais-produto">+</button>
                    </div>
                    <br><br>
                    <button class="end" type="submit" class="btn-quantidade">
                        <?= $modo_admin ? 'Adicionar ao Pedido' : 'Adicionar ao Carrinho' ?>
                    </button>
                <?php else: ?>
                    <button disabled class="end" type="submit" class="btn-quantidade">Adicionar ao Carrinho</button>
                <?php endif; ?>
            </form>
            </div>
            
            <div id="popup" class="popup">
                <div class="popup-content">
            <h3>Produto adicionado com sucesso!</h3> 
                    <div class="popup-buttons">
                        <button class="continuar" onclick="window.location.href='cardapio.php<?= $admin_query_param ?>'">
                             <?= $modo_admin ? 'Continuar a adicionar produtos' : 'Continuar a ver produtos' ?>
                        </button>
                        <button class="carrinho" onclick="window.location.href='<?= $redirecionamento_carrinho ?>'">
                             <?= $modo_admin ? 'Ver Pedido Manual' : 'Ver carrinho' ?>
                        </button>
                        <?php if (!$modo_admin): // O admin não tem botão de checkout aqui ?>
                        <button class="checkout" onclick="window.location.href='<?= isset($_SESSION['usuario']) ? 'finalizar_pedido.php' : 'login.php?redir=finalizar_pedido.php' ?>'">Fazer pagamento</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="alert-popup" class="popup" style="display: none;">
                <div class="popup-content">
                    <span class="popup-icon">😊</span>
                    <h3 id="alert-message"></h3>
                    <button class="close-popup" onclick="hideAlertPopup()">Fechar</button>
                </div>
            </div>
        </div>
    </div>

<script>
    // Função para exibir o popup de sucesso
    function mostrarPopup() {
        const popup = document.getElementById('popup');
        popup.style.display = 'flex';
        setTimeout(() => popup.style.display = 'none', 3000);
    }

    // Troca imagem principal ao clicar em miniaturas
    function trocarImagem(caminho) {
        document.getElementById('img-principal').src = caminho;
    }

    // Popups de alerta
    function showAlertPopup(message) {
        const alertPopup = document.getElementById('alert-popup');
        const alertMessage = document.getElementById('alert-message');
        alertMessage.textContent = message;
        alertPopup.style.display = 'flex';
        setTimeout(() => alertPopup.style.display = 'none', 3000);
    }

    function hideAlertPopup() {
        document.getElementById('alert-popup').style.display = 'none';
    }

    document.addEventListener("DOMContentLoaded", function() {
        // --- BASE DE PREÇOS SEGURA ---
        const precoNormalBase = parseFloat("<?= number_format($preco_normal, 2, '.', '') ?>");
        const precoPromocionalBase = parseFloat("<?= number_format($preco_promocional, 2, '.', '') ?>");
        const isPromocao = <?= ($preco_promocional < $preco_normal && $preco_promocional > 0) ? 'true' : 'false' ?>;
        const precoBase = isPromocao ? precoPromocionalBase : precoNormalBase;

        // Referências de DOM
        const precoTotalEl = document.getElementById("preco-total");
        const precoNormalEl = document.getElementById("preco-total-normal");
        const precoHidden = document.getElementById("preco-hidden");
        const valorAdicionalEl = document.getElementById("valor-adicional");
        const quantidadeInput = document.getElementById("quantidade-input");

        const maxQuantity = parseInt(quantidadeInput.getAttribute('max'));

        function showMaxProductsAlert() {
            showAlertPopup("Ops! Você já atingiu o limite máximo deste prato por hoje 😊");
        }

        // Atualiza disponibilidade geral do produto
        function atualizarDisponibilidadeProduto() {
            const phpMax = parseInt("<?= $quantidade_maxima_possivel ?>");
            quantidadeInput.setAttribute('max', phpMax);

            if (parseInt(quantidadeInput.value) > phpMax) {
                quantidadeInput.value = phpMax;
                showMaxProductsAlert();
            }
            atualizarPreco();
        }

        // --- CÁLCULO DE PREÇOS ---
        function atualizarPreco() {
            let valorAdicional = 0;

            document.querySelectorAll(".ingrediente-card").forEach(card => {
                const precoUnit = parseFloat(card.dataset.preco) || 0;
                const qtd = parseInt(card.querySelector(".quantidade").value) || 0;
                const qtdInicial = parseInt(card.dataset.inicial) || 0;

                const precoCardEl = card.querySelector(".ingrediente-preco");
                precoCardEl.textContent = "+ " + (qtd * precoUnit).toFixed(2) + " MZN";

                const diferenca = qtd - qtdInicial;
                valorAdicional += diferenca * precoUnit;
            });

            const quantidadeProduto = parseInt(quantidadeInput.value) || 1;

            const precoPorUnidadeBase = precoBase + valorAdicional;
            const total = precoPorUnidadeBase * quantidadeProduto;

            // Exibe preços
            if (precoTotalEl) precoTotalEl.textContent = total.toFixed(2) + " MZN";
            if (precoNormalEl && isPromocao) {
                const totalNormal = (precoNormalBase + valorAdicional) * quantidadeProduto;
                precoNormalEl.textContent = totalNormal.toFixed(2) + " MZN";
            }

            // Atualiza valores escondidos
            precoHidden.value = precoBase.toFixed(2);
            valorAdicionalEl.value = valorAdicional.toFixed(2);
        }

        // --- LISTENERS DE INGREDIENTES ---
        document.querySelectorAll(".ingrediente-card").forEach(card => {
            const btnMais = card.querySelector(".mais");
            const btnMenos = card.querySelector(".menos");
            const inputQtd = card.querySelector(".quantidade");
            const hiddenInput = card.querySelector(".ingrediente-hidden");
            const maxEstoque = parseInt(card.dataset.estoque);

            btnMais.addEventListener("click", () => {
                let val = parseInt(inputQtd.value);
                if (val < maxEstoque) {
                    inputQtd.value = ++val;
                    hiddenInput.value = val;
                    atualizarDisponibilidadeProduto();
                } else {
                    showAlertPopup(`Estoque máximo atingido para ${card.querySelector('.ingrediente-nome').textContent}.`);
                }
            });

            btnMenos.addEventListener("click", () => {
                let val = parseInt(inputQtd.value) - 1;
                if (val < 0) val = 0;
                inputQtd.value = val;
                hiddenInput.value = val;
                atualizarDisponibilidadeProduto();
            });
        });

        // --- QUANTIDADE PRINCIPAL ---
        quantidadeInput.addEventListener("input", atualizarPreco);

        const btnMaisProduto = document.querySelector(".mais-produto");
        const btnMenosProduto = document.querySelector(".menos-produto");

        if (btnMaisProduto) {
            btnMaisProduto.addEventListener("click", () => {
                let currentVal = parseInt(quantidadeInput.value);
                let maxVal = parseInt(quantidadeInput.getAttribute('max'));
                if (currentVal < maxVal) {
                    quantidadeInput.value = currentVal + 1;
                    atualizarPreco();
                } else {
                    showMaxProductsAlert();
                }
            });
        }

        if (btnMenosProduto) {
            btnMenosProduto.addEventListener("click", () => {
                let currentVal = parseInt(quantidadeInput.value);
                if (currentVal > 1) {
                    quantidadeInput.value = currentVal - 1;
                    atualizarPreco();
                }
            });
        }

        atualizarDisponibilidadeProduto();

        // --- ENVIO DO FORMULÁRIO ---
        document.querySelector("form").addEventListener("submit", function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

            const id_produto = parseInt(formData.get('id_produto'));
            let quantidade = parseInt(formData.get('quantidade'));
            const precoBaseForm = parseFloat(formData.get('preco'));
            const valorAdicional = parseFloat(formData.get('valor_adicional'));
            const precoFinal = precoBaseForm + valorAdicional;
            const subtotal = quantidade * precoFinal;
            const maxQuantity = parseInt(quantidadeInput.getAttribute('max'));
            const is_admin_mode = formData.get('modo_admin') === 'admin_pedido';

            if (quantidade > maxQuantity) {
                quantidade = maxQuantity;
                quantidadeInput.value = quantidade;
                showAlertPopup(`A quantidade foi ajustada para o máximo permitido: ${quantidade}`);
                atualizarPreco();
                return;
            }

            const ingredientesReduzidos = [];
            const ingredientesIncrementados = [];

            form.querySelectorAll('.ingrediente-card').forEach(card => {
                const id_ingrediente = card.querySelector('.ingrediente-hidden').name.match(/\[(.*?)\]/)[1];
                const quantidadeAtual = parseInt(card.querySelector('.quantidade').value);
                const quantidadeInicial = parseInt(card.dataset.inicial);

                if (quantidadeAtual < quantidadeInicial) {
                    ingredientesReduzidos.push({ id_ingrediente, qtd: quantidadeInicial - quantidadeAtual });
                } else if (quantidadeAtual > quantidadeInicial) {
                    ingredientesIncrementados.push({ id_ingrediente, qtd: quantidadeAtual - quantidadeInicial });
                }
            });

            formData.append('ingredientes_reduzidos', JSON.stringify(ingredientesReduzidos));
            formData.append('ingredientes_incrementados', JSON.stringify(ingredientesIncrementados));
            formData.append('preco', precoFinal);

            if (!id_produto || isNaN(quantidade) || quantidade < 1 || isNaN(precoBaseForm) || isNaN(valorAdicional)) {
                showAlertPopup("Dados inválidos. Por favor, verifique a quantidade.");
                return;
            }

            <?php if ($usuario): ?>
                const actionUrl = form.getAttribute('action');
                fetch(actionUrl, { method: 'POST', body: formData })
                .then(res => {
                    if (res.ok || res.status === 302) {
                        if (is_admin_mode) {
                            window.location.href = 'cardapio.php?modo=admin_pedido';
                        } else {
                            mostrarPopup();
                        }
                    } else {
                        return res.text().then(text => { throw new Error(`Erro do servidor: ${text}`); });
                    }
                })
                .catch(error => {
                    console.error("Erro ao adicionar:", error);
                    showAlertPopup("Erro ao adicionar produto.");
                });
            <?php else: ?>
                let carrinho = [];
                try {
                    const raw = document.cookie.split('; ').find(row => row.startsWith('carrinho='));
                    if (raw) carrinho = JSON.parse(decodeURIComponent(raw.split('=')[1])) || [];
                } catch (e) { console.warn("Cookie inválido."); }

                const maxItems = 20;
                if (carrinho.length >= maxItems) {
                    showAlertPopup("O carrinho de visitante atingiu o limite de 20 itens. Faça o login para continuar.");
                    return;
                }

                const uuid = generateUUID();
                const novoItem = {
                    uuid,
                    id_produto,
                    quantidade,
                    preco: precoFinal,
                    subtotal,
                    ingredientes_reduzidos: ingredientesReduzidos,
                    ingredientes_incrementados: ingredientesIncrementados,
                    id_tipo_item_carrinho: 2,
                    detalhes_personalizacao: "Item personalizado."
                };

                carrinho.push(novoItem);
                document.cookie = `carrinho=${encodeURIComponent(JSON.stringify(carrinho))}; path=/; max-age=604800`;
                mostrarPopup();
            <?php endif; ?>
        });

        function generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }
    });
</script>

</body>
</html>