<?php
// Inclui os arquivos de conexão e validação de login
include "conexao.php";
require_once "require_login.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
// Este bloco é o primeiro a ser executado para evitar que o HTML completo
// seja renderizado na resposta AJAX.
if (isset($_GET['ajax']) && $_GET['ajax'] === 'categorias2') {
    $id_categoriadoingrediente = $_GET['categoriadoingrediente'] ?? null;

    if (!$id_categoriadoingrediente) {
        exit;
    }

    // Consulta SQL para buscar os ingredientes da categoria
    $sql = "SELECT 
                i.id_ingrediente, 
                i.nome_ingrediente,
                i.preco_adicional,
                i.quantidade_estoque,
                i.disponibilidade, 
                iim.caminho_imagem,
                i.descricao
            FROM ingrediente i
            JOIN categoriadoingrediente_ingrediente cii ON i.id_ingrediente = cii.id_ingrediente
            LEFT JOIN ingrediente_imagem iim ON i.id_ingrediente = iim.id_ingrediente AND iim.imagem_principal = 1
            WHERE cii.id_categoriadoingrediente = ?
            ORDER BY i.id_ingrediente DESC";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $id_categoriadoingrediente);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $stmt->close();

    echo "<div class='ingredientes-container' id='c_ingrediente'>";
    if ($resultado->num_rows > 0) {
        while ($ingrediente = $resultado->fetch_assoc()) {
            $imagem = !empty($ingrediente['caminho_imagem']) ? htmlspecialchars($ingrediente['caminho_imagem']) : 'uploads/sem_imagem.png';
            $descricao = htmlspecialchars($ingrediente['descricao'] ?? '');
            
            echo "<div class='ingrediente-card' data-tooltip='{$descricao}' data-preco-base='{$ingrediente['preco_adicional']}'>";
            echo "       <img src='{$imagem}' alt='Imagem do Ingrediente'>";
            echo "       <div class='ingrediente-info'>";
            echo "          <div class='ingrediente-nome'>{$ingrediente['nome_ingrediente']}</div>";
            echo "          <label>Preço adicional:</label><div class='preco-total'>+ " . number_format(0, 2, ',', '.') . " MZN</div>";
            echo "       </div>";
            echo "       <div class='controles-quantidade'>";
            echo "          <button type='button' class='menos'>-</button>";
            echo "          <input type='number' name='ingredientes[{$ingrediente['id_ingrediente']}]' class='quantidade' value='0' min='0' readonly>";
            echo "          <button type='button' class='mais'>+</button>";
            echo "       </div>";
            echo "</div>";
        }
    } else {
        echo "<p>Nenhum ingrediente encontrado nesta categoria.</p>";
    }
    echo "</div>";

    exit;
}

// Inclui as informações do usuário e o restante do código HTML após o bloco AJAX
include "usuario_info.php"; 

$mensagem = "";
$redirecionar = false;

// Lógica de processamento do formulário POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Inicia a transação
    $conexao->begin_transaction();
    try {
        $nome = $_POST['nome'];
        $descricao = $_POST['descricao'];
        $preco = $_POST['preco'];
        $categorias = $_POST['categorias'] ?? []; // Mantém o array
        $id_categoriadoingrediente = filter_var($_POST['categoriadoingrediente'], FILTER_VALIDATE_INT);
        $ingredientes = $_POST['ingredientes'] ?? [];

        // Verifica se o produto já existe
        $stmt = $conexao->prepare("SELECT id_produto FROM produto WHERE nome_produto = ?");
        $stmt->bind_param("s", $nome);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows > 0) {
            $mensagem = "Já existe um produto com esse nome.";
            $conexao->rollback(); 
        } else {
            // Insere o novo produto
            $stmt = $conexao->prepare("INSERT INTO produto (nome_produto, descricao, preco) 
                                         VALUES (?, ?, ?)");
            $stmt->bind_param("ssd", $nome, $descricao, $preco);
            $stmt->execute();
            $id_produto = $conexao->insert_id;
            $stmt->close();

            // Insere as categorias usando um loop para todas as selecionadas
            if (!empty($categorias)) {
                $stmt_cat = $conexao->prepare("INSERT INTO produto_categoria (id_produto, id_categoria) VALUES (?, ?)");
                foreach ($categorias as $id_categoria) {
                    $stmt_cat->bind_param("ii", $id_produto, $id_categoria);
                    $stmt_cat->execute();
                }
                $stmt_cat->close();
            }

            // Associa o produto aos ingredientes selecionados, com a quantidade
            $insere_ing = $conexao->prepare("INSERT INTO produto_ingrediente (id_produto, id_ingrediente, quantidade_ingrediente) VALUES (?, ?, ?)");
            foreach ($ingredientes as $id_ingrediente => $qtd_ingrediente) {
                if ($qtd_ingrediente > 0) {
                    $insere_ing->bind_param("iii", $id_produto, $id_ingrediente, $qtd_ingrediente);
                    $insere_ing->execute();
                }
            }
            $insere_ing->close();
            
            // Upload das imagens
            $stmt_img = $conexao->prepare("INSERT INTO produto_imagem (id_produto, caminho_imagem, legenda, imagem_principal)
                                            VALUES (?, ?, ?, ?)");
            foreach ($_FILES['imagens']['tmp_name'] as $index => $tmp_name) {
                if (!empty($tmp_name)) {
                    $nome_arquivo = basename($_FILES['imagens']['name'][$index]);
                    $destino = "uploads/" . time() . "_" . $nome_arquivo;

                    if (move_uploaded_file($tmp_name, $destino)) {
                        $legenda = $_POST['legenda'][$index] ?? '';
                        $imagem_principal = (isset($_POST['imagem_principal']) && $_POST['imagem_principal'] == $index) ? 1 : 0;
                        $stmt_img->bind_param("issi", $id_produto, $destino, $legenda, $imagem_principal);
                        $stmt_img->execute();
                    }
                }
            }
            $stmt_img->close();

            // Associa cada categoria de produto com a categoria de ingrediente selecionada
            if (!empty($categorias) && $id_categoriadoingrediente) {
                // Prepara as consultas SQL fora do loop
                $stmt_check_assoc = $conexao->prepare("SELECT COUNT(*) FROM categoria_produto_ingrediente WHERE id_categoria = ? AND id_categoriadoingrediente = ?");
                $stmt_assoc = $conexao->prepare("INSERT INTO categoria_produto_ingrediente (id_categoria, id_categoriadoingrediente) VALUES (?, ?)");

                // Itera sobre CADA categoria no array
                foreach ($categorias as $id_categoria) {
                    // Vincula e executa a verificação
                    $stmt_check_assoc->bind_param("ii", $id_categoria, $id_categoriadoingrediente);
                    $stmt_check_assoc->execute();
                    $result_check = $stmt_check_assoc->get_result()->fetch_row()[0];

                    // Apenas insere se a associação não existir
                    if ($result_check == 0) {
                        $stmt_assoc->bind_param("ii", $id_categoria, $id_categoriadoingrediente);
                        $stmt_assoc->execute();
                    }
                }
                
                // Fecha as consultas preparadas após o loop
                $stmt_check_assoc->close();
                $stmt_assoc->close();
            }

            $conexao->commit();
            $mensagem = "✅Produto cadastrado com sucesso!";
            $redirecionar = true;
        }
    } catch (Exception $e) {
        $conexao->rollback();
        error_log("Erro no cadastro de produto: " . $e->getMessage());
        $mensagem = "Ocorreu um erro ao cadastrar o produto. Por favor, tente novamente.";
    }
}
?>


<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Cadastro de Produto</title>
    
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
        <a href="ver_pratos.php">Voltar ás Refeições</a>
        
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

        <?php if ($mensagem): ?>
        <div class="mensagem <?= str_contains($mensagem, '✅') ? 'success' : 'error' ?>">
            <?= $mensagem ?>
        </div>
        <?php endif; ?>

        <h2>Cadastrar Produto</h2>
        <form method="post" enctype="multipart/form-data">
            <label>Nome:</label><input type="text" name="nome" required><br>
            <label>Descrição:</label><textarea name="descricao" required placeholder="Caracterize o Produto por completo"></textarea><br>
            <label>Preço:</label><input type="number" step="0.01" name="preco" required><br>
            
          <div class="form-group">
    <label>Categoria da refeição:</label>
    <div class="checkbox-group">
        <?php
        // Supondo que a variável $cat já foi definida e contém o resultado da sua consulta
                    $cat = $conexao->query("SELECT * FROM categoria");
        if ($cat->num_rows > 0) {
            while ($c = $cat->fetch_assoc()) {
                echo "<div class='categoria-item'>";
                echo "<input type='checkbox' id='categoria_{$c['id_categoria']}' name='categorias[]' value='{$c['id_categoria']}'>";
                echo "<label for='categoria_{$c['id_categoria']}'>" . htmlspecialchars($c['nome_categoria']) . "</label>";
                echo "</div>";
            }
        } else {
            echo "<p>Nenhuma categoria disponível.</p>";
        }
        ?>
    </div>
</div>
            <h4>Imagens do Produto</h4>
            <div id="imagens-container"></div>
            <button type="button" onclick="adicionarCampoImagem()">+ Adicionar Imagem</button><br><br>

            <label>Categoria do ingrediente por Associar:</label>
            <select name="categoriadoingrediente" id="categoriadoingrediente" onchange="carregarCategorias()" required>
                <option value="">Selecione</option>
                <?php
                $categoria_ingrediente = $conexao->query("SELECT * FROM categoriadoingrediente");
                while ($ci = $categoria_ingrediente->fetch_assoc()) {
                    echo "<option value='{$ci['id_categoriadoingrediente']}'>{$ci['nome_categoriadoingrediente']}</option>";
                }
                ?>
            </select><br><br>
            
            <div id="ingredientes-container">
                <!-- Os ingredientes associados aparecerão aqui via AJAX -->
            </div>

            <br>
            <button class="cadastrar" type="submit">Cadastrar Produto</button>
        </form>
    </div>
    
    <?php if ($redirecionar): ?>
    <script>
        // Redireciona em 3 segundos
        setTimeout(() => {
            window.location.href = 'ver_pratos.php';
        }, 3000);
    </script>
    <?php endif; ?>

    <script>
        function adicionarCampoImagem() {
            const container = document.getElementById('imagens-container');
            const index = container.children.length;
            const div = document.createElement('div');
            div.innerHTML = `
                <input type="file" name="imagens[]" required>
                <input type="text" name="legenda[]" placeholder="Legenda da imagem">
                <label>
                    Principal?
                    <input type="radio" name="imagem_principal" value="${index}">
                </label>
                <br><br>
            `;
            container.appendChild(div);
        }

        // Função principal para carregar categorias via AJAX e configurar os controles de quantidade
        function carregarCategorias() {
            const categoriadoingrediente = document.getElementById("categoriadoingrediente").value;
            if (!categoriadoingrediente) {
                document.getElementById("ingredientes-container").innerHTML = '';
                return;
            }

            fetch(`?ajax=categorias2&categoriadoingrediente=${categoriadoingrediente}`)
                .then(res => res.text())
                .then(data => {
                    document.getElementById("ingredientes-container").innerHTML = data;
                    setupQuantityControls();
                })
                .catch(error => {
                    console.error("Erro ao carregar ingredientes:", error);
                    const container = document.getElementById("ingredientes-container");
                    container.innerHTML = "<p style='color:red;'>Erro ao carregar ingredientes. Por favor, tente novamente.</p>";
                });
        }
        
        // Função para configurar os botões de quantidade e cálculo dinâmico
        function setupQuantityControls() {
            document.querySelectorAll(".ingrediente-card").forEach(card => {
                const btnMais = card.querySelector(".mais");
                const btnMenos = card.querySelector(".menos");
                const inputQtd = card.querySelector(".quantidade");
                const precoTotalElement = card.querySelector(".preco-total");
                const precoBase = parseFloat(card.dataset.precoBase);

                // Função interna para atualizar o preço total do ingrediente
                const atualizarPreco = () => {
                    const quantidade = parseInt(inputQtd.value);
                    const precoTotal = quantidade * precoBase;
                    precoTotalElement.textContent = `+ ${precoTotal.toLocaleString('pt-MZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} MZN`;
                };

                btnMais.addEventListener("click", () => {
                    inputQtd.value = parseInt(inputQtd.value) + 1;
                    atualizarPreco();
                });

                btnMenos.addEventListener("click", () => {
                    let val = parseInt(inputQtd.value) - 1;
                    if (val < 0) val = 0;
                    inputQtd.value = val;
                    atualizarPreco();
                });

                atualizarPreco();
            });
        }
    </script>
</body>
</html>
