<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Lista de Refeições</title>
    
        <script src="logout_auto.js"></script>

    <link rel="stylesheet" href="css/admin.css">
          <script src="js/darkmode2.js"></script>
            <script src="js/sidebar.js"></script>
            <script src="js/dropdown2.js"></script>
</head>
<body>

<button class="menu-btn">☰</button>

<!-- Overlay -->
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
    <br><br>

    <a href="dashboard.php">Voltar ao Menu Principal</a>
    <a href="cadastroproduto.php">Cadastrar novas Refeições</a>
                  <a href="ver_categorias.php">Ver Categorias das Refeições</a>

  
                  <!-- ===== PERFIL NO FUNDO DA SIDEBAR ===== -->
<div class="sidebar-user-wrapper">

    <div class="sidebar-user" id="usuarioDropdown">

        <div class="usuario-avatar" style="background-color: <?= $corAvatar ?>;">
            <?= $iniciais ?>
        </div>

        <div class="usuario-dados">
            <div class="usuario-nome"><?= $nome ?></div>
            <div class="usuario-apelido"><?= $apelido ?></div>
        </div>

        <!-- DROPDOWN PARA CIMA -->
        <div class="usuario-menu" id="menuPerfil">
            <a href='editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>'>Editar Dados Pessoais</a>
            <a href="alterar_senha2.php">Alterar Senha</a>
            <a href="logout.php">Sair</a>
        </div>

    </div>

    <!-- BOTÃO DE MODO ESCURO -->
    <img class="dark-toggle" id="darkToggle"
         src="icones/lua.png"
         alt="Modo Escuro"
         title="Alternar modo escuro">
</div>

</sidebar>

<div class="conteudo">
  
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'excluido'): ?>
        <div class="mensagem-sucesso">
            Produto excluído com sucesso!
        </div>
    <?php endif; ?>

    <div class="produtos">
          
        <?php
        $sql = "SELECT 
                    p.id_produto,
                    p.nome_produto,
                    p.preco,
                    p.descricao,
                    pi.caminho_imagem,
                    GROUP_CONCAT(c.nome_categoria SEPARATOR ', ') AS categorias
                FROM produto p
                LEFT JOIN produto_imagem pi ON p.id_produto = pi.id_produto AND pi.imagem_principal = 1
                LEFT JOIN produto_categoria pc ON p.id_produto = pc.id_produto
                LEFT JOIN categoria c ON pc.id_categoria = c.id_categoria
                GROUP BY p.id_produto
                ORDER BY p.id_produto DESC";
        
        $resultado = $conexao->query($sql);

        if ($resultado->num_rows > 0) {
            while ($produto = $resultado->fetch_assoc()) {
                $imagem = $produto['caminho_imagem'] ?: 'uploads/sem_imagem.png';
                $categorias = $produto['categorias'] ?: 'Sem categoria';
                echo "<div class='card'>
                        <img src='{$imagem}' alt='Imagem do Produto'>
                        <div class='info'>
                            <h3>" . htmlspecialchars($produto['nome_produto']) . "</h3>
                            <p><strong>Preço:</strong> MT " . number_format($produto['preco'], 2, ',', '.') . "</p>
                            <p><strong>Categorias:</strong> " . htmlspecialchars($categorias) . "</p>
                            <p><strong>Descrição:</strong> " . htmlspecialchars($produto['descricao']) . "</p>
                        </div>
                        <div class='acoes'>
                            <a href='editarproduto.php?id={$produto['id_produto']}' class='editar'>Editar</a>
                            <a href='excluirproduto.php?id={$produto['id_produto']}' class='excluir' onclick=\"return confirm('Deseja realmente excluir este produto?')\">Excluir</a>
                        </div>
                    </div>";
            }
        } else {
            echo "<p style='padding-left: 20px;'>Nenhum produto encontrado.</p>";
        }
        ?>
    </div>
</div>

</body>
</html>
