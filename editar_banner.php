<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// ==========================================================
// 1. OBTENÇÃO DO ID DO BANNER
// ==========================================================
$banner_id = $_GET['id'] ?? null;
if (!$banner_id) {
    header("Location: gerenciar_banner.php");
    exit;
}

// ==========================================================
// PARTE 1: PROCESSAMENTO DO FORMULÁRIO (POST)
// ==========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $posicao = $_POST['posicao'];

    // ⭐ NOVO: Captura o destino
    $destino = $_POST['destino'];

    // Captura o status 'ativo' manualmente (override)
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    // Captura as datas de agendamento
    $data_inicio = $_POST['data_inicio'] ?? NULL;
    $data_fim = $_POST['data_fim'] ?? NULL;

    // Se a data de início estiver vazia, usa a data atual
    if (empty($data_inicio)) {
        $data_inicio = date('Y-m-d');
    }

    // Define a ordem para o banner principal
    $ordem_bind = ($posicao === 'carrossel') ? 0 : 1;

    // Atualização do banner principal (banner_site)
    $sql = "UPDATE banner_site 
            SET titulo=?, descricao=?, posicao=?, ativo=?, ordem=?, data_inicio=?, data_fim=?, destino=? 
            WHERE id_banner=?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("sssiisssi", $titulo, $descricao, $posicao, $ativo, $ordem_bind, $data_inicio, $data_fim, $destino, $banner_id);
    $stmt->execute();

    // -----------------------------------------------------------
    // LÓGICA DE GESTÃO DE IMAGENS (CARROSSEL)
    // -----------------------------------------------------------
    if ($posicao === 'carrossel') {
        $pasta = "uploads/";
        if (!is_dir($pasta)) mkdir($pasta, 0777, true);

        // 1. Remover imagens existentes (marcadas)
        if (!empty($_POST['remover_imagem_id'])) {
            $ids_para_remover = implode(',', array_map('intval', $_POST['remover_imagem_id']));

            // Obter caminhos de arquivo para exclusão física
            $sql_caminhos = "SELECT caminho_imagem FROM banner_imagens WHERE id_imagem IN ($ids_para_remover)";
            $result_caminhos = $conexao->query($sql_caminhos);

            while ($row = $result_caminhos->fetch_assoc()) {
                if (file_exists($row['caminho_imagem'])) {
                    unlink($row['caminho_imagem']);
                }
            }

            // Excluir registros do banco
            $sql_del = "DELETE FROM banner_imagens WHERE id_imagem IN ($ids_para_remover)";
            $conexao->query($sql_del);
        }

        // 2. Atualizar ordem das imagens existentes
        if (!empty($_POST['imagem_existente_id'])) {
            foreach ($_POST['imagem_existente_id'] as $index => $id_imagem) {
                $nova_ordem = intval($_POST['ordem_existente'][$index] ?? 0);
                $sql_update_ordem = "UPDATE banner_imagens SET ordem=? WHERE id_imagem=?";
                $stmt_update = $conexao->prepare($sql_update_ordem);
                $stmt_update->bind_param("ii", $nova_ordem, $id_imagem);
                $stmt_update->execute();
            }
        }

        // 3. Adicionar novas imagens (Upload)
        if (!empty($_FILES['imagens']['name'][0])) {
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
        }

    } elseif (!empty($_FILES['imagens']['name'][0])) {
        // ==========================================================
        // LÓGICA PARA POSIÇÕES ÚNICAS (Hero / Rodapé)
        // ==========================================================
        $pasta = "uploads/";
        if (!is_dir($pasta)) mkdir($pasta, 0777, true);

        $tmp = $_FILES['imagens']['tmp_name'][0];
        $nome_arquivo = $_FILES['imagens']['name'][0];

        // 1. Remove imagem antiga se existir
        $sql_old_img = "SELECT caminho_imagem FROM banner_site WHERE id_banner = ?";
        $stmt_old_img = $conexao->prepare($sql_old_img);
        $stmt_old_img->bind_param("i", $banner_id);
        $stmt_old_img->execute();
        $result_old_img = $stmt_old_img->get_result();
        $row_old_img = $result_old_img->fetch_assoc();

        if ($row_old_img && !empty($row_old_img['caminho_imagem']) && file_exists($row_old_img['caminho_imagem'])) {
            unlink($row_old_img['caminho_imagem']);
        }

        // 2. Faz upload da nova imagem
        $caminho_novo = $pasta . time() . "_" . basename($nome_arquivo);
        if (move_uploaded_file($tmp, $caminho_novo)) {
            // 3. Atualiza caminho no banco
            $sql_update_path = "UPDATE banner_site SET caminho_imagem=? WHERE id_banner=?";
            $stmt_update_path = $conexao->prepare($sql_update_path);
            $stmt_update_path->bind_param("si", $caminho_novo, $banner_id);
            $stmt_update_path->execute();
        }
    }

    header("Location: gerenciar_banner.php");
    exit;
}

// ==========================================================
// PARTE 2: BUSCA DE DADOS PARA EXIBIÇÃO (GET)
// ==========================================================

// Busca o banner principal
$sql_banner = "SELECT * FROM banner_site WHERE id_banner = ?";
$stmt_banner = $conexao->prepare($sql_banner);
$stmt_banner->bind_param("i", $banner_id);
$stmt_banner->execute();
$result_banner = $stmt_banner->get_result();
$banner = $result_banner->fetch_assoc();

if (!$banner) {
    header("Location: gerenciar_banner.php");
    exit;
}

// Busca imagens do carrossel (se houver)
$imagens_carrossel = [];
if ($banner['posicao'] === 'carrossel') {
    $sql_imagens = "SELECT id_imagem, caminho_imagem, ordem FROM banner_imagens WHERE id_banner = ? ORDER BY ordem ASC";
    $stmt_imagens = $conexao->prepare($sql_imagens);
    $stmt_imagens->bind_param("i", $banner_id);
    $stmt_imagens->execute();
    $result_imagens = $stmt_imagens->get_result();

    while ($row = $result_imagens->fetch_assoc()) {
        $imagens_carrossel[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Editar Banner - <?= htmlspecialchars($banner['titulo']) ?></title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/darkmode2.js"></script>
    <script src="logout_auto.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/dropdown2.js"></script>
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
        <h1>Editar Banner: <?= htmlspecialchars($banner['titulo']) ?></h1>

        <form method="POST" enctype="multipart/form-data">
            <label>Título:</label><br>
            <input type="text" name="titulo" value="<?= htmlspecialchars($banner['titulo']) ?>" required><br><br>

            <label>Descrição:</label><br>
            <textarea name="descricao" rows="3"><?= htmlspecialchars($banner['descricao']) ?></textarea><br><br>

            <label>
                <input type="checkbox" name="ativo" <?= $banner['ativo'] ? 'checked' : '' ?>>
                Banner Ativo (Override Manual)
            </label><br><br>

            <label>Página de Destino (Ao clicar):</label><br>
            <select name="destino" required>
                <option value="Início" <?= $banner['destino'] === 'Início' ? 'selected' : '' ?>>Início</option>
                <option value="Acerca de Nós" <?= $banner['destino'] === 'Acerca de Nós' ? 'selected' : '' ?>>Acerca de Nós</option>
                <option value="Ajuda" <?= $banner['destino'] === 'Ajuda' ? 'selected' : '' ?>>Ajuda</option>
            </select><br><br>

            <fieldset style="border: 1px solid #ccc; padding: 10px; margin-bottom: 20px;">
                <legend>Agendamento de Exibição</legend>

                <label>Data de Início:</label><br>
                <input type="date" name="data_inicio" value="<?= htmlspecialchars($banner['data_inicio'] ?? date('Y-m-d')) ?>"><br><br>

                <label>Data de Fim (Opcional):</label><br>
                <input type="date" name="data_fim" value="<?= htmlspecialchars($banner['data_fim']) ?>"><br>

                <p style="font-size: 0.8em; color: #666;">
                    Define o período em que o banner deve ser exibido.  
                    O status "Ativo" acima serve como um interruptor manual.
                </p>
            </fieldset>

            <label>Posição:</label><br>
            <select name="posicao" id="posicao" required onchange="toggleCarrosselFields()">
                <option value="carrossel" <?= $banner['posicao'] === 'carrossel' ? 'selected' : '' ?>>Carrossel</option>
                <option value="hero" <?= $banner['posicao'] === 'hero' ? 'selected' : '' ?>>Hero</option>
                <option value="rodape" <?= $banner['posicao'] === 'rodape' ? 'selected' : '' ?>>Rodapé</option>
            </select><br><br>

            <div id="carrosselContainer">
                <h3>Imagens do Carrossel (Edição e Adição)</h3>

                <?php if (!empty($imagens_carrossel)): ?>
                    <h4>Imagens Atuais</h4>
                    <div id="imagens-existentes-container">
                        <?php foreach ($imagens_carrossel as $img): ?>
                            <div style="border: 1px dashed #ccc; padding: 10px; margin-bottom: 15px;">
                                <img src="<?= htmlspecialchars($img['caminho_imagem']) ?>" alt="Imagem Carrossel"
                                     style="max-width: 100px; max-height: 100px; display: block; margin-bottom: 10px;">

                                <label>Ordem:</label>
                                <input type="number" name="ordem_existente[]" value="<?= htmlspecialchars($img['ordem']) ?>"
                                       min="0" style="width: 80px;">

                                <input type="hidden" name="imagem_existente_id[]" value="<?= $img['id_imagem'] ?>">

                                <label style="display: block; margin-top: 10px;">
                                    <input type="checkbox" name="remover_imagem_id[]" value="<?= $img['id_imagem'] ?>">
                                    Remover esta Imagem
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h4>Adicionar Novas Imagens (Carrossel)</h4>
                <div id="imagens-container"></div>
                <button type="button" onclick="adicionarCampoImagem()">Adicionar Nova Imagem</button><br><br>
            </div>

            <div id="imagemUnicaContainer">
                <h3>Imagem Única (Para Hero/Rodapé)</h3>

                <?php if ($banner['posicao'] !== 'carrossel' && !empty($banner['caminho_imagem'])): ?>
                    <h4>Imagem Atual</h4>
                    <img src="<?= htmlspecialchars($banner['caminho_imagem']) ?>" alt="Imagem Única"
                         style="max-width: 200px; max-height: 200px; display: block; margin-bottom: 10px;">
                    <p>Faça upload de um novo arquivo para substituir.</p>
                <?php endif; ?>

                <label>Novo Arquivo:</label>
                <input type="file" name="imagens[]" id="imagemUnicaInput">
                <p style="font-size: 0.8em; color: #666;">
                    Selecione um arquivo apenas se quiser substituir a imagem existente.
                </p><br>
            </div>

            <button type="submit">Atualizar Banner</button>
        </form>
    </div>

    <script>
        let imageIndex = 0;

        function toggleCarrosselFields() {
            const pos = document.getElementById('posicao').value;
            const carrosselDiv = document.getElementById('carrosselContainer');
            const unicaDiv = document.getElementById('imagemUnicaContainer');
            const unicaInput = document.getElementById('imagemUnicaInput');

            const isCarrossel = pos === 'carrossel';

            carrosselDiv.style.display = isCarrossel ? 'block' : 'none';
            unicaDiv.style.display = isCarrossel ? 'none' : 'block';

            const hasExistingSingleImage = <?= $banner['posicao'] !== 'carrossel' && !empty($banner['caminho_imagem']) ? 'true' : 'false' ?>;

            if (isCarrossel) {
                unicaInput.removeAttribute('required');
            }
        }

        function adicionarCampoImagem() {
            const container = document.getElementById('imagens-container');
            const div = document.createElement('div');
            div.style.cssText = 'border: 1px dashed #ccc; padding: 10px; margin-bottom: 15px;';

            const existentes = document.getElementById('imagens-existentes-container')
                ? document.getElementById('imagens-existentes-container').children.length : 0;
            const initialOrder = existentes + container.children.length + 1;

            div.innerHTML = `
                <h4>Nova Imagem #${initialOrder}</h4>
                <label>Arquivo:</label>
                <input type="file" name="imagens[]" required><br>
                <label>Ordem de Exibição:</label>
                <input type="number" name="ordem_imagem[]" value="${initialOrder}" min="0" required style="width: 80px;"><br>
                <button type="button" onclick="this.parentNode.remove()">Remover esta Imagem</button><br>
            `;

            container.appendChild(div);
            imageIndex++;
        }

        document.addEventListener('DOMContentLoaded', toggleCarrosselFields);
    </script>
</body>
</html>
