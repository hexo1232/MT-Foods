<?php
session_start();
include "conexao.php";

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$mensagem = "";
$id_usuario = $_SESSION['id_usuario'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $senha_nova = $_POST["senha_nova"] ?? '';
    $senha_confirmar = $_POST["senha_confirmar"] ?? '';

    if (empty($senha_nova) || empty($senha_confirmar)) {
        $mensagem = "⚠️ Preencha todos os campos.";
    } elseif ($senha_nova !== $senha_confirmar) {
        $mensagem = "❌ As senhas não coincidem.";
    } elseif (strlen($senha_nova) < 6) {
        $mensagem = "⚠️ A senha deve ter pelo menos 6 caracteres.";
    } else {
        // Gerar hash seguro
        $senha_hash = password_hash($senha_nova, PASSWORD_DEFAULT);

        // Atualizar no banco
        $sql = "UPDATE usuario SET senha_hash = ?, primeira_senha = 0 WHERE id_usuario = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("si", $senha_hash, $id_usuario);

        if ($stmt->execute()) {
            // Atualiza a sessão com os novos dados
            $sqlUser = "SELECT * FROM usuario WHERE id_usuario = ?";
            $stmtUser = $conexao->prepare($sqlUser);
            $stmtUser->bind_param("i", $id_usuario);
            $stmtUser->execute();
            $result = $stmtUser->get_result();
            $_SESSION['usuario'] = $result->fetch_assoc();

            // Redireciona após alterar senha
            header("Location: cardapio.php?senha_alterada=1");
            exit;
        } else {
            $mensagem = "❌ Erro ao atualizar senha. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Alterar Senha</title>
    <script src="js/mostrarSenha.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
            width: 350px;
        }
        .card h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-size: 14px;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #27ae60;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background: #219150;
        }
        .mensagem {
            text-align: center;
            margin-top: 10px;
            color: red;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="card">
    <h2>Alterar Senha</h2>
    <form method="POST">
        <label>Nova Senha:</label>
        <input type="password" name="senha_nova" required>

        <label>Confirmar Senha:</label>
        <input type="password" name="senha_confirmar" required>

        <button type="submit">Salvar Senha</button>
    </form>
    <?php if (!empty($mensagem)): ?>
        <p class="mensagem"><?= htmlspecialchars($mensagem) ?></p>
    <?php endif; ?>
</div>
</body>
</html>
