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

$mensagem = '';
$redirecionar=false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Inicia uma transação para garantir que ambas as operações sejam bem-sucedidas
    $conexao->begin_transaction();
    
    try {
        $nome_categoria = trim($_POST['nome_categoria']);
        $descricao = trim($_POST['descricao']);
        $produtos_selecionados = isset($_POST['produtos']) ? $_POST['produtos'] : [];

        // Valida se o nome da categoria não está vazio
        if (empty($nome_categoria)) {
            throw new Exception("O nome da categoria é obrigatório.");
        }

        // Insere a nova categoria
        $sql_insert_categoria = "INSERT INTO categoria (nome_categoria, descricao) VALUES (?, ?)";
        $stmt_categoria = $conexao->prepare($sql_insert_categoria);
        $stmt_categoria->bind_param("ss", $nome_categoria, $descricao);
        $stmt_categoria->execute();
        $id_categoria = $conexao->insert_id;
        $stmt_categoria->close();

        // Associa a nova categoria aos produtos selecionados
        if (!empty($produtos_selecionados)) {
            $sql_associar_produto = "INSERT INTO produto_categoria (id_produto, id_categoria) VALUES (?, ?)";
            $stmt_associar = $conexao->prepare($sql_associar_produto);

            foreach ($produtos_selecionados as $id_produto) {
                $stmt_associar->bind_param("ii", $id_produto, $id_categoria);
                $stmt_associar->execute();
            }
            $stmt_associar->close();
        }

        // Se tudo deu certo, confirma a transação
        $conexao->commit();
        $mensagem = "Categoria '{$nome_categoria}' e associações criadas com sucesso!";
        
$redirecionar=true;

    } catch (Exception $e) {
        // Se algo deu errado, desfaz a transação
        $conexao->rollback();
        $mensagem = "Erro ao cadastrar categoria: " . $e->getMessage();
    }
}

// Busca todos os produtos para exibir na lista de seleção
$sql_produtos = "SELECT id_produto, nome_produto FROM produto ORDER BY nome_produto ASC";
$resultado_produtos = $conexao->query($sql_produtos);

$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Categoria</title>
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <script src="logout_auto.js"></script>
       <script src="js/sidebar.js"></script>
    
    <link rel="stylesheet" href="css/admin.css">
          <script src="js/darkmode2.js"></script>
          <script src="js/dropdown2.js"></script>
</head>
<body>

<button class="menu-btn">☰</button>

<!-- Overlay -->
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
  
<br><br>

    <a href="ver_categorias.php">Voltar ás categorias</a>
 

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
    <div class="card">
        <h2>Cadastrar Nova Categoria</h2>

        <?php if (!empty($mensagem)): ?>
            <div class="<?= (strpos($mensagem, 'sucesso') !== false) ? 'mensagem-sucesso' : 'mensagem-erro' ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="nome_categoria">Nome da Categoria:</label>
                <input type="text" id="nome_categoria" name="nome_categoria" required>
            </div>
            <div class="form-group">
                <label for="descricao">Descrição (opcional):</label>
                <textarea id="descricao" name="descricao" rows="4"></textarea>
            </div>
            
            <div class="form-group">
                <label>Associar produtos:</label>
                <div class="checkbox-group">
                    <?php if ($resultado_produtos->num_rows > 0): ?>
                        <?php while ($produto = $resultado_produtos->fetch_assoc()): ?>
                            <div class="produto-item">
                                <input type="checkbox" id="produto_<?= $produto['id_produto'] ?>" name="produtos[]" value="<?= $produto['id_produto'] ?>">
                                <label for="produto_<?= $produto['id_produto'] ?>"><?= htmlspecialchars($produto['nome_produto']) ?></label>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>Nenhum produto disponível para associação.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">Cadastrar Categoria</button>
        </form>
    </div>
</div>
 <?php if ($redirecionar): ?>
        <script>
            setTimeout(() => {
                window.location.href = 'ver_categorias.php';
            }, 3000);
        </script>
    <?php endif; ?>
</body>
</html>
