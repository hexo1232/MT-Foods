<?php
// Inclui o arquivo de conexão com o banco de dados
session_start();
include "conexao.php";
include "verifica_login_opcional.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Define o fuso horário
date_default_timezone_set('Africa/Maputo');


// -----------------------------------------------------
// 🎯 NOVO: 1. Detecta o modo de pedido manual (admin)
// -----------------------------------------------------
$modo_admin = ($_GET['modo'] ?? '') === 'admin_pedido';
$admin_query_param = $modo_admin ? '&modo=admin_pedido' : '';

/**
 * Calcula a quantidade máxima de um produto que pode ser produzida com base no estoque de ingredientes.
 * * @param mysqli $conexao A conexão com o banco de dados.
 * @param int $id_produto O ID do produto a ser verificado.
 * @return int A quantidade máxima possível, ou 0 se o produto não puder ser feito.
 */
function calcularQuantidadeMaxima($conexao, $id_produto) {
    // 1. Obtém os ingredientes necessários para o produto
    $sql = "
        SELECT 
            pi.quantidade_ingrediente AS quantidade_necessaria,
            i.quantidade_estoque AS quantidade_disponivel
        FROM 
            produto_ingrediente pi
        JOIN 
            ingrediente i ON pi.id_ingrediente = i.id_ingrediente
        WHERE 
            pi.id_produto = ?
    ";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_produto);
    $stmt->execute();
    $result = $stmt->get_result();

    $quantidades_possiveis = [];

    // 2. Para cada ingrediente, calcula quantas porções do produto podem ser feitas
    while ($row = $result->fetch_assoc()) {
        $quantidade_necessaria = (int)$row['quantidade_necessaria'];
        $quantidade_disponivel = (int)$row['quantidade_disponivel'];

        // Evita divisão por zero
        if ($quantidade_necessaria > 0) {
            $quantidades_possiveis[] = floor($quantidade_disponivel / $quantidade_necessaria);
        } else {
            // Se a quantidade necessária for 0, o ingrediente não limita
            $quantidades_possiveis[] = PHP_INT_MAX;
        }
    }
    
    // 3. A quantidade máxima é o menor valor entre as porções calculadas
    if (empty($quantidades_possiveis)) {
        return 0; // Nenhum ingrediente associado, então não pode ser produzido
    }
    
    return min($quantidades_possiveis);
}

// ----------------------------------------------------
// A Lógica para a Página de Detalhes
// ----------------------------------------------------

if (!isset($_GET['id_produto']) || empty($_GET['id_produto'])) {
    echo "Produto não encontrado.";
    exit();
}

$id_produto = intval($_GET['id_produto']);

// Busca produto (não precisamos mais da coluna quantidade_estoque aqui)
$stmt = $conexao->prepare("SELECT   
        p.*, p.preco_promocional, p.preco,
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
    where
        p.id_produto=?");
        
$stmt->bind_param("i", $id_produto);
$stmt->execute();
$produto = $stmt->get_result()->fetch_assoc();

if (!$produto) {
    echo "Produto não encontrado.";
    exit();
}

// Obtém as imagens do produto
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

// Obtém os ingredientes associados ao produto e seus estoques
$sql_ingredientes = "
    SELECT 
        i.id_ingrediente, 
        i.nome_ingrediente,
        i.preco_adicional,
        i.quantidade_estoque, 
        iim.caminho_imagem,
        i.descricao
    FROM produto_ingrediente pi
    INNER JOIN ingrediente i ON pi.id_ingrediente = i.id_ingrediente
    LEFT JOIN ingrediente_imagem iim ON i.id_ingrediente = iim.id_ingrediente AND iim.imagem_principal = 1
    WHERE pi.id_produto = ?
    ORDER BY i.id_ingrediente DESC";

$stmt_ing = $conexao->prepare($sql_ingredientes);
$stmt_ing->bind_param("i", $id_produto);
$stmt_ing->execute();
$ingredientes_associados = $stmt_ing->get_result();

// 💡 NOVO: Conta o número de ingredientes associados.
$num_ingredientes = $ingredientes_associados->num_rows;

// Calcula a quantidade máxima inicial do produto usando a nova função
$quantidade_maxima_possivel = calcularQuantidadeMaxima($conexao, $id_produto);
$disponivel = $quantidade_maxima_possivel > 0;

$usuario = $_SESSION['usuario'] ?? null;
$id_usuario = $usuario['id_usuario'] ?? 0;
$is_admin = ($usuario && ($usuario['idperfil'] ?? 0) == 1); 
// -----------------------------------------------------
// 🎯 NOVO: 2. Define o destino do formulário
// -----------------------------------------------------
if ($modo_admin) {
    // Se estiver no modo admin (URL &modo=admin_pedido), usa o processador do admin
    $destino_processamento = 'admin_processa_item.php'; 
    $redirecionamento_carrinho = 'admin_finalizar_pedido.php';
} else {
    // Caso contrário, usa o processador normal do carrinho do cliente
    $destino_processamento = 'adicionar_carrinho.php'; 
    $redirecionamento_carrinho = 'ver_carrinho.php';
}


// ✨ NOVA LÓGICA: Determina o preço final a ser exibido e usado nos cálculos.
// Isso garante que o valor seja o mesmo para o HTML e o JavaScript.
$preco_final = $produto['preco'];
$is_promocao = false;
if (stripos($produto['categorias_nomes'], 'Promoções da semana') !== false) {
    if ($produto['preco_promocional'] && $produto['preco_promocional'] < $produto['preco']) {
        $preco_final = $produto['preco_promocional'];
        $is_promocao = true;
    }
}
?>


<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produto['nome_produto']) ?> - Detalhes</title>
     <link rel="stylesheet" href="css/cliente.css">
           <script src="js/darkmode1.js"></script>
                <script src="js/dropdown.js"></script> 
                         <script src="js/sidebar2.js"></script> 
            
     <?php if ($usuario): ?>
        <script src="logout_auto.js"></script>
    <?php endif; ?>
    
    
    
 
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

        <?php if ($is_admin): ?>
          <a href="cardapio.php?modo=admin_pedido"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar">Voltar</a>
          
        <?php else: ?>
          <a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar">Voltar</a>
          <a href="logout.php"></a>
        <?php endif; ?>

      <?php else: ?>
        <a href="login.php">Fazer Login</a>
        <a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar">Voltar</a>
      <?php endif; ?>
    </div>

    <!-- 🔹 AÇÕES DO USUÁRIO -->
    <div class="acoes-usuario">

      <!-- Dark Mode -->
      <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Alternar modo escuro" title="Alternar modo escuro">

      <!-- Perfil Desktop -->
      <?php if ($usuario): ?>

        <?php
          $nome2 = $usuario['nome'] ?? '';
          $apelido = $usuario['apelido'] ?? '';
          $iniciais = strtoupper(substr($nome2, 0, 1) . substr($apelido, 0, 1));
          $nomeCompleto = "$nome2 $apelido";

          function gerarCor($texto) {
              $hash = md5($texto);
              $r = hexdec(substr($hash, 0, 2));
              $g = hexdec(substr($hash, 2, 2));
              $b = hexdec(substr($hash, 4, 2));
              return "rgb($r, $g, $b)";
          }

          $corAvatar = gerarCor($nomeCompleto);
        ?>

        <div class="usuario-info usuario-desktop" id="usuarioDropdown">
          <div class="usuario-dropdown">
            <div class="usuario-iniciais" style="background-color: <?= $corAvatar ?>;">
              <?= $iniciais ?>
            </div>
            <div class="usuario-nome"><?= $nomeCompleto ?></div>

            <div class="menu-perfil" id="menuPerfil">
              <a href="editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>">
              <img class="icone" src="icones/user1.png" alt="Editar" title="Editar">  
              Editar Dados Pessoais</a>
              <a href="alterar_senha2.php">
              <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar">   
              Alterar Senha</a>
              <a href="logout.php">
                <img class="iconelogout" src="icones/logout1.png" alt=""> Sair
              </a>
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

      <?php if ($is_admin): ?>
        <li><a href="cardapio.php?modo=admin_pedido">Voltar</a></li>
        <!-- <li><a href="logout.php"><span>🚪</span> Logout</a></li> -->

      <?php else: ?>
        <li><a href="cardapio.php">Voltar</a></li>
        <!-- <li><a href="logout.php"><span>🚪</span> Logout</a></li> -->
      <?php endif; ?>

    <?php else: ?>
      <li><a href="login.php">Fazer Login</a></li>
      <li><a href="cardapio.php">Voltar</a></li>
    <?php endif; ?>
  </ul>


  <?php if ($usuario): ?>
    <!-- 🔹 DROPDOWN PERFIL MOBILE (rodapé) -->
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
    <h2><?= htmlspecialchars($produto['nome_produto']) ?> <?= $modo_admin ? ' (Modo Admin)' : '' ?></h2>
    <div class="produto">
        <div>
            <div class="imagem-principal">
                <img id="img-principal" src="<?= $principal ? htmlspecialchars($principal['caminho_imagem']) : 'sem_foto.png' ?>" alt="Imagem principal">
            </div>
            <?php if (count($galeria) > 0): ?>
                <div class="galeria">
                    <?php foreach ($galeria as $img): ?>
                        <div>
                            <img src="<?= htmlspecialchars($img['caminho_imagem']) ?>" onclick="trocarImagem(this.src)">
                            <div class="legenda"><?= htmlspecialchars($img['legenda']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="info">
            <p><strong>Categoria:</strong> <?= htmlspecialchars($produto['categorias_nomes']) ?></p>
            <p><strong>Descrição:</strong><br><?= nl2br(htmlspecialchars($produto['descricao'])) ?></p>

            <?php if ($disponivel): ?>
                <p><span style="color:green;">Disponível</span></p>
            <?php else: ?>
                <p><span style="color:red;">Indisponível (Sem estoque de ingredientes)</span></p>
            <?php endif; ?>

            <?php 
            // 1. Oculta a lista de ingredientes se houver 1 ou 0 ingredientes
            if ($num_ingredientes > 1): 
            ?>
            
            <label><strong>Ingredientes Associados:</strong></label><br>
            <div class="ingredientes-container">
            <?php
            // Movemos a consulta para um novo bloco, para garantir que os ingredientes_associados
            // são exibidos corretamente.
            $sql_ingredientes = "
                SELECT 
                  i.id_ingrediente, 
    i.nome_ingrediente,
    pi.quantidade_ingrediente,
    i.preco_adicional,
    i.quantidade_estoque, 
    iim.caminho_imagem,
    i.descricao
                FROM produto_ingrediente pi
                INNER JOIN ingrediente i ON pi.id_ingrediente = i.id_ingrediente
                LEFT JOIN ingrediente_imagem iim ON i.id_ingrediente = iim.id_ingrediente AND iim.imagem_principal = 1
                WHERE pi.id_produto = ?
                ORDER BY i.id_ingrediente DESC";

            $stmt_ing = $conexao->prepare($sql_ingredientes);
            $stmt_ing->bind_param("i", $id_produto);
            // É necessário reexecutar a consulta para que o ponteiro do resultado volte ao início
            // antes de começar o while.
            $stmt_ing->execute();
            $ingredientes_associados_loop = $stmt_ing->get_result(); // Novo nome para evitar conflito

            while ($ingrediente = $ingredientes_associados_loop->fetch_assoc()) {
                $imagem = !empty($ingrediente['caminho_imagem']) ? htmlspecialchars($ingrediente['caminho_imagem']) : 'uploads/sem_imagem.png';
                $precoExtra = ($ingrediente['preco_adicional'] > 0) ? ' (+ ' . number_format($ingrediente['preco_adicional'], 2, ',', '.') . ' MZN)' : '';
                $descricao = htmlspecialchars($ingrediente['descricao'] ?? 'Sem descrição');
                $nome_ingrediente = htmlspecialchars($ingrediente['nome_ingrediente']);
                
                // Verifica a disponibilidade do ingrediente
                $ingrediente_disponivel = $ingrediente['quantidade_estoque'] > 0;
                $classe_disponibilidade = $ingrediente_disponivel ? '' : 'indisponivel';
                $tooltip = $ingrediente_disponivel ? $descricao : $descricao . " (Esgotado)";

            $quantidade_usada = intval($ingrediente['quantidade_ingrediente']); 
$quantidade_label = $quantidade_usada . "x";

echo "<div class='ingrediente-card $classe_disponibilidade' data-tooltip='{$tooltip}'>";

echo "<div class='ingrediente-quantidade'>{$quantidade_label}</div>";

echo "<img src='{$imagem}' alt='Imagem do Ingrediente'>";

echo "<div class='ingrediente-info'>{$nome_ingrediente}</div>";

echo "</div>";

            }
            ?>
            </div>
            <?php endif; // Fim da condição de $num_ingredientes > 1 ?>

        <form method="post" action="<?= $destino_processamento ?>" onsubmit="enviarFormulario(event)">

    <input type="hidden" name="id_produto" value="<?= $id_produto ?>">
    <?php if ($modo_admin): ?>
        <input type="hidden" name="modo_admin" value="admin_pedido">
    <?php endif; ?>

    <input type="hidden"  id="preco-normal-base" value="<?= number_format($produto['preco'], 2, '.', '') ?>">
    <input type="hidden" id="preco-promocional-base" value="<?= number_format($preco_final, 2, '.', '') ?>">
    <input type="hidden" id="quantidade-maxima" value="<?= $quantidade_maxima_possivel ?>">

    <br><br>
    <label>Quantidade:</label>
    <div class="quantidade-control">
        <button type="button" class="btn-quantidade" onclick="alterarQuantidade(-1)">-</button>
        <span id="quantidade-display">1</span>
        <button type="button" class="btn-quantidade" onclick="alterarQuantidade(1)">+</button>
    </div>
    
    <?php if ($is_promocao): ?>
        <p class="total-price">
            <b>Antes:</b> 
            <span id="total-preco-normal" style="text-decoration: line-through; color: #e04343ff;">
                <?= number_format($produto['preco'], 2, ',', '.') ?>
            </span> MZN <br>

            <b>Agora:</b> 
            <span id="total-preco-promocional" style="color: #28a745;">
                <?= number_format($preco_final, 2, ',', '.') ?>
            </span> MZN
        </p>
    <?php else: ?>
        <p class="total-price">
            <b>Total:</b> 
            <span id="total-preco-normal">
                <?= number_format($preco_final, 2, ',', '.') ?>
            </span> MZN
        </p>
    <?php endif; ?>

    <br><br>
    
<?php if ($disponivel): ?>
    <?php
    // 2. Oculta o botão de personalização se houver 1 ou 0 ingredientes
    if ($num_ingredientes > 1): 
    ?>
    <a href="personalizacao.php?id_produto=<?= $produto['id_produto'] ?><?= $admin_query_param ?>" class="personalizacao">Personalizar a refeição</a><br><br>
    <?php endif; ?>
    
    <button type="submit" class="end btn-carrinho">
        <?= $modo_admin ? 'Adicionar ao Pedido' : 'Adicionar ao Carrinho' ?>
    </button><br>
<?php else: ?>
    <button type="submit" class="end btn-carrinho" disabled>
        Indisponível
    </button><br>
<?php endif; ?>



</form>

        </div>
    </div>
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

<div id="popup-aviso" class="popup-aviso">
    <div class="popup-aviso-content">
        <h3>Aviso</h3>
        <p id="aviso-mensagem"></p>
    </div>
</div>

<script>
function trocarImagem(src) {
    document.getElementById('img-principal').src = src;
}

// Mostra o popup de confirmação e o esconde após 3 segundos
function mostrarPopup() {
    const popup = document.getElementById('popup');
    popup.style.display = 'flex'; // Use 'flex' para centralizar
    setTimeout(() => popup.style.display = 'none', 3000); // auto-esconde
}

// Mostra o pop-up de aviso com uma mensagem
function mostrarAviso(mensagem) {
    const popupAviso = document.getElementById('popup-aviso');
    const avisoMensagem = document.getElementById('aviso-mensagem');
    avisoMensagem.textContent = mensagem;
    popupAviso.style.display = 'flex';
    setTimeout(() => popupAviso.style.display = 'none', 3000);
}

// Calcula e atualiza o preço total dinamicamente
function atualizarTotal() {
    const quantidade = parseInt(document.getElementById('quantidade-display').textContent);

    const precoNormalBaseEl = document.getElementById('preco-normal-base');
    const precoPromocionalBaseEl = document.getElementById('preco-promocional-base');

    if (precoNormalBaseEl && precoPromocionalBaseEl && precoNormalBaseEl.value !== precoPromocionalBaseEl.value) {
        // Produto em promoção
        const precoNormalBase = parseFloat(precoNormalBaseEl.value);
        const precoPromocionalBase = parseFloat(precoPromocionalBaseEl.value);

        const totalNormal = quantidade * precoNormalBase;
        const totalPromocional = quantidade * precoPromocionalBase;

        document.getElementById('total-preco-normal').textContent = totalNormal.toLocaleString('pt-MZ', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        document.getElementById('total-preco-promocional').textContent = totalPromocional.toLocaleString('pt-MZ', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else {
        // Produto sem promoção (só mostra 1 total, mas ainda é dinâmico)
        const precoNormalBase = parseFloat(precoPromocionalBaseEl.value);
        const total = quantidade * precoNormalBase;

        document.getElementById('total-preco-normal').textContent = total.toLocaleString('pt-MZ', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}
// Altera a quantidade e verifica o limite de estoque
function alterarQuantidade(delta) {
    const quantidadeDisplay = document.getElementById('quantidade-display');
    const quantidadeMaxima = parseInt(document.getElementById('quantidade-maxima').value);
    let quantidadeAtual = parseInt(quantidadeDisplay.textContent);
    
    const novaQuantidade = quantidadeAtual + delta;

    // Não permite quantidade menor que 1
    if (novaQuantidade < 1) {
        return;
    }

    // Se exceder a quantidade máxima, mostra o aviso e não altera a quantidade
    if (novaQuantidade > quantidadeMaxima) {
        mostrarAviso("O limite de estoque disponível para este produto é de " + quantidadeMaxima + " unidades.");
        return;
    }

    quantidadeDisplay.textContent = novaQuantidade;
    atualizarTotal();
}

// A função de envio agora pega a quantidade do novo display
function enviarFormulario(e) {
    e.preventDefault();
    
    const quantidade = parseInt(document.getElementById('quantidade-display').textContent);
    const id_produto = document.querySelector('input[name="id_produto"]').value;
    const is_admin_mode = document.querySelector('input[name="modo_admin"]') !== null; // Verifica se o campo hidden existe
    
    let preco = 0;
    const precoNormalBaseEl = document.getElementById('preco-normal-base');
    const precoPromocionalBaseEl = document.getElementById('preco-promocional-base');

    if (precoNormalBaseEl && precoPromocionalBaseEl && precoNormalBaseEl.value !== precoPromocionalBaseEl.value) {
        // Produto em promoção → usar o promocional
        preco = parseFloat(precoPromocionalBaseEl.value);
    } else {
        // Produto normal → usar o normal (ou o promocional, que é igual)
        preco = parseFloat(precoPromocionalBaseEl.value);
    }


    if (!id_produto || isNaN(quantidade) || quantidade < 1 || isNaN(preco)) {
        mostrarAviso("Dados inválidos. Por favor, tente novamente.");
        return;
    }
    
    const formData = new FormData();
    formData.append('id_produto', id_produto);
    formData.append('quantidade', quantidade);
    formData.append('preco', preco);
    
    // Se for modo admin, garante que o parâmetro seja enviado para o admin_processa_item.php
    if (is_admin_mode) {
         formData.append('modo_admin', 'admin_pedido');
    }
    
    // Obtém o destino do formulário do atributo action
    const actionUrl = e.target.getAttribute('action');
    

    // 💡 IMPORTANTE: A lógica de visitante/logado no JS é mantida, mas adaptada.
    <?php if ($usuario): ?>
        // Usuário logado (Cliente ou Admin)
        fetch(actionUrl, {
            method: 'POST',
            body: formData
        })
        .then(res => {
            // Verifica se a resposta foi bem sucedida (status 200 OK)
            if (res.ok) {
                // 🎯 NOVO: Se for admin e o processo for bem-sucedido, redireciona imediatamente
                if (is_admin_mode) {
                     window.location.href = 'cardapio.php?modo=admin_pedido'; 
                } else {
                    // Cliente logado, mostra o popup
                    mostrarPopup();
                }
            } else {
                return res.text().then(text => {
                    // Se a resposta for um erro 302 (Redirecionamento, ex: pedido não iniciado)
                    // Não é o melhor, mas para fins de desenvolvimento, aceita.
                     if (res.status === 302) {
                        // Se for admin, assume que o pedido foi iniciado e redireciona
                         if (is_admin_mode) {
                             window.location.href = 'cardapio.php?modo=admin_pedido'; 
                             return;
                         }
                     }
                    throw new Error(`Erro do servidor: ${text}`);
                });
            }
        })
        .catch(error => {
            console.error("Erro ao adicionar ao carrinho:", error);
            mostrarAviso("Erro ao adicionar produto.");
        });

    <?php else: ?>
        // Visitante (NUNCA será modo admin)
        let carrinho = [];
        try {
            const raw = document.cookie.split('; ').find(row => row.startsWith('carrinho='));
            if (raw) carrinho = JSON.parse(decodeURIComponent(raw.split('=')[1])) || [];
        } catch (e) {
            console.warn("Cookie inválido.");
        }

        const uuid = generateUUID();
        
        const novoItem = { 
            uuid: uuid,
            id_produto: id_produto,
            quantidade: quantidade,
            preco: preco,
            subtotal: quantidade * preco,
            id_tipo_item_carrinho: 1,
            detalhes_personalizacao: "Sem personalizações adicionais."
        };

        carrinho.push(novoItem);
        document.cookie = `carrinho=${encodeURIComponent(JSON.stringify(carrinho))}; path=/; max-age=604800`;
        
        // Mostra o pop-up após salvar no cookie
        mostrarPopup();
    <?php endif; ?>
}

// Função para gerar um UUID no JavaScript (coloque-a fora da função principal)
function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

// Garante que o total inicial seja calculado quando a página carregar
window.onload = function() {
    atualizarTotal();
    // Os event listeners para os checkboxes são mantidos
    document.querySelectorAll('.ingrediente-checkbox').forEach(checkbox => {
        const card = checkbox.closest('.ingrediente-card');
        if (checkbox.checked) {
            card.classList.add('selecionado');
        }
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                card.classList.add('selecionado');
            } else {
                card.classList.remove('selecionado');
            }
        });
    });
};
    
</script>
</body>
</html>