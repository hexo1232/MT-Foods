<?php
require_once __DIR__ . '/../src/PasswordReset.php'; // contém verifyToken() e a conexão PDO
//public/reset_password.php

// Obtém o token da URL
$token = $_GET['token'] ?? '';

// Verifica se o token é válido
$record = verifyToken($pdo, $token);

if (!$record) {
    // Se o token for inválido ou expirado, mostra mensagem e botão para pedir novo link
    echo "
    <div style='
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        flex-direction: column;
        background-color: #f8f9fa;
        font-family: Arial, sans-serif;
    '>
        <div style='
            background-color: #fff3cd;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        '>
            <h3 style='color:#856404;'>Token inválido ou expirado</h3>
            <p style='margin-bottom:20px;color:#856404;'>Solicite um novo link para redefinir a sua palavra-passe.</p>
            <a href='forgot_password.php'
               style='
                   display:inline-block;
                   color:#004085;
                   text-decoration:none;
                   font-weight:bold;
                   padding:10px 20px;
                   border-radius:8px;
                   background-color:#fefcbf;
                   transition: background 0.3s;
               '
               onmouseover=\"this.style.backgroundColor='#fff9c4'\"
               onmouseout=\"this.style.backgroundColor='#fefcbf'\">
               Pedir novo link de redefinição
            </a>
        </div>
    </div>";
    exit;
}
?>


<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Redefinir Palavra-passe</title>
    <link rel="stylesheet" href="../css/admin.css">
    <script src="../js/darkmode1.js"></script>
    <style>
    

        form {
            max-width: 400px;
            margin: 60px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
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
            background-color: #b68c05ff;
        }
    </style>
</head>
<body>
    <form method="POST" action="update_password.php">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <h2 style="text-align:center;">Redefinir palavra-passe</h2>

        <label>Nova palavra-passe:</label>

          <div style="position: relative; display: flex; align-items: center; justify-content: center;">
    <input type="password" name="nova_senha" class="campo-senha-nova" required
           style="width: 100%; padding-right: 35px; box-sizing: border-box;">
    <img src="icones/olho_fechado1.png"
         alt="Mostrar nova senha"
         class="toggle-senha"
         data-target="campo-senha-nova"
         style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
  </div>

        <label>Confirmar palavra-passe:</label>
     

      <div style="position: relative; display: flex; align-items: center; justify-content: center;">
    <input type="password" name="confirmar_senha" class="campo-senha-confirmacao" required
           style="width: 100%; padding-right: 35px; box-sizing: border-box;">
    <img src="icones/olho_fechado1.png"
         alt="Mostrar confirmação de senha"
         class="toggle-senha"
         data-target="campo-senha-confirmacao"
         style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
  </div><br><br>

        <button type="submit">Atualizar senha</button>
    </form>


    <script>
    document.addEventListener("DOMContentLoaded", () => {
  const botoesOlho = document.querySelectorAll(".toggle-senha");

  // Verifica se o modo escuro está ativo
  function estaDarkMode() {
    return document.body.classList.contains("dark-mode");
  }

  // Atualiza o ícone do olho de acordo com o estado e o tema
  function atualizarIcone(botao, visivel) {
    const dark = estaDarkMode();
    if (visivel) {
      // Senha visível → olho aberto
      botao.src = dark
        ? "../icones/olho_aberto2.png"  // modo escuro
        : "../icones/olho_aberto1.png"; // modo claro
    } else {
      // Senha oculta → olho fechado
      botao.src = dark
        ? "../icones/olho_fechado2.png" // modo escuro
        : "../icones/olho_fechado1.png";// modo claro
    }
  }

  // Função que liga cada botão ao seu input (usando classes)
  botoesOlho.forEach((botao) => {
    const targetClass = botao.dataset.target;
    const input = document.querySelector(`.${targetClass}`);

    if (!input) return;

    // Define o ícone inicial com base no modo atual
    atualizarIcone(botao, false);

    // Alternar a visibilidade da senha ao clicar
    botao.addEventListener("click", () => {
      const visivel = input.type === "text";
      input.type = visivel ? "password" : "text";
      atualizarIcone(botao, !visivel);
    });
  });

  // Observa mudanças na classe do body (para atualizar ao trocar de tema)
  const observer = new MutationObserver(() => {
    botoesOlho.forEach((botao) => {
      const targetClass = botao.dataset.target;
      const input = document.querySelector(`.${targetClass}`);
      if (input) {
        const visivel = input.type === "text";
        atualizarIcone(botao, visivel);
      }
    });
  });

  observer.observe(document.body, { attributes: true, attributeFilter: ["class"] });
});

</script>


</body>
</html>
