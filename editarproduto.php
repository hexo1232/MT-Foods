<?php
include "conexao.php";
require_once "require_login.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// A verificação abaixo garante que a página não seja carregada sem um ID de produto.
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "Produto inválido.";
    exit;
}

$id_produto = intval($_GET['id']);
$mensagem = "";
$houveAlteracao = false;

// 🆕 Buscar o ID da categoria 'Promoções da Semana' para usar na lógica condicional
$id_promocao = null;
$stmt_promocao = $conexao->prepare("SELECT id_categoria FROM categoria WHERE nome_categoria = ?");
$nome_promocao = "Promoções da Semana";
if ($stmt_promocao) {
    $stmt_promocao->bind_param("s", $nome_promocao);
    $stmt_promocao->execute();
    $res_promocao = $stmt_promocao->get_result();
    if ($row = $res_promocao->fetch_assoc()) {
        $id_promocao = $row['id_categoria'];
    }
    $stmt_promocao->close();
}

// 🔄 AJAX: Carregar ingredientes por categoria e quantidade associada
if (isset($_GET['ajax']) && $_GET['ajax'] === 'categorias2') {
    $id_categoriadoingrediente = $_GET['categoriadoingrediente'] ?? null;
    $id_produto_ajax = $_GET['id'] ?? null;

    if (!$id_categoriadoingrediente || !$id_produto_ajax) {
        exit;
    }

    // Consulta SQL para buscar os ingredientes e suas quantidades associadas
    $sql = "SELECT 
                i.id_ingrediente, 
                i.nome_ingrediente,
                i.preco_adicional,
                i.quantidade_estoque,
                i.disponibilidade, 
                iim.caminho_imagem,
                i.descricao,
                pi.quantidade_ingrediente
            FROM ingrediente i
            JOIN categoriadoingrediente_ingrediente cii ON i.id_ingrediente = cii.id_ingrediente
            LEFT JOIN ingrediente_imagem iim ON i.id_ingrediente = iim.id_ingrediente AND iim.imagem_principal = 1
            LEFT JOIN produto_ingrediente pi ON i.id_ingrediente = pi.id_ingrediente AND pi.id_produto = ?
            WHERE cii.id_categoriadoingrediente = ?
            ORDER BY i.id_ingrediente DESC";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("ii", $id_produto_ajax, $id_categoriadoingrediente);
    $stmt->execute();
    $resultado = $stmt->get_result();

    echo "<div class='ingredientes-container' id='c_ingrediente'>";
    if ($resultado->num_rows > 0) {
        while ($ingrediente = $resultado->fetch_assoc()) {
            $imagem = !empty($ingrediente['caminho_imagem']) ? htmlspecialchars($ingrediente['caminho_imagem']) : 'uploads/sem_imagem.png';
            $descricao = htmlspecialchars($ingrediente['descricao'] ?? '');
            
            // Define a quantidade inicial para o ingrediente
            $qtdInicial = $ingrediente['quantidade_ingrediente'] ?? 0;
            
            echo "<div class='ingrediente-card' data-tooltip='{$descricao}' data-preco-base='{$ingrediente['preco_adicional']}'>";
            echo "           <img src='{$imagem}' alt='Imagem do Ingrediente'>";
            echo "           <div class='ingrediente-info'>";
            echo "              <div class='ingrediente-nome'>{$ingrediente['nome_ingrediente']}</div>";
            // O preço inicial é calculado com base na quantidade já existente
            echo "              <label>Preço Adicional:</label><div class='preco-total'>+ " . number_format($qtdInicial * $ingrediente['preco_adicional'], 2, ',', '.') . " MZN</div>";
            echo "           </div>";
            echo "           <div class='controles-quantidade'>";
            echo "              <button type='button' class='menos'>-</button>";
            echo "              <input type='number' name='ingredientes[{$ingrediente['id_ingrediente']}]' class='quantidade' value='{$qtdInicial}' min='0' readonly>";
            echo "              <button type='button' class='mais'>+</button>";
            echo "           </div>";
            echo "</div>";
        }
    } else {
        echo "<p>Nenhum ingrediente encontrado nesta categoria.</p>";
    }
    echo "</div>";

    exit;
}

include "usuario_info.php";
$redirecionar = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Inicia a transação
    $conexao->begin_transaction();
    try {
        $nome = $_POST['nome'];
        $descricao = $_POST['descricao'];
        $preco = $_POST['preco'];
        $categorias_selecionadas = $_POST['categorias'] ?? [];
        $ingredientes = $_POST['ingredientes'] ?? [];
        $id_categoriadoingrediente = filter_var($_POST['categoriadoingrediente'], FILTER_VALIDATE_INT);
        
        // ---------------------------------------------------------
        // 🔧 CORREÇÃO DO ERRO "CANNOT BE NULL"
        // ---------------------------------------------------------
        // O banco de dados exige um valor DECIMAL, não aceita NULL.
        // Definimos 0.00 como padrão se não houver promoção.
        $preco_promocional = 0.00; 

        // Verifica se a categoria de promoção está marcada
        $is_promo_ativa = ($id_promocao && in_array($id_promocao, $categorias_selecionadas));

        if ($is_promo_ativa) {
            // Se ativa, verifica se o usuário digitou algo
            $valor_input = trim($_POST['preco_promocional'] ?? '');
            if ($valor_input !== '') {
                $preco_promocional = floatval($valor_input);
            }
        }
        // Se a categoria NÃO estiver marcada, $preco_promocional continua sendo 0.00

        // Verifica duplicidade de nome (exceto o próprio)
        $verifica = $conexao->prepare("SELECT COUNT(*) FROM produto WHERE nome_produto = ? AND id_produto != ?");
        $verifica->bind_param("si", $nome, $id_produto);
        $verifica->execute();
        $verifica->bind_result($existe);
        $verifica->fetch();
        $verifica->close();

        if ($existe > 0) {
            $mensagem = "Já existe um produto com esse nome.";
            // Cancela a transação se o produto já existe
            $conexao->rollback();
        } else {
            // Atualiza os dados principais do produto
            $stmt = $conexao->prepare("UPDATE produto SET nome_produto=?, descricao=?, preco=?, preco_promocional=? WHERE id_produto=?");
            $stmt->bind_param("ssdsi", $nome, $descricao, $preco, $preco_promocional, $id_produto);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $houveAlteracao = true;
            }
            $stmt->close();

            // Lógica de atualização das categorias: delete e insert
            $conexao->query("DELETE FROM produto_categoria WHERE id_produto = $id_produto");
            $insere_cat = $conexao->prepare("INSERT INTO produto_categoria (id_produto, id_categoria) VALUES (?, ?)");
            foreach ($categorias_selecionadas as $id_categoria_selecionada) {
                $insere_cat->bind_param("ii", $id_produto, $id_categoria_selecionada);
                $insere_cat->execute();
            }
            $insere_cat->close();
            
            // Lógica de atualização dos ingredientes: delete e insert
            $conexao->query("DELETE FROM produto_ingrediente WHERE id_produto = $id_produto");

            $insere_ing = $conexao->prepare("INSERT INTO produto_ingrediente (id_produto, id_ingrediente, quantidade_ingrediente) VALUES (?, ?, ?)");
            foreach ($ingredientes as $id_ingrediente => $qtd_ingrediente) {
                if ($qtd_ingrediente > 0) {
                    $insere_ing->bind_param("iii", $id_produto, $id_ingrediente, $qtd_ingrediente);
                    $insere_ing->execute();
                }
            }
            $insere_ing->close();

            // Sincroniza as associações de categoria de produto com a categoria de ingrediente.
            if (!empty($categorias_selecionadas) && $id_categoriadoingrediente) {
                // Remove todas as associações existentes
                $stmt_delete = $conexao->prepare("DELETE FROM categoria_produto_ingrediente WHERE id_categoriadoingrediente = ?");
                $stmt_delete->bind_param("i", $id_categoriadoingrediente);
                $stmt_delete->execute();
                $stmt_delete->close();
                
                // Insere as novas associações
                $stmt_assoc = $conexao->prepare("INSERT INTO categoria_produto_ingrediente (id_categoria, id_categoriadoingrediente) VALUES (?, ?)");
                foreach ($categorias_selecionadas as $id_categoria) {
                    $stmt_assoc->bind_param("ii", $id_categoria, $id_categoriadoingrediente);
                    $stmt_assoc->execute();
                }
                $stmt_assoc->close();
            }
            
            // ---------------------------------------------------------
            // 🔧 Lógica da Imagem Principal
            // ---------------------------------------------------------
            
            // 1. Verifica se JÁ existe uma imagem principal no banco para este produto
            $check_main = $conexao->query("SELECT COUNT(*) as qtd FROM produto_imagem WHERE id_produto = $id_produto AND imagem_principal = 1");
            $row_main = $check_main->fetch_assoc();
            $tem_principal_no_banco = ($row_main['qtd'] > 0);

            // 2. Verifica se o usuário alterou a principal via Radio Button (Imagens existentes ou novas)
            $radio_selecionado = $_POST['imagem_principal'] ?? null;

            // Se for um ID numérico (imagem existente), atualiza
            if ($radio_selecionado && is_numeric($radio_selecionado)) {
                $conexao->query("UPDATE produto_imagem SET imagem_principal = 0 WHERE id_produto = $id_produto");
                $conexao->query("UPDATE produto_imagem SET imagem_principal = 1 WHERE id_imagem = " . intval($radio_selecionado));
                $tem_principal_no_banco = true;
            }

            // 3. Processa Upload de Novas Imagens
            if (isset($_FILES['imagens']) && is_array($_FILES['imagens']['tmp_name'])) {
                $stmt_img = $conexao->prepare("INSERT INTO produto_imagem (id_produto, caminho_imagem, legenda, imagem_principal) VALUES (?, ?, ?, ?)");
                
                foreach ($_FILES['imagens']['tmp_name'] as $index => $tmp_name) {
                    if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                        $nome_arquivo = basename($_FILES['imagens']['name'][$index]);
                        $destino = "uploads/" . time() . "_" . $nome_arquivo;

                        if (move_uploaded_file($tmp_name, $destino)) {
                            $legenda = $_POST['legenda'][$index] ?? '';
                            $imagem_principal = 0;

                            // Se o usuário selecionou ESTA nova imagem no radio button
                            if ($radio_selecionado === "nova_imagem_" . $index) {
                                $conexao->query("UPDATE produto_imagem SET imagem_principal = 0 WHERE id_produto = $id_produto");
                                $imagem_principal = 1;
                                $tem_principal_no_banco = true;
                            }
                            // OU: Se não há nenhuma principal definida ainda, a primeira que sobe vira principal
                            elseif (!$tem_principal_no_banco) {
                                $imagem_principal = 1;
                                $tem_principal_no_banco = true;
                            }

                            $stmt_img->bind_param("issi", $id_produto, $destino, $legenda, $imagem_principal);
                            $stmt_img->execute();
                            $houveAlteracao = true;
                        }
                    }
                }
                $stmt_img->close();
            }

            // Atualiza legendas de imagens existentes
            if (isset($_POST['legenda_existente']) && is_array($_POST['legenda_existente'])) {
                $stmt_legenda = $conexao->prepare("UPDATE produto_imagem SET legenda = ? WHERE id_imagem = ?");
                foreach ($_POST['legenda_existente'] as $id_imagem_existente => $nova_legenda) {
                    $stmt_legenda->bind_param("si", $nova_legenda, $id_imagem_existente);
                    $stmt_legenda->execute();
                }
                $stmt_legenda->close();
            }

            // Finaliza a transação
            $conexao->commit();
            $mensagem = "✅Produto atualizado com sucesso!";
            $redirecionar = true;

        }
    } catch (Exception $e) {
        // Desfaz a transação em caso de erro
        $conexao->rollback();
        $mensagem = "Ocorreu um erro: " . $e->getMessage();
    }
}

// Lógica de remoção de imagem (fora do bloco POST principal)
if (isset($_GET['remover_imagem'])) {
    $conexao->begin_transaction();
    try {
        $id_imagem_remover = intval($_GET['remover_imagem']);
        
        $caminho_img_res = $conexao->query("SELECT caminho_imagem, imagem_principal FROM produto_imagem WHERE id_imagem = $id_imagem_remover");
        $dados_img = $caminho_img_res->fetch_assoc();

        if ($dados_img) {
            $caminho_img = $dados_img['caminho_imagem'];
            $era_principal = $dados_img['imagem_principal'];

            $conexao->query("DELETE FROM produto_imagem WHERE id_imagem = $id_imagem_remover");
            if (file_exists($caminho_img)) {
                unlink($caminho_img);
            }

            // SE removeu a principal, define outra como principal automaticamente
            if ($era_principal == 1) {
                $res_outra = $conexao->query("SELECT id_imagem FROM produto_imagem WHERE id_produto = $id_produto LIMIT 1");
                if ($row_outra = $res_outra->fetch_assoc()) {
                    $novo_id = $row_outra['id_imagem'];
                    $conexao->query("UPDATE produto_imagem SET imagem_principal = 1 WHERE id_imagem = $novo_id");
                }
            }

            $conexao->commit();
            header("Location: editarproduto.php?id=$id_produto&imagemRemovida=1");
            exit;
        }
    } catch (Exception $e) {
        $conexao->rollback();
        $mensagem = "Ocorreu um erro ao remover a imagem: " . $e->getMessage();
    }
}

// NOVIDADE: Adicionado preco_promocional na consulta
$stmt = $conexao->prepare("SELECT nome_produto, descricao, preco, preco_promocional FROM produto WHERE id_produto = ?");
$stmt->bind_param("i", $id_produto);
$stmt->execute();
$resultado = $stmt->get_result();
$produto = $resultado->fetch_assoc();

if (!$produto) {
    echo "Produto não encontrado.";
    exit;
}

$imagens = $conexao->query("SELECT * FROM produto_imagem WHERE id_produto = $id_produto");

// Nova lógica para pré-selecionar a categoria de ingredientes
$selected_ingrediente_cat = null;
$stmt_ing_cat = $conexao->prepare("SELECT cii.id_categoriadoingrediente FROM produto_ingrediente pi JOIN categoriadoingrediente_ingrediente cii ON pi.id_ingrediente = cii.id_ingrediente WHERE pi.id_produto = ? LIMIT 1");
$stmt_ing_cat->bind_param("i", $id_produto);
$stmt_ing_cat->execute();
$res_ing_cat = $stmt_ing_cat->get_result();
if ($row_ing_cat = $res_ing_cat->fetch_assoc()) {
    $selected_ingrediente_cat = $row_ing_cat['id_categoriadoingrediente'];
}
$stmt_ing_cat->close();

// Buscar categorias associadas ao produto para pré-seleção
$categorias_associadas = [];
$cat_ass_res = $conexao->query("SELECT id_categoria FROM produto_categoria WHERE id_produto = $id_produto");
if ($cat_ass_res) {
    while ($row = $cat_ass_res->fetch_assoc()) {
        $categorias_associadas[] = $row['id_categoria'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Editar Produto</title>
        <script src="logout_auto.js"></script>

    <link rel="stylesheet" href="css/admin.css">
          <script src="js/darkmode2.js"></script>
             <script src="js/sidebar.js"></script>
             <script src="js/dropdown2.js"></script>
</head>
<body>
    
<button class="menu-btn">☰</button>

<div class="sidebar-overlay"></div>
    
<sidebar class="sidebar">
      
          <br><br>
        
            <a href="ver_pratos.php">Voltar ás Refeições</a>
       <div class="sidebar-user-wrapper">

    <div class="sidebar-user" id="usuarioDropdown">

        <div class="usuario-avatar" style="background-color: <?= $corAvatar ?>;">
            <?= $iniciais ?>
        </div>

        <div class="usuario-dados">
            <div class="usuario-nome"><?= $nome ?></div>
            <div class="usuario-apelido"><?= $apelido ?></div>
        </div>

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

    <img class="dark-toggle" id="darkToggle"
         src="icones/lua.png"
         alt="Modo Escuro"
         title="Alternar modo escuro">
</div>


        </sidebar>
        <div class="conteudo">
    <?php if ($mensagem): ?>
        <div class="mensagem <?= str_contains($mensagem, '✅') || str_contains($mensagem, 'removida') ? 'success' : 'error' ?>">
            <?= $mensagem ?>
        </div>
    <?php endif; ?>
    <h2>Editar Produto</h2>
    <form method="post" enctype="multipart/form-data">
        <label>Nome:</label><input type="text" name="nome" value="<?= htmlspecialchars($produto['nome_produto']) ?>" required><br>
        <label>Descrição:</label><textarea name="descricao" required placeholder="Caracterize o Produto por completo"><?= htmlspecialchars($produto['descricao']) ?></textarea><br>
        <label>Preço:</label><input type="number" step="0.01" name="preco" value="<?= htmlspecialchars($produto['preco']) ?>" required><br>
        
        <div class="form-group">
            <label>Categoria da refeição:</label>
            <div class="checkbox-group">
                <?php
                $cat = $conexao->query("SELECT * FROM categoria");
                if ($cat) {
                    while ($c = $cat->fetch_assoc()) {
                        if (isset($c['id_categoria']) && isset($c['nome_categoria'])) {
                            $checked = in_array($c['id_categoria'], $categorias_associadas) ? 'checked' : '';
                            echo "<div class='categoria-item'>";
                            // Adicionando um ID à checkbox para o JavaScript
                            echo "<input type='checkbox' id='categoria_{$c['id_categoria']}' name='categorias[]' value='{$c['id_categoria']}' {$checked} data-categoria-id='{$c['id_categoria']}'>";
                            echo "<label for='categoria_{$c['id_categoria']}'>" . htmlspecialchars($c['nome_categoria']) . "</label>";
                            echo "</div>";
                        }
                    }
                }
                ?>
            </div>
        </div>
        
        <div id="campo-promocao" style="display: none;">
            <label>Preço Promocional:</label><input type="number" step="0.01" name="preco_promocional" value="<?= htmlspecialchars($produto['preco_promocional'] ?? '') ?>"><br>
        </div>

        <h4>Imagens do Produto</h4>
        <div id="imagens-container">
            <?php 
            // Garante que o ponteiro está no inicio
            $imagens->data_seek(0);
            while ($img = $imagens->fetch_assoc()): 
            ?>
            <div>
                <img src="<?= htmlspecialchars($img['caminho_imagem']) ?>" width="100">
                <input type="text" name="legenda_existente[<?= $img['id_imagem'] ?>]" value="<?= htmlspecialchars($img['legenda']) ?>">
                <a href="?id=<?= $id_produto ?>&remover_imagem=<?= $img['id_imagem'] ?>">Remover</a>
                <label>
                    Principal?
                    <input type="radio" name="imagem_principal" value="<?= $img['id_imagem'] ?>" <?= ($img['imagem_principal'] == 1) ? 'checked' : '' ?>>
                </label>
            </div>
            <?php endwhile; ?>
        </div>
        <button type="button" onclick="adicionarCampoImagem()">+ Adicionar Imagem</button><br><br>

        <label>Categoria do ingrediente por Associar:</label>
        <select name="categoriadoingrediente" id="categoriadoingrediente" onchange="carregarCategorias(<?= $id_produto ?>)">
            <option value="">Selecione</option>
            <?php
            $categoria_ingrediente = $conexao->query("SELECT * FROM categoriadoingrediente");
            while ($ci = $categoria_ingrediente->fetch_assoc()) {
                $selected = ($ci['id_categoriadoingrediente'] == $selected_ingrediente_cat) ? 'selected' : '';
                echo "<option value='{$ci['id_categoriadoingrediente']}' {$selected}>{$ci['nome_categoriadoingrediente']}</option>";
            }
            ?>
        </select><br><br>
        
        <div id="ingredientes-container">
            </div>
        
        <script>
            // ACIONA O CARREGAMENTO IMEDIATAMENTE APÓS O SELECT SER RENDERIZADO
            document.addEventListener('DOMContentLoaded', () => {
                const categoriadoingredienteElement = document.getElementById("categoriadoingrediente");
                if (categoriadoingredienteElement && categoriadoingredienteElement.value) {
                    carregarCategorias(<?= $id_produto ?>);
                }
                
                // 🆕 NOVIDADE: Lógica para o campo de preço promocional
                // Verifica se a categoria de promoção existe e pega o checkbox
                const idPromo = <?= json_encode($id_promocao) ?>;
                if (idPromo) {
                    const promoCheckbox = document.querySelector(`input[type="checkbox"][data-categoria-id="${idPromo}"]`);
                    const promoField = document.getElementById('campo-promocao');
                    
                    if (promoCheckbox && promoField) {
                        // Função para alternar a visibilidade
                        const togglePromoField = () => {
                            if (promoCheckbox.checked) {
                                promoField.style.display = 'block';
                            } else {
                                promoField.style.display = 'none';
                                // Opcional: limpar o valor quando desmarcar
                                const inputPromo = promoField.querySelector('input');
                                if(inputPromo) inputPromo.value = '';
                            }
                        };
                        // Executa na carga da página
                        togglePromoField();
                        // Adiciona o evento de mudança
                        promoCheckbox.addEventListener('change', togglePromoField);
                    }
                }
            });
        </script>

        <br>
        <button class="cadastrar" type="submit">Salvar Alterações</button>

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
        const index = container.children.length; // Conta quantos filhos para gerar indice único
        const div = document.createElement('div');
        
        // Define um valor único para o radio da nova imagem para identificar no PHP
        const radioValue = "nova_imagem_" + index;

        div.innerHTML = `
            <input type="file" name="imagens[]" required>
            <input type="text" name="legenda[]" placeholder="Legenda da imagem">
            <label>
                Principal?
                <input type="radio" name="imagem_principal" value="${radioValue}">
            </label>
            <br><br>
        `;
        container.appendChild(div);
    }

    // Função principal para carregar categorias via AJAX
    function carregarCategorias(id_produto) {
        const categoriadoingrediente = document.getElementById("categoriadoingrediente").value;
        if (!categoriadoingrediente) {
            document.getElementById("ingredientes-container").innerHTML = '';
            return;
        }
        
        const url = `?ajax=categorias2&categoriadoingrediente=${categoriadoingrediente}&id=${id_produto}`;

        fetch(url)
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
