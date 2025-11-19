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

$usuario_logado = $_SESSION['usuario'];

// Consulta para obter todas as categorias
$sql_categorias = "SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria ASC";
$resultado_categorias = $conexao->query($sql_categorias);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Gerenciar Categorias</title>
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
 
    <a href="ver_pratos.php">Voltar ao Menu das Refeições</a>
    <a href="cadastrar_categoria.php">Cadastrar nova categoria</a>
  
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
    <h2>Categorias de Produtos</h2>

    <?php if ($resultado_categorias->num_rows > 0): ?>
        <?php while ($categoria = $resultado_categorias->fetch_assoc()): ?>
                <div class="categorias">
            <div class="card">
                <h3><?= htmlspecialchars($categoria['nome_categoria']) ?></h3>
                <?php
                // Prepara e executa a consulta para encontrar os produtos da categoria atual
                $sql_produtos = "
                    SELECT p.nome_produto
                    FROM produto p
                    JOIN produto_categoria pc ON p.id_produto = pc.id_produto
                    WHERE pc.id_categoria = ?
                    ORDER BY p.nome_produto ASC
                ";
                $stmt_produtos = $conexao->prepare($sql_produtos);
                $stmt_produtos->bind_param("i", $categoria['id_categoria']);
                $stmt_produtos->execute();
                $resultado_produtos = $stmt_produtos->get_result();

                if ($resultado_produtos->num_rows > 0) {
                    echo "<h4>Produtos nesta categoria:</h4>";
                    echo "<ul>";
                    while ($produto = $resultado_produtos->fetch_assoc()) {
                        echo "<li>" . htmlspecialchars($produto['nome_produto']) . "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p>Nenhum produto associado a esta categoria.</p>";
                }
                $stmt_produtos->close();
                ?>
                <div class="acoes">
                    <a href="editar_categoria.php?id_categoria=<?= $categoria['id_categoria'] ?>" class="editar">Editar</a>
                    <a href="excluir_categoria.php?id_categoria=<?= $categoria['id_categoria'] ?>" class="excluir" onclick="return confirm('Deseja realmente excluir esta categoria? Isso também excluirá os produtos associados.');">Excluir</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>Nenhuma categoria encontrada.</p>
    <?php endif; ?>
</div>
    </div>

</body>
</html>
<?php
$conexao->close();
?>
