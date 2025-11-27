<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php"; // Presumido que este ficheiro existe para obter info do usuário

// Prevenção de cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario_logado = $_SESSION['usuario'];

// Consulta para obter todas as categorias de ingredientes
$sql_categorias = "SELECT id_categoriadoingrediente, nome_categoriadoingrediente FROM categoriadoingrediente ORDER BY nome_categoriadoingrediente ASC";
$resultado_categorias = $conexao->query($sql_categorias);

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Gerenciar Categorias de Ingredientes</title>
    <script src="logout_auto.js"></script>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/dropdown2.js"></script>
</head>
<body>

<button class="menu-btn">☰</button>
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
    <br><br>
    <a href="ver_ingredientes.php">Voltar ao Menu dos Ingredientes</a>
    <a href="cadastrar_categoria_ingrediente.php">Cadastrar nova Categoria de Ingrediente</a>
    
  <!-- ===== PERFIL NO FUNDO DA SIDEBAR ===== -->
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
        <a href='editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>'>
        <img class="icone" src="icones/user1.png" alt="Editar" title="Editar">    
        Editar Dados Pessoais</a>
        <a href="alterar_senha2.php">
    <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar"> 
        Alterar Senha</a>
        <a href="logout.php">
        <img class="iconelogout" src="icones/logout1.png" alt="Logout" title="Sair">    
        Sair</a>
    </div>

</div>

</sidebar>

<div class="conteudo">
    <h2>Categorias de Ingredientes</h2>

    <?php if ($resultado_categorias->num_rows > 0): ?>
        <div class="categorias-container">
        <?php while ($categoria = $resultado_categorias->fetch_assoc()): ?>
            <div class="card">
                <h3><?= htmlspecialchars($categoria['nome_categoriadoingrediente']) ?></h3>
                
                <?php
                // Prepara e executa a consulta para encontrar os ingredientes associados
                $sql_ingredientes = "
                    SELECT i.nome_ingrediente
                    FROM ingrediente i
                    JOIN categoriadoingrediente_ingrediente cii ON i.id_ingrediente = cii.id_ingrediente
                    WHERE cii.id_categoriadoingrediente = ?
                    ORDER BY i.nome_ingrediente ASC
                ";
                
                $stmt_ingredientes = $conexao->prepare($sql_ingredientes);
                if ($stmt_ingredientes === false) {
                    echo "<p>Erro na preparação da consulta de ingredientes: " . $conexao->error . "</p>";
                    continue; 
                }
                
                $stmt_ingredientes->bind_param("i", $categoria['id_categoriadoingrediente']);
                $stmt_ingredientes->execute();
                $resultado_ingredientes = $stmt_ingredientes->get_result();

                if ($resultado_ingredientes->num_rows > 0) {
                    echo "<h4>Ingredientes nesta categoria:</h4>";
                    echo "<ul>";
                    while ($ingrediente = $resultado_ingredientes->fetch_assoc()) {
                        echo "<li>" . htmlspecialchars($ingrediente['nome_ingrediente']) . "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p>Nenhum ingrediente associado a esta categoria.</p>";
                }
                $stmt_ingredientes->close();
                ?>
                
                <div class="acoes">
                    <a href="editar_categoria_ingrediente.php?id=<?= $categoria['id_categoriadoingrediente'] ?>" class="editar">Editar</a>
                    <a href="excluir_categoria_ingrediente.php?id=<?= $categoria['id_categoriadoingrediente'] ?>" class="excluir" onclick="return confirm('Deseja realmente excluir esta categoria? As associações com os ingredientes serão removidas, mas os ingredientes NÃO serão eliminados.');">Excluir</a>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>Nenhuma categoria de ingrediente encontrada.</p>
    <?php endif; ?>
</div>

</body>
</html>
<?php
$conexao->close();
?>