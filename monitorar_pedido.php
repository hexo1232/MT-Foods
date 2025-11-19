<?php

require_once "conexao.php";
require_once "require_login.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}


$id_usuario = $_SESSION['usuario']['id_usuario'] ?? null;

if (isset($_GET['id_pedido'])) {
    // --- LÓGICA PARA EXIBIR UM ÚNICO PEDIDO ---
    $id_pedido = intval($_GET['id_pedido']);

    // Busca dados do pedido usando prepared statement
    $sql_pedido = "
        SELECT p.id_pedido, p.data_pedido, p.total, p.status_pedido, t.nome_tipo_entrega
        FROM pedido p
        JOIN tipo_entrega t ON p.idtipo_entrega = t.idtipo_entrega
        WHERE p.id_pedido = ? and p.id_usuario = ? and status_pedido in ('pendente', 'Em preparação', 'Saiu Para Entrega', 'Pronto para Retirada')
    ";
    $stmt_pedido = $conexao->prepare($sql_pedido);
    $stmt_pedido->bind_param("ii", $id_pedido, $id_usuario);
    $stmt_pedido->execute();
    $result_pedido = $stmt_pedido->get_result();
    $pedido = $result_pedido->fetch_assoc();

    if (!$pedido) {
        die("Pedido não encontrado ou não pertence ao usuário.");
    }

    // Busca os itens do pedido
    $sql_itens = "
        SELECT ip.id_item_pedido, ip.quantidade, ip.subtotal, p.nome_produto,
               (SELECT caminho_imagem FROM produto_imagem WHERE id_produto = p.id_produto AND imagem_principal = 1 LIMIT 1) AS imagem_principal
        FROM item_pedido ip
        JOIN produto p ON ip.id_produto = p.id_produto
        WHERE ip.id_pedido = ?
    ";
    $stmt_itens = $conexao->prepare($sql_itens);
    $stmt_itens->bind_param("i", $id_pedido);
    $stmt_itens->execute();
    $result_itens = $stmt_itens->get_result();
    $itens_pedido = [];

    // Itera sobre cada item para buscar personalizações
    while ($item = $result_itens->fetch_assoc()) {
        $id_item_pedido = $item['id_item_pedido'];

        $ingredientes_incrementados = [];
        $ingredientes_reduzidos = [];
        
        // Busca personalizações para o item
        $sql_pers = "
            SELECT ipp.ingrediente_nome, ipp.tipo, ii.caminho_imagem
            FROM item_pedido_personalizacao ipp
            JOIN ingrediente i ON ipp.ingrediente_nome = i.nome_ingrediente
            LEFT JOIN ingrediente_imagem ii ON i.id_ingrediente = ii.id_ingrediente AND ii.imagem_principal = 1
            WHERE ipp.id_item_pedido = ?
        ";
        $stmt_pers = $conexao->prepare($sql_pers);
        $stmt_pers->bind_param("i", $id_item_pedido);
        $stmt_pers->execute();
        $result_pers = $stmt_pers->get_result();
        
        while ($pers = $result_pers->fetch_assoc()) {
            $ing_data = [
                'nome' => $pers['ingrediente_nome'],
                'imagem' => $pers['caminho_imagem']
            ];
            if ($pers['tipo'] === 'extra') {
                $ingredientes_incrementados[] = $ing_data;
            } elseif ($pers['tipo'] === 'removido') {
                $ingredientes_reduzidos[] = $ing_data;
            }
        }
        
        $item['ingredientes_incrementados'] = $ingredientes_incrementados;
        $item['ingredientes_reduzidos'] = $ingredientes_reduzidos;
        $itens_pedido[] = $item;
    }
    
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Pedido</title>
    <script src="logout_auto.js"></script>

                 <script src="logout_auto.js"></script>
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
     
     <?php if ($id_usuario): ?>
        <a href="monitorar_pedido.php">Continuar a ver Pedidos</a>
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
      <?php if ($id_usuario): ?>
        <a href="monitorar_pedido.php">Continuar a ver Pedidos</a>
      <?php endif; ?></ul>

  <?php if ($usuario): ?>
    <div class="sidebar-user-section">


      <div id="sidebarProfileDropdown" class="sidebar-profile-dropdown">
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
1

          
</nav>


          

    <div class="conteudo">


  <h2>Acompanhe o seu Pedidos número </h2>
        <h3>Itens do Pedido</h3>
        <?php foreach ($itens_pedido as $item): ?>

            <div class="item-card">
                <img src="<?= htmlspecialchars($item['imagem_principal'] ?? 'imagens/sem_imagem.jpg') ?>" alt="Imagem do produto">
                <div class="item-details">
                           <h3><?= htmlspecialchars($item['nome_produto']) ?></h3>
        <p><b>Status atual:</b> <span id="statusAtual"><?= htmlspecialchars(ucfirst($pedido['status_pedido'])) ?></span></p>
        <p><b>Entrega:</b> <?= htmlspecialchars($pedido['nome_tipo_entrega']) ?></p>
      
                    
                    <p>Quantidade: <?= htmlspecialchars($item['quantidade']) ?></p>
                    <p>Preço Total da refeição: <?= number_format($item['subtotal'], 2, ',', '.') ?> MT</p>

                    <!-- Ingredientes Extra -->
                    <?php if (!empty($item['ingredientes_incrementados'])): ?>
                        <div class="personalizacoes">
                            <h4>Ingredientes Extra</h4>
                            <div class="ingredientes-container">
                                <?php
                                $counted_ingredientes = [];
                                foreach ($item['ingredientes_incrementados'] as $ing) {
                                    if (!isset($counted_ingredientes[$ing['nome']])) {
                                        $counted_ingredientes[$ing['nome']] = [
                                            'count' => 0,
                                            'imagem' => $ing['imagem']
                                        ];
                                    }
                                    $counted_ingredientes[$ing['nome']]['count']++;
                                }
                                ?>
                                <?php foreach ($counted_ingredientes as $ing_nome => $ing_data): ?>
                                    <div class="ingrediente-card extra">
                                        <img src="<?= htmlspecialchars($ing_data['imagem'] ?? 'imagens/sem_foto_ingrediente.png') ?>" alt="Foto do ingrediente">
                                        <span><?= htmlspecialchars($ing_nome) ?> (x<?= htmlspecialchars($ing_data['count']) ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Ingredientes Removidos -->
                    <?php if (!empty($item['ingredientes_reduzidos'])): ?>
                        <div class="personalizacoes">
                            <h4>Ingredientes Removidos</h4>
                            <div class="ingredientes-container">
                                <?php
                                $counted_ingredientes = [];
                                foreach ($item['ingredientes_reduzidos'] as $ing) {
                                    if (!isset($counted_ingredientes[$ing['nome']])) {
                                        $counted_ingredientes[$ing['nome']] = [
                                            'count' => 0,
                                            'imagem' => $ing['imagem']
                                        ];
                                    }
                                    $counted_ingredientes[$ing['nome']]['count']++;
                                }
                                ?>
                                <?php foreach ($counted_ingredientes as $ing_nome => $ing_data): ?>
                                    <div class="ingrediente-card removido">
                                        <img src="<?= htmlspecialchars($ing_data['imagem'] ?? 'imagens/sem_foto_ingrediente.png') ?>" alt="Foto do ingrediente">
                                        <span><?= htmlspecialchars($ing_nome) ?> (x<?= htmlspecialchars($ing_data['count']) ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
          <p><b>Total Pago:</b> <?= number_format($pedido['total'], 2, ',', '.') ?> MT</p>
<!--         <h3>Histórico do Pedido</h3>
        <ol id="listaRastreamento"></ol>
    </div> -->

    <script>
        const pedidoId = <?= $id_pedido ?>;
        atualizarRastreamento(pedidoId);
        setInterval(() => atualizarRastreamento(pedidoId), 10000);
        
        function atualizarRastreamento(pedidoId) {
            fetch("rastreamento_api.php?id_pedido=" + pedidoId)
                .then(response => response.json())
                .then(data => {
                    let lista = document.getElementById("listaRastreamento");
                    lista.innerHTML = "";
                    data.forEach(item => {
                        let li = document.createElement("li");
                        li.textContent = `[${item.data}] ${item.status}`;
                        lista.appendChild(li);
                    });
                    if (data.length > 0) {
                        document.getElementById("statusAtual").textContent = data[data.length - 1].status;
                    }
                })
                .catch(err => console.error("Erro ao carregar rastreamento:", err));
        }
    </script>
</body>
</html>
<?php
} else {
    // --- LÓGICA PARA EXIBIR TODOS OS PEDIDOS PENDENTES ---
    if ($id_usuario) { // Adicionada esta verificação
        $sql_pedidos_pendentes = "
            SELECT p.id_pedido, p.data_pedido, p.total, p.status_pedido, t.nome_tipo_entrega
            FROM pedido p
            JOIN tipo_entrega t ON p.idtipo_entrega = t.idtipo_entrega
            WHERE p.id_usuario = $id_usuario AND p.status_pedido IN ('pendente', 'Em preparação', 'Saiu Para Entrega', 'Pronto para Retirada')
                        ORDER BY p.data_pedido ASC
        ";
        $result_pendentes = mysqli_query($conexao, $sql_pedidos_pendentes);
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <title>Meus Pedidos Pendentes</title>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <script src="logout_auto.js"></script>
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
      <a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar">Voltar</a>
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
    <li><a href="cardapio.php"><img class="icone2" src="icones/voltar1.png" alt="Logout" title="voltar"> Voltar</a></li>
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
1

          
</nav>




            <div class="conteudo">
                <h2>Meus Pedidos Pendentes</h2>
                 
                    
                <?php if (mysqli_num_rows($result_pendentes) > 0): ?>
            
                    <ul style="list-style-type: none;">
                        <?php while($pedido_pendente = mysqli_fetch_assoc($result_pendentes)): ?>
                                      <div class="item-card">
                            <li class="pedido-item">
                                <div>
                                    <p><b>Pedido #<?= $pedido_pendente['id_pedido'] ?></b></p>
                                    <p>Data: <?= (new DateTime($pedido_pendente['data_pedido']))->format('d/m/Y H:i') ?></p>
                                    <p>Total: <?= number_format($pedido_pendente['total'], 2, ',', '.') ?> MT</p>
                                </div>
                                <a href="monitorar_pedido.php?id_pedido=<?= $pedido_pendente['id_pedido'] ?>" class="botao-detalhes">Detalhes </a>
                            </li>
                            </div>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>Você não tem pedidos pendentes no momento.</p>
                <?php endif; ?>
                          
            </div>
        </body>
        </html>
        <?php
    } else {
        echo "<p>Você precisa estar logado para ver seus pedidos pendentes.</p>";
    }
}
?>
