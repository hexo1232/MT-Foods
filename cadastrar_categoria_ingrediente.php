<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

$mensagem = '';
$sucesso = false;
$redirecionar=false;

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario_logado = $_SESSION['usuario'];

// --- 1. Processamento do Formulário ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_categoria = trim($_POST['nome_categoria'] ?? '');
    $ingredientes_selecionados = $_POST['ingredientes'] ?? [];

    if (empty($nome_categoria)) {
        $mensagem = "O nome da categoria é obrigatório.";
    } else {
        // Iniciar Transação
        $conexao->begin_transaction();
        try {
            // 1. Inserir na tabela categoriadoingrediente
            $sql_insert_cat = "INSERT INTO categoriadoingrediente (nome_categoriadoingrediente) VALUES (?)";
            $stmt_cat = $conexao->prepare($sql_insert_cat);
            if ($stmt_cat === false) {
                throw new Exception("Erro na preparação da inserção da categoria: " . $conexao->error);
            }
            $stmt_cat->bind_param("s", $nome_categoria);
            
            if (!$stmt_cat->execute()) {
                throw new Exception("Erro ao inserir a categoria: " . $stmt_cat->error);
            }
            
            $id_categoria_nova = $conexao->insert_id;
            $stmt_cat->close();

            // 2. Inserir associações (se houver ingredientes selecionados)
            if (!empty($ingredientes_selecionados)) {
                $sql_insert_assoc = "INSERT INTO categoriadoingrediente_ingrediente (id_categoriadoingrediente, id_ingrediente) VALUES (?, ?)";
                $stmt_assoc = $conexao->prepare($sql_insert_assoc);
                if ($stmt_assoc === false) {
                    throw new Exception("Erro na preparação da associação: " . $conexao->error);
                }
                
                foreach ($ingredientes_selecionados as $id_ingrediente) {
                    // Validação básica para garantir que o ID é numérico
                    $id_ingrediente = (int)$id_ingrediente; 
                    if ($id_ingrediente > 0) {
                        $stmt_assoc->bind_param("ii", $id_categoria_nova, $id_ingrediente);
                        if (!$stmt_assoc->execute()) {
                            // Em um cenário real, você pode querer logar o erro e continuar, mas por segurança, abortamos a transação.
                            throw new Exception("Erro ao associar ingrediente ID: " . $id_ingrediente . ". Erro: " . $stmt_assoc->error);
                        }
                    }
                }
                $stmt_assoc->close();
            }

            // Commit se tudo correr bem
            $conexao->commit();
            $mensagem = "Categoria de Ingrediente '$nome_categoria' cadastrada com sucesso!";
            $sucesso = true;
            $redirecionar=true;

        } catch (Exception $e) {
            // Rollback em caso de erro
            $conexao->rollback();
            $mensagem = "Erro: " . $e->getMessage();
            $sucesso = false;
        }
    }
}

// --- 2. Obter lista de Ingredientes para o Formulário ---
$ingredientes = [];
$sql_ing = "SELECT id_ingrediente, nome_ingrediente FROM ingrediente ORDER BY nome_ingrediente ASC";
$resultado_ing = $conexao->query($sql_ing);
if ($resultado_ing) {
    while ($row = $resultado_ing->fetch_assoc()) {
        $ingredientes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cadastrar Categoria de Ingrediente</title>
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
        .btn-submit { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .btn-submit:hover { background-color: #45a049; }
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
    <h2>Cadastrar Nova Categoria de Ingrediente</h2>

    <?php if ($mensagem): ?>
        <div class="mensagem <?= $sucesso ? 'sucesso' : 'erro' ?>">
            <?= $mensagem ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="nome_categoria">Nome da Categoria:</label>
            <input type="text" id="nome_categoria" name="nome_categoria" required value="<?= htmlspecialchars($nome_categoria ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Ingredientes a Associar (Opcional):</label>
            <?php if (!empty($ingredientes)): ?>
                <div class="checkbox-group">
                    <?php foreach ($ingredientes as $ing): ?>
                        <label>
                            <input type="checkbox" name="ingredientes[]" value="<?= $ing['id_ingrediente'] ?>">
                            <?= htmlspecialchars($ing['nome_ingrediente']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Nenhum ingrediente encontrado na base de dados para associar.</p>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="btn-submit">Cadastrar Categoria</button>
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