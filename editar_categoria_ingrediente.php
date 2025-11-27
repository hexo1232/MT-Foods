<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

$id_categoria = (int)($_GET['id'] ?? 0);
$mensagem = '';
$sucesso = false;
$redirecionar=false;

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

if ($id_categoria <= 0) {
    header("Location: ver_categoria_ingrediente.php");
    exit;
}

// --- 1. Carregar dados da Categoria e Associações Atuais ---
$categoria = null;
$ingredientes_associados_ids = [];

// A) Carregar dados da categoria
$sql_cat = "SELECT id_categoriadoingrediente, nome_categoriadoingrediente FROM categoriadoingrediente WHERE id_categoriadoingrediente = ?";
$stmt_cat_load = $conexao->prepare($sql_cat);
if ($stmt_cat_load) {
    $stmt_cat_load->bind_param("i", $id_categoria);
    $stmt_cat_load->execute();
    $resultado_cat = $stmt_cat_load->get_result();
    $categoria = $resultado_cat->fetch_assoc();
    $stmt_cat_load->close();

    if (!$categoria) {
        header("Location: ver_categoria_ingrediente.php");
        exit;
    }
} else {
    die("Erro na preparação (Carregar Categoria): " . $conexao->error);
}

// B) Carregar IDs dos ingredientes associados
$sql_assoc_ids = "SELECT id_ingrediente FROM categoriadoingrediente_ingrediente WHERE id_categoriadoingrediente = ?";
$stmt_assoc_ids = $conexao->prepare($sql_assoc_ids);
if ($stmt_assoc_ids) {
    $stmt_assoc_ids->bind_param("i", $id_categoria);
    $stmt_assoc_ids->execute();
    $resultado_assoc_ids = $stmt_assoc_ids->get_result();
    while ($row = $resultado_assoc_ids->fetch_assoc()) {
        $ingredientes_associados_ids[] = $row['id_ingrediente'];
    }
    $stmt_assoc_ids->close();
} else {
    die("Erro na preparação (Carregar Associações): " . $conexao->error);
}


// --- 2. Processamento do Formulário (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $novo_nome = trim($_POST['nome_categoria'] ?? '');
    $novos_ingredientes_selecionados = $_POST['ingredientes'] ?? [];
    
    // Atualizar as associações (para o formulário) para o caso de erro
    $ingredientes_associados_ids = $novos_ingredientes_selecionados;


    if (empty($novo_nome)) {
        $mensagem = "O nome da categoria é obrigatório.";
    } else {
        // Iniciar Transação
        $conexao->begin_transaction();
        try {
            // A) Atualizar o nome da categoria
            $sql_update_cat = "UPDATE categoriadoingrediente SET nome_categoriadoingrediente = ? WHERE id_categoriadoingrediente = ?";
            $stmt_update_cat = $conexao->prepare($sql_update_cat);
            if ($stmt_update_cat === false) {
                throw new Exception("Erro na preparação do UPDATE da categoria: " . $conexao->error);
            }
            $stmt_update_cat->bind_param("si", $novo_nome, $id_categoria);
            
            if (!$stmt_update_cat->execute()) {
                throw new Exception("Erro ao atualizar a categoria: " . $stmt_update_cat->error);
            }
            $stmt_update_cat->close();

            // B) Limpar associações antigas
            $sql_delete_assoc = "DELETE FROM categoriadoingrediente_ingrediente WHERE id_categoriadoingrediente = ?";
            $stmt_delete_assoc = $conexao->prepare($sql_delete_assoc);
            if ($stmt_delete_assoc === false) {
                 throw new Exception("Erro na preparação do DELETE das associações: " . $conexao->error);
            }
            $stmt_delete_assoc->bind_param("i", $id_categoria);
            
            if (!$stmt_delete_assoc->execute()) {
                throw new Exception("Erro ao limpar associações antigas: " . $stmt_delete_assoc->error);
            }
            $stmt_delete_assoc->close();

            // C) Inserir novas associações (se houver ingredientes selecionados)
            if (!empty($novos_ingredientes_selecionados)) {
                $sql_insert_assoc = "INSERT INTO categoriadoingrediente_ingrediente (id_categoriadoingrediente, id_ingrediente) VALUES (?, ?)";
                $stmt_insert_assoc = $conexao->prepare($sql_insert_assoc);
                if ($stmt_insert_assoc === false) {
                    throw new Exception("Erro na preparação da reinserção de associações: " . $conexao->error);
                }
                
                foreach ($novos_ingredientes_selecionados as $id_ingrediente) {
                    $id_ingrediente = (int)$id_ingrediente; 
                    if ($id_ingrediente > 0) {
                        $stmt_insert_assoc->bind_param("ii", $id_categoria, $id_ingrediente);
                        if (!$stmt_insert_assoc->execute()) {
                            throw new Exception("Erro ao reassociar ingrediente ID: " . $id_ingrediente . ". Erro: " . $stmt_insert_assoc->error);
                        }
                    }
                }
                $stmt_insert_assoc->close();
            }

            // Commit se tudo correr bem
            $conexao->commit();
            $mensagem = "Categoria de Ingrediente '$novo_nome' atualizada com sucesso!";
            $sucesso = true;
            $redirecionar=true;

            // Atualizar nome no objeto categoria para exibir no formulário após sucesso
            $categoria['nome_categoriadoingrediente'] = $novo_nome;

        } catch (Exception $e) {
            // Rollback em caso de erro
            $conexao->rollback();
            $mensagem = "Erro ao atualizar a categoria: " . $e->getMessage();
            $sucesso = false;
        }
    }
}

// --- 3. Obter lista de Ingredientes para o Formulário (sempre) ---
$ingredientes_todos = [];
$sql_ing = "SELECT id_ingrediente, nome_ingrediente FROM ingrediente ORDER BY nome_ingrediente ASC";
$resultado_ing = $conexao->query($sql_ing);
if ($resultado_ing) {
    while ($row = $resultado_ing->fetch_assoc()) {
        $ingredientes_todos[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Editar Categoria de Ingrediente</title>
    <script src="logout_auto.js"></script>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/dropdown2.js"></script>
    <style>
        .form-group label { display: block; margin-top: 15px; font-weight: bold; }
        .form-group input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .checkbox-group label { display: inline-flex; align-items: center; font-weight: normal; }
        .checkbox-group input[type="checkbox"] { margin-right: 5px; }
        .btn-submit { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .btn-submit:hover { background-color: #0056b3; }
        .mensagem { padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .mensagem.sucesso { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .mensagem.erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<button class="menu-btn">☰</button>
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
    <br><br>
   <a href="ver_categoria_ingrediente.php">Voltar</a>

    
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

    <!-- BOTÃO DE MODO ESCURO -->
    <img class="dark-toggle" id="darkToggle"
         src="icones/lua.png"
         alt="Modo Escuro"
         title="Alternar modo escuro">
</div>

</sidebar>

<div class="conteudo">
    <h2>Editar Categoria de Ingrediente: <?= htmlspecialchars($categoria['nome_categoriadoingrediente'] ?? 'N/A') ?></h2>

    <?php if ($mensagem): ?>
        <div class="mensagem <?= $sucesso ? 'sucesso' : 'erro' ?>">
            <?= $mensagem ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="nome_categoria">Nome da Categoria:</label>
            <input type="text" id="nome_categoria" name="nome_categoria" required value="<?= htmlspecialchars($categoria['nome_categoriadoingrediente'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Gerenciar Ingredientes Associados:</label>
            <?php if (!empty($ingredientes_todos)): ?>
                <div class="checkbox-group">
                    <?php foreach ($ingredientes_todos as $ing): ?>
                        <label>
                            <input 
                                type="checkbox" 
                                name="ingredientes[]" 
                                value="<?= $ing['id_ingrediente'] ?>" 
                                <?= in_array($ing['id_ingrediente'], $ingredientes_associados_ids) ? 'checked' : '' ?>
                            >
                            <?= htmlspecialchars($ing['nome_ingrediente']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Nenhum ingrediente disponível para associação.</p>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="btn-submit">Salvar Alterações</button>
    </form>
</div>

<?php if ($redirecionar): ?>
        <script>
            setTimeout(() => {
                window.location.href = 'ver_categoria_ingrediente.php';
            }, 3000);
        </script>
    <?php endif; ?>
    
</body>
</html>
<?php
$conexao->close();
?>