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
// ⚠️ CORREÇÃO: Usar um bind_param para evitar problemas de codificação ou SQL Injection
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

    // Consulta SQL para buscar os ingredientes e suas quantidades associadas (se existirem)
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
        
        // 🚀 CORREÇÃO PRINCIPAL: Lógica para o preço promocional
        $preco_promocional = null; // Inicializa como NULL
        
        // Verifica se a categoria de promoção está selecionada E se o campo promocional foi preenchido
        $is_promocao_selecionada = ($id_promocao && in_array($id_promocao, $categorias_selecionadas));
        
        if ($is_promocao_selecionada) {
            // Se estiver em promoção, tenta obter o valor. Se for vazio, define como NULL.
            $valor_promocional = trim($_POST['preco_promocional'] ?? '');
            if (!empty($valor_promocional)) {
                $preco_promocional = floatval($valor_promocional);
            } else {
                // Se a promoção está selecionada mas o campo está vazio,
                // definimos como NULL (não há preço promocional definido).
                $preco_promocional = null;
            }
        } else {
            // Se a categoria de promoção não estiver selecionada, o preço promocional deve ser NULL.
            $preco_promocional = null;
        }


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
            // 🆕 Atualiza os dados principais do produto
            // ⚠️ Ajuste: o tipo de dado do $preco_promocional deve ser 'd' (double/float) e não 's' (string),
            // mas o bind_param pode lidar com NULLs se o MySQL aceitar o NULL para DECIMAL.
            // Para garantir que NULL seja enviado, usaremos a função `bind_param` diretamente abaixo.
            
            $stmt = $conexao->prepare("UPDATE produto SET nome_produto=?, descricao=?, preco=?, preco_promocional=? WHERE id_produto=?");
            
            // Para lidar com o NULL do preço promocional, usamos a referência direta do PHP 
            // e garantimos que o MySQL entenda o NULL para o tipo DECIMAL.
            // Aqui estamos usando a variável $preco_promocional que já foi tratada acima.
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

            // Sincroniza as associações de categoria de produto com a categoria de ingrediente. (MANTIDO)
            if (!empty($categorias_selecionadas) && $id_categoriadoingrediente) {
                // ... (seu código de sincronização de categoria de ingrediente)
                $stmt_delete = $conexao->prepare("DELETE FROM categoria_produto_ingrediente WHERE id_categoriadoingrediente = ?");
                if ($stmt_delete === false) {
                    throw new Exception("Falha ao preparar a declaração de exclusão: " . $conexao->error);
                }
                $stmt_delete->bind_param("i", $id_categoriadoingrediente);
                $stmt_delete->execute();
                $stmt_delete->close();
                
                $stmt_assoc = $conexao->prepare("INSERT INTO categoria_produto_ingrediente (id_categoria, id_categoriadoingrediente) VALUES (?, ?)");
                if ($stmt_assoc === false) {
                    throw new Exception("Falha ao preparar a declaração de inserção: " . $conexao->error);
                }
                foreach ($categorias_selecionadas as $id_categoria) {
                    $stmt_assoc->bind_param("ii", $id_categoria, $id_categoriadoingrediente);
                    $stmt_assoc->execute();
                }
                $stmt_assoc->close();
            }
            
            // Lógica de atualização de imagens
            
            // 1. Verifica se já existe uma imagem principal
            $res_principal_existente = $conexao->query("SELECT COUNT(*) as count FROM produto_imagem WHERE id_produto = $id_produto AND imagem_principal = 1");
            $existe_principal = $res_principal_existente->fetch_assoc()['count'] > 0;
            
            // 2. Atualiza imagem principal selecionada pelo RÁDIO BUTTON
            if (isset($_POST['imagem_principal']) && $_POST['imagem_principal'] !== 'nova_imagem_0') {
                $img_principal = intval($_POST['imagem_principal']);
                $resAtual = $conexao->query("SELECT id_imagem FROM produto_imagem WHERE id_produto = $id_produto AND imagem_principal = 1");
                $atual = $resAtual->fetch_assoc();

                if (!$atual || $atual['id_imagem'] != $img_principal) {
                    $conexao->query("UPDATE produto_imagem SET imagem_principal = 0 WHERE id_produto = $id_produto");
                    $conexao->query("UPDATE produto_imagem SET imagem_principal = 1 WHERE id_imagem = $img_principal");
                    $houveAlteracao = true;
                    // Marca que uma principal já foi definida
                    $existe_principal = true; 
                }
            } else {
                // Se a seleção do rádio for uma nova imagem (que será tratada abaixo)
                // ou se a seleção for uma imagem existente que não mudou, mantemos a lógica anterior.
                // Aqui não fazemos nada, apenas deixamos a variável `existe_principal` no estado em que está.
            }

            // 3. Adiciona novas imagens
            $primeira_nova_imagem_id = null;
            $stmt_img = $conexao->prepare("INSERT INTO produto_imagem (id_produto, caminho_imagem, legenda, imagem_principal) VALUES (?, ?, ?, ?)");
            if (isset($_FILES['imagens']) && is_array($_FILES['imagens']['tmp_name'])) {
                foreach ($_FILES['imagens']['tmp_name'] as $index => $tmp_name) {
                    if (!empty($tmp_name) && is_uploaded_file($tmp_name)) {
                        $nome_arquivo = basename($_FILES['imagens']['name'][$index]);
                        $destino = "uploads/" . time() . "_" . $nome_arquivo;

                        if (move_uploaded_file($tmp_name, $destino)) {
                            $legenda = $_POST['legenda'][$index] ?? '';
                            $imagem_principal = 0;

                            // 🎯 CORREÇÃO DA IMAGEM PRINCIPAL: Se NENHUMA imagem principal existir (nova ou antiga), 
                            // a primeira imagem enviada se torna a principal.
                            if (!$existe_principal && $index === 0) {
                                $imagem_principal = 1;
                                $existe_principal = true; // Marca como definida
                            }

                            // Verifica se o rádio button de uma das novas imagens foi marcado
                            if (isset($_POST['imagem_principal']) && $_POST['imagem_principal'] == "nova_imagem_$index") {
                                // Limpa qualquer principal existente para dar lugar à nova imagem
                                $conexao->query("UPDATE produto_imagem SET imagem_principal = 0 WHERE id_produto = $id_produto");
                                $imagem_principal = 1;
                                $existe_principal = true;
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
                    if ($stmt_legenda->affected_rows > 0) {
                        $houveAlteracao = true;
                    }
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
// ... (O restante do código de remoção de imagem e consulta de dados permanece inalterado)

// Lógica de remoção de imagem (fora do bloco POST principal)
if (isset($_GET['remover_imagem'])) {
    $conexao->begin_transaction();
    try {
        $id_imagem_remover = intval($_GET['remover_imagem']);
        
        $caminho_img_res = $conexao->query("SELECT caminho_imagem FROM produto_imagem WHERE id_imagem = $id_imagem_remover");
        $caminho_img_row = $caminho_img_res->fetch_assoc();
        $caminho_img = $caminho_img_row['caminho_imagem'] ?? null;

        if ($caminho_img) {
            $conexao->query("DELETE FROM produto_imagem WHERE id_imagem = $id_imagem_remover");
            if (file_exists($caminho_img)) {
                unlink($caminho_img);
            }
            
            // 🎯 CORREÇÃO PÓS-REMOÇÃO: Se a imagem principal foi removida, a primeira restante deve se tornar a principal.
            $check_principal = $conexao->query("SELECT id_imagem FROM produto_imagem WHERE id_produto = $id_produto AND imagem_principal = 1");
            if ($check_principal->num_rows == 0) {
                // Não há principal, seleciona a primeira que resta
                $nova_principal_res = $conexao->query("SELECT id_imagem FROM produto_imagem WHERE id_produto = $id_produto LIMIT 1");
                if ($nova_principal_res->num_rows > 0) {
                    $nova_principal_id = $nova_principal_res->fetch_assoc()['id_imagem'];
                    $conexao->query("UPDATE produto_imagem SET imagem_principal = 1 WHERE id_imagem = $nova_principal_id");
                }
            }

            $conexao->commit();
            header("Location: editarproduto.php?id=$id_produto&mensagem=Imagem removida com sucesso!");
            exit;
        }
    } catch (Exception $e) {
        $conexao->rollback();
        $mensagem = "Ocorreu um erro ao remover a imagem: " . $e->getMessage();
    }
}

// 🆕 Adicionado preco_promocional na consulta para preencher o campo na edição
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
// ... (o restante do código antes do HTML)
// (MANTIDO)
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
    </head>
<body>
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
            $imagens->data_seek(0); // Reseta o ponteiro para o início
            $contador_imagem = 0;
            while ($img = $imagens->fetch_assoc()): ?>
            <div>
                <img src="<?= htmlspecialchars($img['caminho_imagem']) ?>" width="100">
                <input type="text" name="legenda_existente[<?= $img['id_imagem'] ?>]" value="<?= htmlspecialchars($img['legenda']) ?>">
                <a href="?id=<?= $id_produto ?>&remover_imagem=<?= $img['id_imagem'] ?>">Remover</a>
                <label>
                    Principal?
                    <input type="radio" name="imagem_principal" value="<?= $img['id_imagem'] ?>" <?= ($img['imagem_principal'] == 1) ? 'checked' : '' ?>>
                </label>
            </div>
            <?php 
            $contador_imagem++;
            endwhile; ?>
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
            // ... (seu código JavaScript)
            function adicionarCampoImagem() {
                const container = document.getElementById('imagens-container');
                // 🎯 CORREÇÃO: Usar um prefixo diferente para novas imagens para evitar conflito com IDs existentes
                const index = container.children.length; 
                const radioValue = `nova_imagem_${index}`;

                const div = document.createElement('div');
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

            // ... (o restante do JavaScript permanece inalterado)
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
</body>
</html>
