<?php
// Inicia a sessão para acesso a variáveis de sessão
session_start();

// Inclui o arquivo de conexão com o banco de dados
include "conexao.php";
include "verifica_login_opcional.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


// -----------------------------------------------------
// 🎯 NOVO: 1. Detecta o modo de pedido manual (admin)
// -----------------------------------------------------
$modo_admin = ($_GET['modo'] ?? '') === 'admin_pedido';
$admin_query_param = $modo_admin ? '&modo=admin_pedido' : '';




// Se o usuário aceitou cookies antes, carrega filtros salvos
if (isset($_COOKIE['filtros_produtos'])) {
    $filtros_salvos = json_decode($_COOKIE['filtros_produtos'], true);
} else {
    $filtros_salvos = [];
}

// Recebe filtros via GET ou cookies
$nome = $_GET['nome'] ?? ($filtros_salvos['nome'] ?? '');
$preco_min = $_GET['preco_min'] ?? ($filtros_salvos['preco_min'] ?? '');
$preco_max = $_GET['preco_max'] ?? ($filtros_salvos['preco_max'] ?? '');
$id_categoria = $_GET['categoria'] ?? ($filtros_salvos['categoria'] ?? '');

// Salva filtros em cookies se o utilizador aceitou
if (isset($_COOKIE['aceitou_cookies']) && $_COOKIE['aceitou_cookies'] == "sim" && $_GET) {
    setcookie('filtros_produtos', json_encode([
        'nome' => $nome,
        'preco_min' => $preco_min,
        'preco_max' => $preco_max,
        'categoria' => $id_categoria
    ]), time() + (86400 * 30), "/");
}

// Monta a cláusula WHERE para os filtros de pesquisa
$where = "WHERE 1=1";
$param = [];
$tipos = '';

if (!empty($nome)) {
    $where .= " AND p.nome_produto LIKE ?";
    $param[] = "%$nome%";
    $tipos .= 's';
}
if (!empty($preco_min)) {
    $where .= " AND p.preco >= ?";
    $param[] = $preco_min;
    $tipos .= 'd';
}
if (!empty($preco_max)) {
    $where .= " AND p.preco <= ?";
    $param[] = $preco_max;
    $tipos .= 'd';
}
if (!empty($id_categoria)) {
    // A nova lógica de filtro usa a tabela de associação
    $where .= " AND p.id_produto IN (SELECT id_produto FROM produto_categoria WHERE id_categoria = ?)";
    $param[] = $id_categoria;
    $tipos .= 'i';
}

// Consulta principal para obter os produtos, agora usando a tabela de associação
$sql = "
    SELECT
        p.*, p.preco_promocional,p.preco,
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


// Categorias para o filtro
$categorias = $conexao->query("SELECT * FROM categoria");

// Usuário logado ou não
$usuario = $_SESSION['usuario'] ?? null;
$id_usuario = $usuario['id_usuario'] ?? 0;
$is_admin = ($usuario && ($usuario['idperfil'] ?? 0) == 1); 

if ($modo_admin) {
    // Se estiver no modo admin (URL &modo=admin_pedido), usa o processador do admin
 
      $destino_processamento = 'cardapio.php?modo=admin_pedido'; 
   
} else {
    // Caso contrário, usa o processador normal do carrinho do cliente
   $destino_processamento = 'cardapio.php'; 

}

// --- BLOCO PHP PARA ENCONTRAR O ÚLTIMO PEDIDO ATIVO PARA NOTIFICAÇÃO ---
$last_active_order_id = 0;
if ($id_usuario > 0) {
    // CORREÇÃO: Busca APENAS o último pedido 'entregue' que NÃO foi visto.
    $sql_last_order = "
        SELECT id_pedido 
        FROM pedido 
        WHERE id_usuario = ? 
        AND status_pedido = 'entregue' 
        AND notificacao_vista = 0 
        ORDER BY data_pedido DESC 
        LIMIT 1
    ";
    $stmt_last = $conexao->prepare($sql_last_order);
    $stmt_last->bind_param("i", $id_usuario);
    $stmt_last->execute();
    $res_last = $stmt_last->get_result();
    
    if ($res_last->num_rows > 0) {
        $row_last = $res_last->fetch_assoc();
        $last_active_order_id = $row_last['id_pedido'];
    }
    $stmt_last->close();
}
// --- FIM BLOCO PHP ---
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cardápio</title>
      <?php if ($usuario): ?>
        <script src="logout_auto.js"></script>
    <?php endif; ?>
    
    <link rel="stylesheet" href="css/cliente.css">
          <script src="js/darkmode1.js"></script>
        
            <script src="js/dropdown.js"></script> 
                     <script src="js/sidebar2.js"></script> 

                       <script src="js/carrinho.js"></script> 

<style>
   

</style>
</head>
<body>

<header class="topbar">
  <div class="container">

    <!-- 🔹 BOTÃO MENU MOBILE (visível apenas em mobile - ESQUERDA) -->
    <button class="menu-btn-mobile" id="menuBtnMobile">&#9776;</button>

    <!-- 🟠 LOGO DA EMPRESA (CENTRO no mobile) -->
    <div class="logo">
      <a href="index.php">
        <img src="icones/logo.png" alt="Logo do Restaurante" class="logo-img">
      </a>
    </div>

    <!-- 🔹 LINKS PRINCIPAIS (Desktop) -->

    <div class="links-menu">
      <?php if ($usuario): ?>
             <a href="index.php"><img class="icone2" src="icones/casa1.png" alt="Logout" title="casa"> Início</a>
        <a href="promocoes.php?<?= $admin_query_param ?>"><img class="icone2" src="icones/promo1.png" alt="Logout" title="casa">Promoções</a>

        <a href="historico_compras.php?<?= $admin_query_param ?>">
          <img class="icone2" src="icones/historico1.png" alt="Logout" title="historico">
          Histórico de Compras
          <span id="finalizados-contador" class="contador">0</span>
        </a>

        <?php if (!$is_admin): ?>
            
          <a href="monitorar_pedido.php">
            <img class="icone2" src="icones/progresso1.png" alt="Logout" title="monitorar"> 
            Acompanhar Pedidos
            <span id="pedidos-ativos-contador" class="contador">0</span>
          </a>
        <?php endif; ?>

        <?php if ($modo_admin): ?>
          <a href="admin_finalizar_pedido.php" class="btn-pedido-manual">Ver Pedido Manual</a>
        <?php endif; ?>
 
      <?php else: ?>
        <a href="index.php" class="login-link"><img class="icone2" src="icones/casa1.png" alt="Logout" title="casa">Voltar ao Início</a>
        <!-- <a href="login.php" class="login-link">Fazer Login</a> -->
        <a href="monitorar_pedido.php">  <img class="icone2" src="icones/progresso1.png" alt="Logout" title="monitorar"> Acompanhar Pedidos</a>
        <a href="historico_compras.php"><img class="icone2" src="icones/historico1.png" alt="Logout" title="historico">Histórico</a>
        <a href="promocoes.php"><img class="icone2" src="icones/promo1.png" alt="Logout" title="casa">Promoções</a>
      <?php endif; ?>
    </div>

    <!-- 🔹 AÇÕES USUÁRIO (sempre visível) -->
    <div class="acoes-usuario">
      <!-- Dark Mode Toggle -->
      <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Alternar modo escuro" title="Alternar modo escuro">
      
      <!-- Carrinho (sempre visível) -->

         <?php if (!$modo_admin): ?>
      <a href="ver_carrinho.php" class="carrinho-link">
        <img id="iconeCarrinho" src="icones/carrinho1.png" alt="Carrinho" title="Ver carrinho" class="icon-carrinho">
        <span id="carrinho-contador" class="contador">0</span>
      </a>
           <?php endif; ?>

      <!-- Usuário ou Login -->
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
        <!-- Desktop: Mostra perfil completo -->
        <div class="usuario-info usuario-desktop" id="usuarioDropdown">
          <div class="usuario-dropdown">
            <div class="usuario-iniciais" style="background-color: <?= $corAvatar ?>;">
              <?= $iniciais ?>
            </div>
            <div class="usuario-nome"><?= $nomeCompleto ?></div>

            <div class="menu-perfil" id="menuPerfil">
              <a href='editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>'>Editar Dados Pessoais</a>
              <a href="alterar_senha2.php">Alterar Senha</a>
              <a href="logout.php">
                <img class="iconelogout" src="icones/logout1.png" alt="Logout" title="Sair"> Sair
              </a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="login-link-mobile">Entrar</a>
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
      <li><a href="index.php">
        <a href="index.php"><img class="" src="icones/casa1.png" alt="Logout" title="casa"> Voltar ao Início</a></li>
      <li><a href="promocoes.php?<?= $admin_query_param ?>"><img class="" src="icones/promo1.png" alt="Logout" title="casa"> Promoções</a></li>

      <li>   <a href="historico_compras.php?<?= $admin_query_param ?>">
          <img class="" src="icones/historico1.png" alt="Logout" title="historico"> Histórico de Compras
          <span id="finalizados-contador-mobile" class="contador">0</span>
        </a>
      </li>

      
<?php if (!$is_admin): ?>
        <li>
          <a href="monitorar_pedido.php">
           <img class="" src="icones/progresso1.png" alt="Logout" title="monitorar">      Acompanhar Pedidos
             <span id="pedidos-ativos-contador-mobile" class="contador">0</span>
          </a>
        </li>
      <?php endif; ?>

      <?php if ($modo_admin): ?>
        <li><a href="admin_finalizar_pedido.php" class="btn-pedido-manual"> Ver Pedido Manual</a></li>
      <?php endif; ?>

    <?php else: ?>
      <li> <a href="index.php"><img class="" src="icones/casa1.png" alt="Logout" title="casa"> Voltar ao Início</a></li>
  
      <li><a href="monitorar_pedido.php">        <img class="" src="icones/progresso1.png" alt="Logout" title="monitorar">    Acompanhar Pedidos</a></li>
      <li><a href="historico_compras.php">  <img class="" src="icones/historico1.png" alt="Logout" title="historico"> Histórico</a></li>
      <li><a href="promocoes.php"> <img class="" src="icones/promo1.png" alt="Logout" title="casa"> Promoções</a></li>
    <?php endif; ?>
  </ul>


  <!-- 🔹 USUÁRIO NO FUNDO DO MENU (igual ao ChatGPT) -->
  <?php if ($usuario): ?>
    
    <!-- DROPDOWN PARA CIMA (primeiro) -->
   <!-- 🔹 USUÁRIO NO FUNDO DO MENU -->
<?php if ($usuario): ?>
  <div class="sidebar-user-section">
    
    <!-- DROPDOWN PARA CIMA (primeiro) -->
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

    <!-- CARTÃO DO USUÁRIO (clicável) -->
    <div id="sidebarUserProfile" class="sidebar-user-profile">
      <div class="sidebar-user-avatar" style="background-color: <?= $corAvatar ?>;">
        <?= $iniciais ?>
      </div>

      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= $nome ?></div>
        <div class="sidebar-user-email"><?= $usuario["email"] ?></div>
      </div>

<span id="sidebarArrow" class="dropdown-arrow">▲</span>

    </div>
  </div>
<?php endif; ?>

  <?php endif; ?>

</nav>

<!-- Overlay -->
<div id="menuOverlay" class="menu-overlay hidden"></div>


  <div id="popup-pedido-finalizado" class="modal" style="display:none;">
    <div class="modal-content">
        <h4>Pedido Finalizado!</h4>
        <p>Seu pedido foi concluído. Clique no link Histórico para mais detalhes!</p>
        <button onclick="fecharPopupFinalizado()">Fechar</button>
    </div>
</div>

<div class="conteudo">
    <h2 style="padding:0 20px;">Cardápio <?= $modo_admin ? ' (Modo Admin - Pedido Manual)' : '' ?></h2>

  <p class="btn-filtro">
      <img id="imgFiltro" src="icones/filtro1.png" alt="filtro1" title="filtro1" style="cursor:pointer;"> Filtros
</p>


<form method="get" action="cardapio.php" style="padding: 20px;" class="filtros" id="formFiltros">
    <?php if ($modo_admin): ?>
        <input type="hidden" name="modo" value="admin_pedido">
    <?php endif; ?>

    <input type="text" name="nome" placeholder="Nome do produto" value="<?= htmlspecialchars($nome) ?>">
    <input type="number" step="0.01" name="preco_min" placeholder="Preço mín." value="<?= $preco_min ?>">
    <input type="number" step="0.01" name="preco_max" placeholder="Preço máx." value="<?= $preco_max ?>">

    <select name="categoria">
        <option value="">Todas as categorias</option>
        <?php $categorias->data_seek(0); ?>
        <?php while($c = $categorias->fetch_assoc()): ?>
            <option value="<?= $c['id_categoria'] ?>" <?= $c['id_categoria'] == $id_categoria ? 'selected' : '' ?>>
                <?= $c['nome_categoria'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <input class="busca" type="submit" value="Filtrar">

    <?php if ($is_admin): ?>
        <a href="limpar_filtros_admin.php?origem=cardapio" class="limpar-filtros">Limpar Filtros</a>
    <?php else: ?>
        <a href="limpar_filtros.php?origem=cardapio" class="limpar-filtros">Limpar Filtros</a>
    <?php endif; ?>
</form>

    <?php if ($result->num_rows === 0): ?>
        <p class="message-empty">No momento não temos produtos disponíveis em nosso cardápio. 
        Volte em breve, estamos preparando novidades deliciosas para si!</p>
    <?php else: ?>

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
                                <!-- <b>Preço por cada <?= htmlspecialchars($p['nome_produto']) ?>:</b> -->
                                <?php if (!empty($p['preco_promocional']) && $p['preco_promocional'] < $p['preco']): ?>
                                Antes:   <span class="preco-original"><?= number_format($p['preco'], 2, ',', '.') ?> MZN</span><br>
                                Agora: <span class="preco"><?= number_format($p['preco_promocional'], 2, ',', '.') ?> MZN</span>
                                <?php else: ?>
                                    <span class="preco"><?= number_format($p['preco'], 2, ',', '.') ?> MZN</span>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p>
                                <!-- <b>Preço por cada <?= htmlspecialchars($p['nome_produto']) ?>:</b> -->
                                <span class="preco"><?= number_format($p['preco'], 2, ',', '.') ?> MZN</span>
                            </p>
                        <?php endif; ?>

                        <p><strong>Categorias:</strong> <?= htmlspecialchars($p['categorias_nomes']) ?></p>
                        
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



<div class="popup-cookies" id="popup-cookies">
    <p>Este site usa cookies para salvar as suas preferências de filtro. Aceita?</p>
    <button onclick="aceitarCookies()">Aceitar</button>
    <button onclick="rejeitarCookies()">Rejeitar</button>
</div>

<script>



document.getElementById("imgFiltro").addEventListener("click", function() {
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

function toggleMenu(){
    document.querySelector(".links-menu").classList.toggle("show");
}

// Variáveis PHP acessíveis ao JavaScript
const ID_USUARIO = <?php echo (int)$id_usuario; ?>;
const LAST_ACTIVE_ORDER_ID = <?php echo (int)$last_active_order_id; ?>;


// ----------------------------------------------------------------------
// FUNÇÕES DE AJAX POLLING PARA VERIFICAÇÃO DE STATUS DE PEDIDO
// ----------------------------------------------------------------------

const INTERVALO_POLLING_MS = 5000; // 5 segundos
let pollingInterval = null;

// NOVO: Marca se o popup já foi mostrado nesta sessão para o pedido atual.
let popupMostrado = false; 
// NOVO: Chave de localStorage para guardar o estado do popup visto
const POPUP_VISTO_KEY = `pedido_${LAST_ACTIVE_ORDER_ID}_popup_visto`;


function fecharPopupFinalizado() {
    document.getElementById('popup-pedido-finalizado').style.display = 'none';
    
    // NOVO: Marca o popup como visto no cliente ao ser fechado
    if (LAST_ACTIVE_ORDER_ID > 0) {
        localStorage.setItem(POPUP_VISTO_KEY, 'true');
    }
    popupMostrado = true;
}

// ... (Função atualizarContadorFinalizados() - Sem Alterações) ...
function atualizarContadorFinalizados() {
    // ID_USUARIO deve ser definido globalmente no PHP/JS.
    if (ID_USUARIO === 0) return;

    fetch('pedidos_finalizados_contador.php')
        .then(response => response.json())
        .then(data => {
            console.log("Contador do Servidor:", data.total_finalizados_nao_vistos); 
            const count = data.total_finalizados_nao_vistos;

            // --- NOVO: Seleciona e atualiza AMBOS os contadores ---
            
            // 1. Contador Desktop (ID: finalizados-contador)
            const desktopCounter = document.getElementById('finalizados-contador');
            
            // 2. Contador Mobile (ID: finalizados-contador-mobile)
            const mobileCounter = document.getElementById('finalizados-contador-mobile');

            [desktopCounter, mobileCounter].forEach(counterElement => {
                if (counterElement) {
                    counterElement.textContent = count;
                    // Mostra/esconde em ambos os elementos
                    counterElement.style.display = count > 0 ? 'inline-block' : 'none';
                }
            });
            // --- FIM DA UNIFICAÇÃO ---

        })
        .catch(error => {
            console.error("Erro ao carregar contador de finalizados:", error);
        });
}

// Chame a função para iniciar a contagem (e talvez em um setInterval)
// atualizarContadorFinalizados();


function verificarStatusDoPedido(id_pedido) {
    if (!id_pedido || id_pedido === 0 || popupMostrado) {
        // NÃO fazemos polling se já mostramos o popup para este pedido
        return;
    }

    fetch(`check_order_status.php?id=${id_pedido}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na rede ou pedido não encontrado.');
            }
            return response.json();
        })
        .then(data => {
            console.log(`Polling status para Pedido #${id_pedido}:`, data.status_detalhado);
            
            if (data.success) {
                if (data.notificacao_nova) {
                    
                    const vistoNoStorage = localStorage.getItem(POPUP_VISTO_KEY) === 'true';

                    if (!popupMostrado && !vistoNoStorage) {
                        // 1. Mostrar o popup
                        document.getElementById('popup-pedido-finalizado').style.display = 'block';
                        
                        // 2. Marcar como mostrado na variável local para o resto desta sessão/pollings
                        popupMostrado = true; 
                    }
                    
                    if (data.status_detalhado === 'entregue') {
                        atualizarContadorFinalizados(); 
                    }
                } else {
                    // Se notificacao_nova for false, significa que o usuário já visitou o Histórico
                    // ou o pedido mudou de estado. Nesse caso, paramos o polling e removemos o marcador.
                    clearInterval(pollingInterval);
                    pollingInterval = null;
                    console.log("Polling parado: Pedido não requer mais notificação.");
                    localStorage.removeItem(POPUP_VISTO_KEY); // Limpa o marcador local
                }
            }
        })
        .catch(error => {
            console.error("Erro no Polling AJAX:", error);
        });
}

function iniciarPollingPedidos() {
    // 1. Verifica se há um usuário logado
    if (ID_USUARIO === 0) {
        console.log("Polling não iniciado: Nenhum usuário logado.");
        return;
    }
    
    // 2. Verifica se há um pedido ativo/pendente de notificação para monitorar
    if (LAST_ACTIVE_ORDER_ID === 0) {
        console.log("Polling não iniciado: Nenhum pedido entregue e não visto encontrado para iniciar polling.");
        return;
    }

    // 3. Verifica se o popup para ESTE pedido já foi fechado pelo utilizador antes
    const vistoNoStorage = localStorage.getItem(POPUP_VISTO_KEY) === 'true';
    if (vistoNoStorage) {
        popupMostrado = true; // Define a flag para impedir o popup
        console.log("Popup já foi fechado. Não será exibido, mas contador persistirá.");
    }
    
    // 4. Inicia o Polling
    console.log(`Iniciando Polling para Pedido ID: ${LAST_ACTIVE_ORDER_ID}`);
    
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    
    pollingInterval = setInterval(() => verificarStatusDoPedido(LAST_ACTIVE_ORDER_ID), INTERVALO_POLLING_MS);

    // Chamada inicial imediata
    verificarStatusDoPedido(LAST_ACTIVE_ORDER_ID); 
}

// Chamada inicial para o contador de finalizados e polling
window.onload = function() {
    if (!document.cookie.includes('aceitou_cookies')) {
        document.getElementById('popup-cookies').style.display = 'block';
    }
    
    if (ID_USUARIO !== 0) {
        atualizarContadorFinalizados(); 
    }
    
    iniciarPollingPedidos();
}

// ----------------------------------------------------------------------
// FIM FUNÇÕES DE AJAX POLLING
// ----------------------------------------------------------------------
</script>
</body>
</html>