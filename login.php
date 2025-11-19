<?php

session_start();
require_once 'conexao.php';

// --- Função de Lógica de Personalização (Mantida do seu código) ---
function criarStringPersonalizacao($conexao, $reduzidos, $incrementados) {
    $mensagens = [];
    $ids_modificados = [];

    if (is_array($reduzidos)) {
        foreach ($reduzidos as $ing) {
            if (isset($ing['id_ingrediente'])) {
                $ids_modificados[] = $ing['id_ingrediente'];
            }
        }
    }

    if (is_array($incrementados)) {
        foreach ($incrementados as $ing) {
            if (isset($ing['id_ingrediente'])) {
                $ids_modificados[] = $ing['id_ingrediente'];
            }
        }
    }

    $nomes_ingredientes = [];

    if (!empty($ids_modificados)) {
        $ids_unicos = array_unique($ids_modificados);
        $ids_str = implode(',', array_map('intval', $ids_unicos));

        $sql_nomes = "SELECT id_ingrediente, nome_ingrediente
                      FROM ingrediente
                      WHERE id_ingrediente IN ($ids_str)";
        $res_nomes = $conexao->query($sql_nomes);

        if ($res_nomes) {
            while ($row = $res_nomes->fetch_assoc()) {
                $nomes_ingredientes[$row['id_ingrediente']] = $row['nome_ingrediente'];
            }
        }
    }

    if (is_array($reduzidos)) {
        foreach ($reduzidos as $ing) {
            if (isset($ing['id_ingrediente']) && isset($ing['qtd'])) {
                $nome = $nomes_ingredientes[$ing['id_ingrediente']] ?? 'Ingrediente Desconhecido';
                $mensagens[] = "$nome reduzido " . $ing['qtd'] . " vez" . ($ing['qtd'] > 1 ? "es" : "");
            }
        }
    }

    if (is_array($incrementados)) {
        foreach ($incrementados as $ing) {
            if (isset($ing['id_ingrediente']) && isset($ing['qtd'])) {
                $nome = $nomes_ingredientes[$ing['id_ingrediente']] ?? 'Ingrediente Desconhecido';
                $mensagens[] = "$nome incrementado " . $ing['qtd'] . " vez" . ($ing['qtd'] > 1 ? "es" : "");
            }
        }
    }

    if (empty($mensagens)) {
        return "Sem personalizações adicionais.";
    }

    return implode(' | ', $mensagens);
}

// --- Processamento do Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entrada = trim($_POST['entrada'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (isset($_GET['redir'])) {
        $_SESSION['url_destino'] = basename($_GET['redir']);
    }

    if ($entrada && $senha) {
        $sql = "SELECT * FROM usuario
                WHERE email = ? OR telefone = ? OR nome = ?
                LIMIT 1";

        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("sss", $entrada, $entrada, $entrada);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();

            if (password_verify($senha, $usuario['senha_hash'])) {
                $_SESSION['usuario'] = $usuario;
                $idUsuario = $usuario['id_usuario'];

                     // 🚨 Verificação se está com senha padrão
            if ((int)$usuario['primeira_senha'] === 1) {
                // Redireciona para alteração de senha obrigatória
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                header("Location: alterar_senha.php?primeiro=1");
                exit;
            }
                // Inicia a transação para garantir a integridade dos dados
                $conexao->begin_transaction();

                try {
                    // Buscar ou criar carrinho ativo para o usuário logado
                    $stmt_carrinho_logado = $conexao->prepare("SELECT id_carrinho
                                                               FROM carrinho
                                                               WHERE id_usuario = ? AND status = 'activo'
                                                               LIMIT 1");
                    $stmt_carrinho_logado->bind_param("i", $idUsuario);
                    $stmt_carrinho_logado->execute();
                    $res_carrinho_logado = $stmt_carrinho_logado->get_result();

                    if ($res_carrinho_logado->num_rows > 0) {
                        $id_carrinho = $res_carrinho_logado->fetch_assoc()['id_carrinho'];
                    } else {
                        $stmt = $conexao->prepare("INSERT INTO carrinho (id_usuario, data_criacao, status)
                                                   VALUES (?, NOW(), 'activo')");
                        $stmt->bind_param("i", $idUsuario);
                        $stmt->execute();
                        $id_carrinho = $stmt->insert_id;
                    }

                    // --- Lógica de Migração de Carrinho Otimizada ---
                    if (isset($_COOKIE['carrinho'])) {
                        $carrinhoCookie = json_decode(urldecode($_COOKIE['carrinho']), true);

                        if (is_array($carrinhoCookie)) {
                            foreach ($carrinhoCookie as $item) {
                                $id_produto = (int)($item['id_produto'] ?? 0);
                                $quantidade = (int)($item['quantidade'] ?? 0);
                                $subtotal = (float)($item['subtotal'] ?? 0);
                                $id_tipo_item_carrinho = (int)($item['id_tipo_item_carrinho'] ?? 1);
                                $detalhes_personalizacao = $item['detalhes_personalizacao'] ?? "Sem personalizações adicionais.";

                                // Inserir item no novo carrinho do usuário logado
                                if ($id_produto > 0 && $quantidade > 0) {
                                    $stmt_item = $conexao->prepare("
                                        INSERT INTO item_carrinho
                                        (id_carrinho, id_produto, quantidade, subtotal, id_tipo_item_carrinho, detalhes_personalizacao)
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ");
                                    $stmt_item->bind_param("iiidss", $id_carrinho, $id_produto, $quantidade, $subtotal, $id_tipo_item_carrinho, $detalhes_personalizacao);
                                    $stmt_item->execute();
                                    $id_item_carrinho = $stmt_item->insert_id;

                                    // Se o item é personalizado, migra os ingredientes
                                    if ($id_tipo_item_carrinho == 2) {
                                        $reduzidos = $item['ingredientes_reduzidos'] ?? [];
                                        $incrementos = $item['ingredientes_incrementados'] ?? [];
                                        
                                        // Migra os ingredientes incrementados, um por vez, com base na quantidade
                                        foreach ($incrementos as $ing) {
                                            if (isset($ing['id_ingrediente'])) {
                                                // Loop para inserir a quantidade correta de ingredientes
                                                for ($i = 0; $i < ($ing['qtd'] ?? 1); $i++) {
                                                    $stmt_ing = $conexao->prepare("INSERT INTO carrinho_ingrediente (id_item_carrinho, id_ingrediente, tipo, preco) VALUES (?, ?, ?, ?)");
                                                    $tipo = 'extra';
                                                    $preco_ing = (float)($ing['preco'] ?? 0);
                                                    $stmt_ing->bind_param("iisd", $id_item_carrinho, $ing['id_ingrediente'], $tipo, $preco_ing);
                                                    $stmt_ing->execute();
                                                }
                                            }
                                        }

                                        // Migra os ingredientes reduzidos, um por vez, com base na quantidade
                                        foreach ($reduzidos as $ing) {
                                            if (isset($ing['id_ingrediente'])) {
                                                // Loop para inserir a quantidade correta de ingredientes
                                                for ($i = 0; $i < ($ing['qtd'] ?? 1); $i++) {
                                                    $stmt_ing = $conexao->prepare("INSERT INTO carrinho_ingrediente (id_item_carrinho, id_ingrediente, tipo, preco) VALUES (?, ?, ?, ?)");
                                                    $tipo = 'removido';
                                                    $preco_ing = 0.00; // Preço para itens removidos é zero
                                                    $stmt_ing->bind_param("iisd", $id_item_carrinho, $ing['id_ingrediente'], $tipo, $preco_ing);
                                                    $stmt_ing->execute();
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        // Limpa o cookie do carrinho após a migração
                        setcookie('carrinho', '', time() - 3600, "/");
                    }

                    // Se tudo correu bem, comita as alterações
                    $conexao->commit();

                } catch (Exception $e) {
                    // Se ocorrer um erro, reverte todas as operações do banco de dados
                    $conexao->rollback();
                    // Opcional: registrar o erro
                    // error_log("Erro de migração de carrinho: " . $e->getMessage());
                    // Não exiba o erro ao usuário, apenas redirecione ou mostre uma mensagem genérica
                }

                // Redirecionamentos (mantidos do seu código)
                if (isset($_SESSION['url_destino'])) {
                    $urlDestino = $_SESSION['url_destino'];
                    unset($_SESSION['url_destino']);
                    header("Location: $urlDestino");
                    exit;
                }

                if ((int)$usuario['idperfil'] == 1) {
                    header("Location: dashboard.php");
                } else {
                    header("Location: cardapio.php");
                }
                exit;

            } else {
                 $erro = "Senha incorreta. ";
        // Exibe link para reset de senha apenas se o e-mail existir
        if (!empty($usuario['email'])) {
            $link_reset = "public/reset_password.php?email=" . urlencode($usuario['email']);
            $erro .= "<a href='$link_reset'>Esqueceu a senha?</a>";
        }
            }
        } else {
            $erro = "Usuário não encontrado.";
        }
    } else {
        $erro = "Preencha todos os campos.";
    }
}
?>

<!-- Formulário de Login -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" 
      content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Login</title>
       <link rel="stylesheet" href="css/admin.css">
       <script src="js/mostrarSenha.js"></script>
       <script src="js/darkmode1.js"></script>
</head>
<body>
    
  
<form method="POST" 
      style="max-width: 600px; margin: 0 auto; text-align: center; margin-top:50px; animation: fadeIn 1s ease-in;" 
      action="login.php<?= isset($_GET['redir']) ? '?redir=' . urlencode($_GET['redir']) : '' ?>">

  <h3>Login</h3>

  <img id="logotipo" src="icones/logo.png" alt="Logo" style="display:block; margin: 10px auto; max-width:150px;" >

  <label style="text-align:justify;">Usuário:</label>
  <input type="text" name="entrada" placeholder="nome, email ou número" required><br>

  <label for="senha" style="display: block; text-align: left; margin-top: 10px;">Senha:</label>
<div style="position: relative; display: flex; align-items: center; justify-content: center;">
  <input type="password" name="senha" class="campo-senha" required
         style="width: 100%; padding-right: 35px; box-sizing: border-box; ">
  <img src="icones/olho_fechado1.png"
       alt="Mostrar senha"
       class="toggle-senha"
       data-target="campo-senha"
       style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
</div>

  <br><br>

  <button type="submit">Entrar</button>

  <label style="text-align: center;">
    Não tem conta? <a href="cadastro.php"> Clique aqui</a>
  </label>

  <?php 
  if (!empty($erro)) { 
      echo "<p class='mensagem error' style='align-itens:center;'>{$erro}</p>"; 
  } 
  ?>
</form>



</body>
</html>
