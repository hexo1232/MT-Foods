
<?php 
$mensagem = "";
$redirecionar = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include "conexao.php";

    $nome     = htmlspecialchars(trim($_POST['nome']));
    $apelido  = htmlspecialchars(trim($_POST['apelido']));
    $telefone = htmlspecialchars(trim($_POST['telefone']));
    $email    = htmlspecialchars(trim($_POST['email']));
    $senha    = trim($_POST['senha']);
    $conf     = htmlspecialchars(trim($_POST['conf']));

    // Verificação dos campos obrigatórios
    if (empty($nome) || empty($apelido) || empty($telefone) || empty($email) || empty($senha) || empty($conf)) {
        $mensagem = "⚠️ Todos os campos são obrigatórios!";
    } 
    else if ($senha != $conf) {
        $mensagem = "❌ A senha e a confirmação não coincidem.";
    } 
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "❌ Email inválido.";
    } 
    elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{6,}$/', $senha)) {
        $mensagem = "❌ A senha deve ter pelo menos 6 caracteres, uma letra maiúscula, uma minúscula e um número.";
    }
    else { 
        // Criptografa a senha definida pelo usuário
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        // Ao se cadastrar escolhendo sua própria senha, primeira_senha = 0
        $sql = "INSERT INTO usuario (nome, apelido, telefone, email, senha_hash, idperfil, primeira_senha) 
                VALUES (?, ?, ?, ?, ?, 3, 0)";
        
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("sssss", $nome, $apelido, $telefone, $email, $senha_hash);

        if ($stmt->execute()) {
            $mensagem = "✅ Cadastro realizado com sucesso! Redirecionando para a tela de login...";
            $redirecionar = true;
        } else {
            $mensagem = "❌ Erro ao cadastrar: " . $conexao->error;
        }

        $stmt->close();
        $conexao->close();
    }
}
?>




<!DOCTYPE html>
<html>
<head>
    <title>Cadastro</title>
    <meta charset="UTF-8">
         <script src="logout_auto.js"></script>
          <script src="js/mostrarSenha.js"></script>
           <link rel="stylesheet" href="css/admin.css">
    
   <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
       <script src="js/sidebar.js"></script>
    <title>Cadastro de Usuário</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        h2 {
            text-align: center;
            color: #333;
        }

        form {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #aaa;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #f1bf1b;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background-color: #cfa61dff;
        }

        .mensagem {
            max-width: 500px;
            margin: 20px auto;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
        }

        .mensagem.success {
            background-color: #d4edda;
            color: #155724;
        }

        .mensagem.error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>

<button class="menu-btn">☰</button>

<!-- Overlay -->
<div class="sidebar-overlay"></div>

        <sidebar class="sidebar">
           <br><br>
        
            <a href="login.php">Voltar ao Login</a>
 
            <!-- ===== PERFIL NO FUNDO DA SIDEBAR ===== -->

        </sidebar>


    <?php if ($mensagem): ?>
        <div class="mensagem <?= str_contains($mensagem, '✅') ? 'success' : 'error' ?>">
            <?= $mensagem ?>
        </div>
    <?php endif; ?> <br><br>

 <h2>Cadastro de Usuário</h2>
    <form method="post" action="">
        <label>Nome:</label>
        <input type="text" name="nome" required><br>

        <label>Apelido:</label>
        <input type="text" name="apelido" required><br>

        <label>Telefone:</label>
        <input type="text" name="telefone" required placeholder="84/87/83 *******"><br>
        
   <label>Email:</label>
        <input type="email" name="email" placeholder="xxx@gmail.com" required><br>

   <label>Senha:</label>
        

             <div style="position: relative; display: flex; align-items: center; justify-content: center;">
    <input type="password" name="senha" class="campo-senha-nova" required
           style="width: 100%; padding-right: 35px; box-sizing: border-box;">
    <img src="icones/olho_fechado1.png"
         alt="Mostrar nova senha"
         class="toggle-senha"
         data-target="campo-senha-nova"
         style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
  </div>
           <label>Confirme a sua senha:</label>


         <div style="position: relative; display: flex; align-items: center; justify-content: center;">
    <input type="password" name="conf" class="campo-senha-confirmacao" required
           style="width: 100%; padding-right: 35px; box-sizing: border-box;">
    <img src="icones/olho_fechado1.png"
         alt="Mostrar confirmação de senha"
         class="toggle-senha"
         data-target="campo-senha-confirmacao"
         style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
  </div><br><br>
      
        <button type="submit">Cadastrar-se</button><br><br>

      
    </form>

    <?php if ($redirecionar): ?>
<script>
    // Redireciona em 3 segundos
    setTimeout(() => {
        window.location.href = 'login.php';
    }, 3000);
</script>
<?php endif; ?>


</body>
</html>