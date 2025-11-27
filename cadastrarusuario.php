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


// 🔄 AJAX: Carregar cidades da província
if (isset($_GET['ajax']) && $_GET['ajax'] === 'cidades') {
    $idprovincia = $_GET['provincia'] ?? null;

    if (!$idprovincia) exit;

    $sql = "SELECT idcidade, nome_cidade FROM cidade WHERE idprovincia = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $idprovincia);
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<option value="">Cidade</option>';
    while ($row = $result->fetch_assoc()) {
        echo '<option value="' . $row['idcidade'] . '">' . $row['nome_cidade'] . '</option>';
    }
    exit;
}

// 🔽 Carrega as províncias
$províncias = $conexao->query("SELECT idprovincia, nome_provincia FROM provincia");

// 🔐 Lógica principal
$mensagem = "";
$redirecionar = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = htmlspecialchars(trim($_POST['nome']));
    $apelido = htmlspecialchars(trim($_POST['apelido']));
    $numero = htmlspecialchars(trim($_POST['numero']));
    $email = htmlspecialchars(trim($_POST['email']));
    $opc = $_POST['opcao'];
    $idcidade = $_POST['cidade'];
    $idprovincia = $_POST['provincia'];

    if (empty($nome) || empty($apelido) || empty($numero) || empty($email) || empty($opc) || empty($idcidade) || empty($idprovincia)) {
        $mensagem = "⚠️ Todos os campos são obrigatórios!";
    }  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "❌ Email inválido.";
    }  else {

                $senhaPadrao = "123456";
        $senhaHash = password_hash($senhaPadrao, PASSWORD_DEFAULT);

        // Verificar duplicidade de email
        $verificar = $conexao->prepare("SELECT id_usuario FROM usuario WHERE email = ?");
        $verificar->bind_param("s", $email);
        $verificar->execute();
        $resultado = $verificar->get_result();

        if ($resultado->num_rows > 0) {
            $mensagem = "❌ Este e-mail já está cadastrado.";
        } else {


            $perfil = ($opc === "Funcionário") ? 2 : 3;

            $stmt = $conexao->prepare("INSERT INTO usuario (nome, apelido, telefone, email, senha_hash, idprovincia, idcidade, idperfil) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssii", $nome, $apelido, $numero, $email, $senhaHash, $idprovincia, $idcidade, $perfil);

            if ($stmt->execute()) {
                $mensagem = "✅ Usuário <b>$nome $apelido</b> cadastrado com sucesso! Redirecionando...";
                $redirecionar = true;
            } else {
                $mensagem = "❌ Erro ao cadastrar: " . $stmt->error;
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Cadastrar Usuário</title>
 
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
        <a href="ver_usuarios.php">Voltar aos Usuários</a>
         
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

 <?php if (!empty($mensagem)): ?>
    <div style="max-width: 600px; margin: 20px auto; padding: 15px; background: <?= $redirecionar ? '#d4edda' : '#f8d7da' ?>; color: <?= $redirecionar ? '#155724' : '#721c24' ?>; border-radius: 8px; font-weight: bold; text-align: center;">
        <?= $mensagem ?>
    </div>
    <?php endif; ?>
    
<div class="conteudo">

    <form method="post" action="">
        
    <h2>Cadastro de Novo Usuário</h2>
        <label>Nome:</label>
        <input type="text" name="nome" required><br>

        <label>Apelido:</label>
        <input type="text" name="apelido" required><br>

        <label>Telefone:</label>
        <input type="text" name="numero" required placeholder="84/87/83 *******"><br>

        <label>Email:</label>
        <input type="email" name="email" required><br>

   
        <label>Província:</label>
        <select name="provincia" id="provincia" onchange="carregarCidades()" required>
            <option value="">Selecione a Província</option>
            <?php while ($p = $províncias->fetch_assoc()) { ?>
                <option value="<?= $p['idprovincia'] ?>"><?= $p['nome_provincia'] ?></option>
            <?php } ?>
        </select><br>

        <label>Cidade:</label>
        <select name="cidade" id="cidade" required>
            <option value="">Cidade</option>
        </select><br>

        <label>Perfil:</label>
        <select name="opcao" required>
            <option value="Funcionário">Funcionário</option>
            <option value="Cliente">Cliente</option>
        </select><br><br>

        <button type="submit">Cadastrar</button>
    </form>
            </div>


           

    <?php if ($redirecionar): ?>
        <script>
            setTimeout(() => {
                window.location.href = 'ver_usuarios.php';
            }, 3000);
        </script>
    <?php endif; ?>

    <script>
        function carregarCidades() {
            const provincia = document.getElementById("provincia").value;
            if (!provincia) return;

            fetch(`?ajax=cidades&provincia=${provincia}`)
                .then(res => res.text())
                .then(data => document.getElementById("cidade").innerHTML = data)
                .catch(() => alert("Erro ao carregar cidades."));
        }
    </script>
</body>
</html>
