<?php
// Inicia a sessão para acesso a variáveis de sessão
session_start();

// Inclui o arquivo de conexão com o banco de dados
include "conexao.php";
include "verifica_login_opcional.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$modo_admin = ($_GET['modo'] ?? '') === 'admin_pedido';
$admin_query_param = $modo_admin ? '?modo=admin_pedido' : '';

// Se o usuário aceitou cookies antes, carrega filtros salvos
if (isset($_COOKIE['filtros_promocoes'])) {
    $filtros_salvos = json_decode($_COOKIE['filtros_promocoes'], true);
} else {
    $filtros_salvos = [];
}

// Recebe filtros via GET ou cookies
$nome = $_GET['nome'] ?? ($filtros_salvos['nome'] ?? '');
$preco_min = $_GET['preco_min'] ?? ($filtros_salvos['preco_min'] ?? '');
$preco_max = $_GET['preco_max'] ?? ($filtros_salvos['preco_max'] ?? '');

// Salva filtros em cookies se o utilizador aceitou
if (isset($_COOKIE['aceitou_cookies']) && $_COOKIE['aceitou_cookies'] == "sim" && $_GET) {
    setcookie('filtros_promocoes', json_encode([
        'nome' => $nome,
        'preco_min' => $preco_min,
        'preco_max' => $preco_max
    ]), time() + (86400 * 30), "/");
}

// Monta a cláusula WHERE para os filtros de pesquisa
$where = "WHERE c.nome_categoria = 'Promoções da Semana'";
$param = [];
$tipos = '';

if (!empty($nome)) {
    $where .= " AND p.nome_produto LIKE ?";
    $param[] = "%$nome%";
    $tipos .= 's';
}
if (!empty($preco_min)) {
    $where .= " AND p.preco_promocional >= ?";
    $param[] = $preco_min;
    $tipos .= 'd';
}
if (!empty($preco_max)) {
    $where .= " AND p.preco_promocional <= ?";
    $param[] = $preco_max;
    $tipos .= 'd';
}

$sql = "
    SELECT
        p.*, p.preco, p.preco_promocional,
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
    $where
    GROUP BY
        p.id_produto
";

// Prepara e executa a consulta
$stmt = $conexao->prepare($sql);
if (!empty($param)) {
    $stmt->bind_param($tipos, ...$param);
}
$stmt->execute();
$result = $stmt->get_result();

// Usuário logado ou não
$usuario = $_SESSION['usuario'] ?? null;
$id_usuario = $usuario['id_usuario'] ?? 0;
$is_admin = ($usuario && ($usuario['idperfil'] ?? 0) == 1); 

if ($modo_admin) {
    // Se estiver no modo admin (URL &modo=admin_pedido), usa o processador do admin
 
      $destino_processamento = 'promocoes.php?modo=admin_pedido'; 
   
} else {
    // Caso contrário, usa o processador normal do carrinho do cliente
   $destino_processamento = 'promocoes.php'; 

}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promoções da Semana</title>
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
        <img src="icones/logo.png" alt="Logo" class="logo-img">
      </a>
    </div>

    <!-- 🔹 LINKS DESKTOP -->
    <div class="links-menu">
      <?php if ($usuario): ?>

        <?php if ($is_admin): ?>
          <a href="cardapio.php?modo=admin_pedido"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar">Voltar ao Cardápio</a>
        <?php else: ?>
          <a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar">Voltar</a>
        <?php endif; ?>

      <?php else: ?>
        <a href="login.php">Fazer Login</a>
        <a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar">Voltar</a>
      <?php endif; ?>
    </div>

    <!-- 🔹 AÇÕES DO USUÁRIO -->
    <div class="acoes-usuario">

      <!-- Dark Mode -->
      <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo escuro">

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
        <li><a href="cardapio.php?modo=admin_pedido"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar"> Voltar</a></li>
      <?php else: ?>
        <li><a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar"> Voltar</a></li>
      <?php endif; ?>

    <?php else: ?>
      <li><a href="login.php">Fazer Login</a></li>
      <li><a href="cardapio.php"> <img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar"> Voltar</a></li>
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

      <h2 style="padding:0 20px;">Promoções da Semana <?= $modo_admin ? ' (Modo Admin)' : '' ?></h2>

  <!-- Botão de Filtro -->
<p class="btn-filtro">
    Filtros
    <img id="imgFiltro" src="icones/filtro1.png" alt="filtro1" title="filtro1" style="cursor:pointer;">
</p>

<!-- Formulário de Filtros -->
<form method="get" action="promocoes.php" style="padding: 20px;" class="filtros" id="formFiltros">
    <?php if ($modo_admin): ?>
        <input type="hidden" name="modo" value="admin_pedido">
    <?php endif; ?>

    <input type="text" name="nome" placeholder="Nome do produto" value="<?= htmlspecialchars($nome) ?>">
    <input type="number" step="0.01" name="preco_min" placeholder="Preço mín." value="<?= $preco_min ?>">
    <input type="number" step="0.01" name="preco_max" placeholder="Preço máx." value="<?= $preco_max ?>">
    <input class="busca" type="submit" value="Filtrar">

    <?php if ($is_admin): ?>
        <a href="limpar_filtros_admin.php?origem=promocoes" class="limpar-filtros">Limpar Filtros</a>
    <?php else: ?>
        <a href="limpar_filtros.php?origem=promocoes" class="limpar-filtros">Limpar Filtros</a>
    <?php endif; ?>
</form>


    <?php if ($result->num_rows === 0): ?>
        <p class="message-empty">No momento não temos produtos na promoção disponíveis no cardápio. 
        Volte em breve, estamos preparando novidades deliciosas para si!</p>
    <?php else: ?>
    <!-- Exibição dos Produtos -->
     <section class="refeicoes">
     <div class="container-produtos">
            <?php while ($p = $result->fetch_assoc()): ?>
               <?php
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
$stmt_check = $conexao->prepare($sql);
$stmt_check->bind_param("i", $p['id_produto']);
$stmt_check->execute();
$res_check = $stmt_check->get_result();

$disponivel = true; // começa assumindo que o produto está disponível

while ($row = $res_check->fetch_assoc()) {
    if ($row['quantidade_disponivel'] < $row['quantidade_necessaria']) {
        $disponivel = false; // não tem estoque suficiente para pelo menos 1 prato
        break;
    }
}
?>
                <div class="card-produto">
                    <?php if ($p['imagem_principal']): ?>
                        <img src="<?= htmlspecialchars($p['imagem_principal']) ?>" alt="Imagem do produto">
                    <?php else: ?>
                        <img src="imagens/sem_imagem.jpg" alt="Sem imagem">
                    <?php endif; ?>
                    <div class="info">
                        <div class="titulo"><?= htmlspecialchars($p['nome_produto']) ?></div>
                        <?php if (stripos($p['categorias_nomes'], 'Promoções da semana') !== false): ?>
                            <p>
                               
                                <?php if (!empty($p['preco_promocional']) && $p['preco_promocional'] < $p['preco']): ?>
                                Antes:   <span class="preco-original"><?= number_format($p['preco'], 2, ',', '.') ?> MZN</span><br>
                                Agora: <span class="preco"><?= number_format($p['preco_promocional'], 2, ',', '.') ?> MZN</span>
                                <?php else: ?>
                                    <span class="preco"><?= number_format($p['preco'], 2, ',', '.') ?> MZN</span>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p>
                               
                                <span class="preco"><?= number_format($p['preco'], 2, ',', '.') ?> MZN</span>
                            </p>
                        <?php endif; ?>

                        <!-- <p><strong>Categorias:</strong> <?= htmlspecialchars($p['categorias_nomes']) ?></p>
                         -->
                        <!-- Exibe a disponibilidade do produto -->
                      <p>

    <?php if ($disponivel): ?>
        <span style="color:green;">Disponível</span>
    <?php else: ?>
        <span style="color:red;">Indisponível</span>
    <?php endif; ?>
</p>

                         <a href="detalhesproduto.php?id_produto=<?= $p['id_produto'] ?><?= $admin_query_param ?>" class="botao-detalhes">Ver detalhes</a>
                        </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>
    </section>

<script>

  document.querySelector(".btn-filtro").addEventListener("click", function() {
    const form = document.getElementById("formFiltros");
    form.style.display = (form.style.display === "none" || form.style.display === "") 
        ? "block" 
        : "none";
});

function aceitarCookies() {
    document.cookie = "aceitou_cookies=sim; path=/; max-age=" + (60*60*24*30);
    document.getElementById('popup-cookies').style.display = 'none';
}
function rejeitarCookies() {
    document.cookie = "aceitou_cookies=nao; path=/; max-age=" + (60*60*24*30);
    document.getElementById('popup-cookies').style.display = 'none';
}
window.onload = function() {
    if (!document.cookie.includes('aceitou_cookies')) {
        document.getElementById('popup-cookies').style.display = 'block';
    }
}
</script>
</body>
</html>
