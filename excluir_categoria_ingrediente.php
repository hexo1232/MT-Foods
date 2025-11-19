<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

$id_categoria = (int)($_GET['id'] ?? 0);
$mensagem = '';
$sucesso = false;

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

if ($id_categoria <= 0) {
    // Redireciona se o ID for inválido
    header("Location: ver_categoria_ingrediente.php");
    exit;
}

// --- Processamento da Exclusão ---
try {
    // A exclusão da categoria na tabela principal, devido ao ON DELETE CASCADE
    // na tabela categoriadoingrediente_ingrediente, irá automaticamente
    // desfazer as associações, mantendo o ingrediente intacto.
    $sql_delete = "DELETE FROM categoriadoingrediente WHERE id_categoriadoingrediente = ?";
    $stmt_delete = $conexao->prepare($sql_delete);
    
    if ($stmt_delete === false) {
        throw new Exception("Erro na preparação da exclusão: " . $conexao->error);
    }
    
    $stmt_delete->bind_param("i", $id_categoria);
    
    if (!$stmt_delete->execute()) {
        throw new Exception("Erro ao excluir a categoria: " . $stmt_delete->error);
    }
    
    $linhas_afetadas = $stmt_delete->affected_rows;
    $stmt_delete->close();
    
    if ($linhas_afetadas > 0) {
        $mensagem = "Categoria de ingrediente excluída com sucesso. As associações foram desfeitas.";
        $sucesso = true;
    } else {
        $mensagem = "Categoria não encontrada ou já excluída.";
        $sucesso = false;
    }

} catch (Exception $e) {
    $mensagem = "Erro ao processar a exclusão: " . $e->getMessage();
    $sucesso = false;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Excluir Categoria de Ingrediente</title>
    <script src="logout_auto.js"></script>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="js/sidebar.js"></script>
    <style>
        .mensagem { 
            padding: 20px; 
            border-radius: 8px; 
            margin: 40px auto; 
            max-width: 600px; 
            text-align: center; 
            font-size: 1.1em;
        }
        .mensagem.sucesso { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .mensagem.erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-voltar { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 15px; }
        .btn-voltar:hover { background-color: #0056b3; }
    </style>
</head>
<body>

<button class="menu-btn">☰</button>
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
    <a href="dashboard.php">Voltar ao Menu Principal</a>
    <a href="ver_categoria_ingrediente.php">Gerenciar Categorias de Ingredientes</a>
    
    <div class="sidebar-footer">
        <a href="logout.php" title="Sair"><img id="iconelogout" src="icones/logout.png" alt="Logout"></a>
        <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Modo Escuro" title="Alternar modo escuro">
    </div>
</sidebar>

<div class="conteudo">
    <h2>Exclusão de Categoria de Ingrediente</h2>

    <div class="mensagem <?= $sucesso ? 'sucesso' : 'erro' ?>">
        <p><?= $mensagem ?></p>
        <a href="ver_categoria_ingrediente.php" class="btn-voltar">Voltar para Categorias</a>
    </div>
</div>

</body>
</html>
<?php
$conexao->close();
?>