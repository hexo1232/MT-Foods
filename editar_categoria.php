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
$categoria = null;
$produtos_associados = [];
$redirecionar=false;


// 1. Lógica para carregar os dados da categoria e produtos associados (GET)
if (isset($_GET['id_categoria'])) {
    $id_categoria = $_GET['id_categoria'];

    // Busca os dados da categoria
    $sql_categoria = "SELECT * FROM categoria WHERE id_categoria = ?";
    $stmt_categoria = $conexao->prepare($sql_categoria);
    $stmt_categoria->bind_param("i", $id_categoria);
    $stmt_categoria->execute();
    $resultado_categoria = $stmt_categoria->get_result();

    if ($resultado_categoria->num_rows > 0) {
        $categoria = $resultado_categoria->fetch_assoc();
    } else {
        $mensagem = "Erro: Categoria não encontrada.";
        $categoria = null; // Garante que o formulário não seja exibido
    }
    $stmt_categoria->close();

    // Busca os produtos associados a esta categoria
    $sql_produtos_associados = "SELECT id_produto FROM produto_categoria WHERE id_categoria = ?";
    $stmt_associados = $conexao->prepare($sql_produtos_associados);
    $stmt_associados->bind_param("i", $id_categoria);
    $stmt_associados->execute();
    $resultado_associados = $stmt_associados->get_result();

    while ($row = $resultado_associados->fetch_assoc()) {
        $produtos_associados[] = $row['id_produto'];
    }
    $stmt_associados->close();
}

// 2. Lógica para atualizar a categoria e associações (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_categoria = $_POST['id_categoria'];

    // Obtém os dados originais do banco de dados para comparação
    $sql_original_categoria = "SELECT nome_categoria, descricao FROM categoria WHERE id_categoria = ?";
    $stmt_original_categoria = $conexao->prepare($sql_original_categoria);
    $stmt_original_categoria->bind_param("i", $id_categoria);
    $stmt_original_categoria->execute();
    $original_categoria = $stmt_original_categoria->get_result()->fetch_assoc();
    $stmt_original_categoria->close();

    $sql_original_associacoes = "SELECT id_produto FROM produto_categoria WHERE id_categoria = ?";
    $stmt_original_associacoes = $conexao->prepare($sql_original_associacoes);
    $stmt_original_associacoes->bind_param("i", $id_categoria);
    $stmt_original_associacoes->execute();
    $original_produtos_associados = [];
    $resultado_original_associacoes = $stmt_original_associacoes->get_result();
    while ($row = $resultado_original_associacoes->fetch_assoc()) {
        $original_produtos_associados[] = $row['id_produto'];
    }
    $stmt_original_associacoes->close();

    // Obtém os novos dados do formulário
    $nome_categoria = trim($_POST['nome_categoria']);
    $descricao = trim($_POST['descricao']);
    $produtos_selecionados = isset($_POST['produtos']) ? $_POST['produtos'] : [];

    // Ordena os arrays para uma comparação correta
    sort($produtos_selecionados);
    sort($original_produtos_associados);

    // Verifica se houve alguma alteração nos dados
    $is_nome_changed = $nome_categoria !== $original_categoria['nome_categoria'];
    $is_descricao_changed = $descricao !== $original_categoria['descricao'];
    $is_produtos_changed = ($produtos_selecionados != $original_produtos_associados);

    if (!$is_nome_changed && !$is_descricao_changed && !$is_produtos_changed) {
        // Nenhuma alteração detectada, redireciona com mensagem específica
        header("Location: editar_categoria.php?id_categoria=" . $id_categoria . "&msg=nenhuma_alteracao");
        exit;
    }

    // Se houver alterações, inicia a transação e atualiza o banco de dados
    $conexao->begin_transaction();

    try {
        // Atualiza a categoria
        $sql_update_categoria = "UPDATE categoria SET nome_categoria = ?, descricao = ? WHERE id_categoria = ?";
        $stmt_update = $conexao->prepare($sql_update_categoria);
        $stmt_update->bind_param("ssi", $nome_categoria, $descricao, $id_categoria);
        $stmt_update->execute();
        $stmt_update->close();

        // Exclui todas as associações existentes para esta categoria
        $sql_delete_associacoes = "DELETE FROM produto_categoria WHERE id_categoria = ?";
        $stmt_delete = $conexao->prepare($sql_delete_associacoes);
        $stmt_delete->bind_param("i", $id_categoria);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        // Associa a categoria aos produtos selecionados
        if (!empty($produtos_selecionados)) {
            $sql_associar_produto = "INSERT INTO produto_categoria (id_produto, id_categoria) VALUES (?, ?)";
            $stmt_associar = $conexao->prepare($sql_associar_produto);

            foreach ($produtos_selecionados as $id_produto) {
                $stmt_associar->bind_param("ii", $id_produto, $id_categoria);
                $stmt_associar->execute();
            }
            $stmt_associar->close();
        }

        $conexao->commit();
        header("Location: editar_categoria.php?id_categoria=" . $id_categoria . "&msg=sucesso");
        exit;
        
    } catch (Exception $e) {
        $conexao->rollback();
        $mensagem = "Erro ao atualizar categoria: " . $e->getMessage();
    }
}

// Busca todos os produtos para exibir na lista de seleção
$sql_todos_produtos = "SELECT id_produto, nome_produto FROM produto ORDER BY nome_produto ASC";
$resultado_todos_produtos = $conexao->query($sql_todos_produtos);

$conexao->close();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Editar Categoria</title>
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
   <a href="ver_categorias.php">Voltar ás categorias</a>
      
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
    <div class="card">
        <h2>Editar Categoria</h2>

        <?php 
        if (isset($_GET['msg'])) {
            if ($_GET['msg'] === 'sucesso') {
                $mensagem = "Categoria e associações atualizadas com sucesso!";
                                $classe_mensagem = 'mensagem-sucesso';
                                
$redirecionar=true;

            } elseif ($_GET['msg'] === 'nenhuma_alteracao') {
                $mensagem = "Nenhuma alteração detectada. A categoria permaneceu inalterada.";
                $classe_mensagem = 'mensagem-neutra';
            }
        }
        if (!empty($mensagem)): 
            $classe_mensagem = isset($classe_mensagem) ? $classe_mensagem : '';
        ?>
            <div class="<?= $classe_mensagem ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <?php if ($categoria): ?>
            <form method="post" action="">
                <input type="hidden" name="id_categoria" value="<?= htmlspecialchars($categoria['id_categoria']) ?>">
                
                <div class="form-group">
                    <label for="nome_categoria">Nome da Categoria:</label>
                    <input type="text" id="nome_categoria" name="nome_categoria" value="<?= htmlspecialchars($categoria['nome_categoria']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição (opcional):</label>
                    <textarea id="descricao" name="descricao" rows="4"><?= htmlspecialchars($categoria['descricao']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Associar produtos:</label>
                    <div class="checkbox-group">
                        <?php if ($resultado_todos_produtos->num_rows > 0): ?>
                            <?php while ($produto = $resultado_todos_produtos->fetch_assoc()): ?>
                                <div class="produto-item">
                                    <?php 
                                    $checked = in_array($produto['id_produto'], $produtos_associados) ? 'checked' : '';
                                    ?>
                                    <input type="checkbox" id="produto_<?= $produto['id_produto'] ?>" name="produtos[]" value="<?= $produto['id_produto'] ?>" <?= $checked ?>>
                                    <label for="produto_<?= $produto['id_produto'] ?>"><?= htmlspecialchars($produto['nome_produto']) ?></label>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>Nenhum produto disponível para associação.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">Salvar Alterações</button>
            </form>
             <?php if ($redirecionar): ?>
        <script>
            setTimeout(() => {
                window.location.href = 'ver_categorias.php';
            }, 3000);
        </script>
    <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
