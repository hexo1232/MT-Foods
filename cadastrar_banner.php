<?php 
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $posicao = $_POST['posicao'];
    $destino = $_POST['destino']; // ✅ NOVO: Captura o destino
    
    // 1. ALTERAÇÃO PRINCIPAL: Não usamos mais o campo 'ativo' manual no cadastro.
    // O campo 'ativo' na tabela será controlado automaticamente pelo agendamento.
    $ativo = 1; 

    // Captura as novas datas
    $data_inicio = $_POST['data_inicio'] ?? NULL;
    $data_fim = $_POST['data_fim'] ?? NULL;

    // Se não for definida, seta a data_inicio para hoje (ou NULL se preferir agendamento manual)
    if (empty($data_inicio)) {
        $data_inicio = date('Y-m-d');
    }

    // Define a ordem para o banner principal (1 para Hero/Rodapé, 0 para Carrossel)
    $ordem_bind = ($posicao === 'carrossel') ? 0 : 1; 

    // 1. Inserir banner principal
    // NOVO SQL: Inclui data_inicio, data_fim e destino
    $sql = "INSERT INTO banner_site (titulo, descricao, posicao, ativo, ordem, data_inicio, data_fim, destino) VALUES (?, ?, ?, ?, ?, ?, ?,?)";
    $stmt = $conexao->prepare($sql);
    
    // NOVO bind_param: Adiciona 'ss' para data_inicio e data_fim e 's' para destino
    $stmt->bind_param("sssiisss", $titulo, $descricao, $posicao, $ativo, $ordem_bind, $data_inicio, $data_fim,$destino);
    $stmt->execute();
    $banner_id = $stmt->insert_id;


   // 2. LÓGICA DE GESTÃO DE IMAGENS APÓS INSERÇÃO DO BANNER PRINCIPAL
    $pasta = "uploads/";
    if (!is_dir($pasta)) mkdir($pasta, 0777, true);

    if ($posicao === 'carrossel' && !empty($_FILES['imagens']['name'][0])) {
        // --- LÓGICA PARA CARROSSEL (Salva em banner_imagens) ---

        $ordens_novas = $_POST['ordem_imagem'] ?? [];

        foreach ($_FILES['imagens']['name'] as $i => $nome_arquivo) {
            $tmp = $_FILES['imagens']['tmp_name'][$i];
            if (empty($tmp)) continue;

            $caminho = $pasta . time() . "_" . basename($nome_arquivo);

            if (move_uploaded_file($tmp, $caminho)) {
                $ordem_img = intval($ordens_novas[$i] ?? 0);

                $sql_img = "INSERT INTO banner_imagens (id_banner, caminho_imagem, ordem) VALUES (?, ?, ?)";
                $stmt_img = $conexao->prepare($sql_img);
                $stmt_img->bind_param("isi", $banner_id, $caminho, $ordem_img);
                $stmt_img->execute();
            }
        }

    } elseif (!empty($_FILES['imagens']['name'][0])) {
        // --- LÓGICA PARA POSIÇÃO ÚNICA (Hero/Rodapé - Salva em banner_site) ---

        $tmp = $_FILES['imagens']['tmp_name'][0]; // Apenas o primeiro arquivo (o único)
        $nome_arquivo = $_FILES['imagens']['name'][0];

        $caminho = $pasta . time() . "_" . basename($nome_arquivo);

        if (move_uploaded_file($tmp, $caminho)) {
            // ATUALIZA o caminho da imagem no banner_site principal
            $sql_update_path = "UPDATE banner_site SET caminho_imagem = ? WHERE id_banner = ?";
            $stmt_update_path = $conexao->prepare($sql_update_path);
            $stmt_update_path->bind_param("si", $caminho, $banner_id);
            $stmt_update_path->execute();
        }
    }

    header("Location: gerenciar_banner.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Banner</title>
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="logout_auto.js"></script>
    <script src="js/dropdown2.js"></script>
       <script src="js/sidebar.js"></script>
</head>
<body>

<button class="menu-btn">☰</button>

<div class="sidebar-overlay"></div>

    <sidebar class="sidebar">
        <br><br>
        <a href="gerenciar_banner.php">Voltar aos banners</a>
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
        <h1>Cadastrar Banner</h1>
        <form method="POST" enctype="multipart/form-data">
            <label>Título:</label><br>
            <input type="text" name="titulo" required><br><br>

            <label>Descrição:</label><br>
            <textarea name="descricao" rows="3"></textarea><br><br>
            
            <fieldset style="border: 1px solid #ccc; padding: 10px; margin-bottom: 20px;">
                <legend>Agendamento de Exibição</legend>
                <label>Data de Início (Obrigatório):</label><br>
                <input type="date" name="data_inicio" value="<?= date('Y-m-d') ?>"><br><br>
                <label>Data de Fim (Obrigatório):</label><br>
                <input type="date" name="data_fim"><br>
                            </fieldset>

                        <label>Página de Destino (Ao clicar):</label><br>
            <select name="destino" required>
                <option value="Início">Início</option>
                <option value="Acerca de Nós">Acerca de Nós</option>
                <option value="Ajuda">Ajuda</option>
            </select><br><br>
            
            <label>Posição:</label><br>
            <select name="posicao" id="posicao" required onchange="toggleCarrosselFields()">
                <option value="">Selecione...</option>
                <option value="carrossel">Carrossel</option>
                <option value="hero">Hero</option>
                <option value="rodape">Rodapé</option>
            </select><br><br>
            
            <div id="carrosselContainer" style="display:none;">
                <h3>Imagens do Carrossel (Definir Ordem Individual)</h3>
                <div id="imagens-container"></div>
                <button type="button" onclick="adicionarCampoImagem()">Adicionar Imagem</button><br><br>
            </div>

            <div id="imagemUnicaContainer" style="display:none;">
                <h3>Imagem Única (Para Hero/Rodapé)</h3>
                <label>Arquivo:</label>
                <input type="file" name="imagens[]" id="imagemUnicaInput">
                <p style="font-size: 0.8em; color: #666;">
                    A ordem será fixada em 1 para esta posição.
                </p><br>
            </div>

            <button type="submit">Cadastrar Banner</button>
        </form>
    </div>

    <script>
        // Mantém o controle do índice para garantir nomes de input alinhados
        let imageIndex = 0;

        function toggleCarrosselFields() {
            const pos = document.getElementById('posicao').value;
            const carrosselDiv = document.getElementById('carrosselContainer');
            const unicaDiv = document.getElementById('imagemUnicaContainer');
            const unicaInput = document.getElementById('imagemUnicaInput');

            if (pos === 'carrossel') {
                carrosselDiv.style.display = 'block';
                unicaDiv.style.display = 'none';
                unicaInput.removeAttribute('required'); 
            } else if (pos === 'hero' || pos === 'rodape') {
                carrosselDiv.style.display = 'none';
                unicaDiv.style.display = 'block';
                unicaInput.setAttribute('required', 'required'); 
            } else {
                carrosselDiv.style.display = 'none';
                unicaDiv.style.display = 'none';
                unicaInput.removeAttribute('required');
            }
        }

        // Função para adicionar múltiplas imagens com campo de ordem
        function adicionarCampoImagem() {
            const container = document.getElementById('imagens-container');
            const div = document.createElement('div');
            div.style.cssText = 'border: 1px dashed #ccc; padding: 10px; margin-bottom: 15px;';

            const initialOrder = container.children.length + 1;

            div.innerHTML = `
                <h4>Imagem #${initialOrder}</h4>
                <label>Arquivo:</label>
                <input type="file" name="imagens[]" required><br>
                
                <label>
                    Ordem de Exibição:
                    <input type="number" name="ordem_imagem[]" value="${initialOrder}" min="0" required style="width: 80px;"> 
                </label><br>
                
                <button type="button" onclick="this.parentNode.remove()">Remover esta Imagem</button>
                <br>
            `;
            container.appendChild(div);
            imageIndex++; 
        }
        
        document.addEventListener('DOMContentLoaded', toggleCarrosselFields);
    </script>
</body>
</html>
