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
$mensagem = "";
$redirecionar = false;

// Inserção do ingrediente e imagens
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $nome = $_POST['nome'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    // Certifique-se de tratar o preço como float/decimal
    $preco = filter_var($_POST['preco'] ?? 0, FILTER_VALIDATE_FLOAT); 
    $quantidade = filter_var($_POST['quantidade'] ?? 0, FILTER_VALIDATE_INT);
    
    // CORREÇÃO 1: Capturar as categorias pelo nome correto do campo no HTML (ingredientes[])
    $categorias = $_POST['ingredientes'] ?? []; 

    if (empty($nome) || $preco === false || $quantidade === false) {
        $mensagem = "Dados obrigatórios inválidos.";
    } else {

        // Verifica se o ingrediente já existe
        $stmt = $conexao->prepare("SELECT id_ingrediente FROM ingrediente WHERE nome_ingrediente = ?");
        $stmt->bind_param("s",$nome);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $mensagem = "<p style='color:red;'>Já existe um ingrediente com esse nome.</p>";
        } else {
            
            // Inicia a transação para garantir que todas as inserções (ingrediente, categorias, imagens) sejam bem-sucedidas
            $conexao->begin_transaction();
            try {
                // 1. Inserir na tabela ingrediente
                $disponibilidade = ($quantidade > 0) ? 'Disponível' : 'Indisponível';
                
                $stmt = $conexao->prepare("INSERT INTO ingrediente (nome_ingrediente, descricao, preco_adicional, quantidade_estoque, disponibilidade) 
                                         VALUES (?, ?, ?, ?, ?)");
                
                if ($stmt === false) {
                    throw new Exception("Erro na preparação da inserção do ingrediente: " . $conexao->error);
                }
                
                // O tipo para preco_adicional deve ser 'd' (double/float) e quantidade_estoque 'i' (integer)
                $stmt->bind_param("ssdis", $nome, $descricao, $preco, $quantidade, $disponibilidade);
                
                if (!$stmt->execute()) {
                    throw new Exception("Erro ao inserir ingrediente: " . $stmt->error);
                }
                
                $id_ingrediente = $conexao->insert_id;
                $stmt->close();

                // 2. Associa às categorias (CORREÇÃO JÁ APLICADA NA VARIÁVEL $categorias)
                if (!empty($categorias)) {
                    $sql_insert_assoc = "INSERT INTO categoriadoingrediente_ingrediente (id_categoriadoingrediente, id_ingrediente) VALUES (?, ?)";
                    $insere = $conexao->prepare($sql_insert_assoc);

                    if ($insere === false) {
                        throw new Exception("Erro na preparação da associação de categoria: " . $conexao->error);
                    }

                    foreach ($categorias as $id_categoriadoingrediente) {
                        // Validação: Assumindo que o ID de categoria é um inteiro positivo
                        $id_categoriadoingrediente = (int)$id_categoriadoingrediente;

                        // Simplificado: A verificação de existência na tabela associativa só é estritamente necessária
                        // se houver a possibilidade de ID duplicado na submissão, mas o PK impede duplicados.
                        // Mantemos a inserção direta.
                        
                        $insere->bind_param("ii", $id_categoriadoingrediente, $id_ingrediente);
                        if (!$insere->execute()) {
                            // Logar erro específico e continuar ou lançar exceção
                            throw new Exception("Erro ao associar a categoria ID: " . $id_categoriadoingrediente . ". Erro: " . $insere->error);
                        }
                    }
                    $insere->close();
                }

                // 3. Upload e Inserção das imagens (CORREÇÃO 2: USAR $id_ingrediente)
                if (isset($_FILES['imagens']) && is_array($_FILES['imagens']['tmp_name'])) {
                    
                    $stmt_img = $conexao->prepare("INSERT INTO ingrediente_imagem (id_ingrediente, caminho_imagem, legenda, imagem_principal)
                                                     VALUES (?, ?, ?, ?)");
                    
                    if ($stmt_img === false) {
                        throw new Exception("Erro na preparação da inserção de imagem: " . $conexao->error);
                    }

                    foreach ($_FILES['imagens']['tmp_name'] as $index => $tmp_name) {
                        if (!empty($tmp_name) && $_FILES['imagens']['error'][$index] === UPLOAD_ERR_OK) {
                            
                            $nome_arquivo = basename($_FILES['imagens']['name'][$index]);
                            $destino = "uploads/" . time() . "_" . $nome_arquivo;

                            if (move_uploaded_file($tmp_name, $destino)) {
                                $legenda = $_POST['legenda'][$index] ?? '';
                                // Verifica se o rádio button 'imagem_principal' corresponde ao índice atual
                                $imagem_principal = (isset($_POST['imagem_principal']) && (string)$_POST['imagem_principal'] === (string)$index) ? 1 : 0;
                                
                                // CORREÇÃO AQUI: Usar $id_ingrediente em vez de $id_produto
                                $stmt_img->bind_param("issi", $id_ingrediente, $destino, $legenda, $imagem_principal);
                                
                                if (!$stmt_img->execute()) {
                                     throw new Exception("Erro ao inserir a imagem: " . $stmt_img->error);
                                }

                            } else {
                                // Se o move_uploaded_file falhar, o ingrediente ainda será cadastrado, mas a imagem não.
                                // Pode-se adicionar logging aqui.
                            }
                        }
                    }
                    $stmt_img->close();
                }

                // Commit se tudo correu bem
                $conexao->commit();
                $mensagem = "✅ Ingrediente cadastrado com sucesso!";
                $redirecionar = true;

            } catch (Exception $e) {
                // Rollback em caso de erro
                $conexao->rollback();
                $mensagem = "❌ Erro durante o cadastro do ingrediente: " . $e->getMessage();
                $redirecionar = false;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

       <title>Cadastro de Ingrediente</title>
    
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
    <a href="ver_ingredientes.php">Voltar aos ingredientes</a>
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

    <h2>Cadastrar Ingrediente</h2>
        
    <form method="post" enctype="multipart/form-data">
        <label>Nome:</label><input type="text" name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"><br>
        <label>Descrição:</label><textarea name="descricao" required placeholder="Caracterize o Ingrediente por completo"><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea><br>
        <label>Preço adicional:</label><input type="number" name="preco" step="0.01" min="0" required placeholder="Insira aqui o preço adicional..." value="<?= htmlspecialchars($_POST['preco'] ?? '') ?>"><br>
        <label>Quantidade:</label><input type="number" name="quantidade" min="0" required value="<?= htmlspecialchars($_POST['quantidade'] ?? '') ?>"><br>
    
        <label>Categorias por Associar:</label><br>
    
        <div class="ingredientes-container">
        <?php
        // Refazer a consulta para o caso de ter ocorrido erro de validação (manter valores do POST)
        $sql = "SELECT * from categoriadoingrediente ORDER BY nome_categoriadoingrediente ASC";
        $resultado = $conexao->query($sql);

        // Obter as categorias selecionadas em caso de erro no POST
        $categorias_selecionadas_post = $_POST['ingredientes'] ?? [];

        while ($categoria = $resultado->fetch_assoc()) {
            $id_cat = $categoria['id_categoriadoingrediente'];
            $checked = in_array($id_cat, $categorias_selecionadas_post) ? 'checked' : '';
            
            // CORREÇÃO APLICADA: O nome do input é 'ingredientes[]', que é o que o PHP recebe.
            echo "<div class='ingrediente-card " . ($checked ? 'selecionado' : '') . "'>";
            echo "  <label>";
            echo "      <input type='checkbox' name='ingredientes[]' value='{$id_cat}' class='ingrediente-checkbox' {$checked}> " . htmlspecialchars($categoria['nome_categoriadoingrediente']);
            echo "  </label>";
            echo "</div>";
        }
        ?>  
        </div >
            
        <h4>Imagens do Ingrediente</h4>
        <div id="imagens-container">
            <!-- Campos de imagem serão adicionados aqui pelo JS -->
        </div>
        <button type="button" onclick="adicionarCampoImagem()">+ Adicionar Imagem</button><br><br>

        <button class="cadastrar" type="submit">Cadastrar Ingrediente</button>
    </form>
</div>

<?php if ($redirecionar): ?>
    <script>
        // Redireciona em 3 segundos
        setTimeout(() => {
            window.location.href = 'ver_ingredientes.php';
        }, 3000);
    </script>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Função para adicionar campos de imagem
        let nextIndex = 0;
        function adicionarCampoImagem() {
            const container = document.getElementById('imagens-container');
            const index = nextIndex++; 

            const div = document.createElement('div');
            div.classList.add('imagem-input-group');
            div.style.marginBottom = '15px';
            div.innerHTML = `
                <input type="file" name="imagens[]" required>
                <input type="text" name="legenda[]" placeholder="Legenda da imagem" style="margin-top: 5px; width: 80%;">
                <label style="display: block; margin-top: 5px;">
                    <input type="radio" name="imagem_principal" value="${index}" ${index === 0 ? 'checked' : ''}>
                    Principal?
                </label>
                <button type="button" onclick="this.closest('.imagem-input-group').remove()" style="background: none; color: red; border: none; cursor: pointer; float: right;">Remover</button>
                <hr style="margin-top: 10px; border-color: #eee;">
            `;
            container.appendChild(div);
        }

        // Adiciona um campo de imagem por padrão
        adicionarCampoImagem();
        window.adicionarCampoImagem = adicionarCampoImagem;

        // Lógica de estilo para os checkboxes
        document.querySelectorAll('.ingrediente-checkbox').forEach(checkbox => {
            const card = checkbox.closest('.ingrediente-card');
            
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    card.classList.add('selecionado');
                } else {
                    card.classList.remove('selecionado');
                }
            });
        });
    });
</script>
</body>
</html>