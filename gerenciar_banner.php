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

// Função: obtém o caminho da imagem de pré-visualização
function get_preview_image($conexao, $banner)
{
    // 1. Se for posição única (Hero/Rodapé), usa a imagem da tabela principal
    if ($banner['posicao'] !== 'carrossel' && !empty($banner['caminho_imagem'])) {
        return $banner['caminho_imagem'];
    }

    // 2. Se for carrossel, busca a imagem de menor ordem na tabela banner_imagens
    if ($banner['posicao'] === 'carrossel') {
        $sql_img = "SELECT caminho_imagem FROM banner_imagens WHERE id_banner = ? ORDER BY ordem ASC LIMIT 1";
        $stmt_img = $conexao->prepare($sql_img);
        $stmt_img->bind_param("i", $banner['id_banner']);
        $stmt_img->execute();
        $result_img = $stmt_img->get_result();

        if ($row_img = $result_img->fetch_assoc()) {
            return $row_img['caminho_imagem'];
        }
    }

    // 3. Retorna um placeholder se não houver imagem
    return 'uploads/placeholder.jpg'; // Certifique-se de que este arquivo exista!
}

// Função: determina o Status de Agendamento (Visualização Admin)
function get_schedule_status($banner)
{
    $hoje = date('Y-m-d');
    $data_inicio = $banner['data_inicio'];
    $data_fim = $banner['data_fim'];

    // Se a data de fim expirou E não é NULL
    if ($data_fim !== NULL && $data_fim < $hoje) {
        return ['texto' => 'Expirado', 'classe' => 'expirado'];
    }

    // Se a data de início é no futuro
    if ($data_inicio !== NULL && $data_inicio > $hoje) {
        return ['texto' => 'Agendado', 'classe' => 'agendado'];
    }

    // Se está dentro do período (ou data_inicio é hoje/passado e data_fim é NULL/futuro)
    return ['texto' => 'Publicado', 'classe' => 'publicado'];
}

// Função: formata a data para visualização
function format_date($date)
{
    return $date ? date('d/m/Y', strtotime($date)) : 'Indefinida';
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8" >
   <meta name="viewport" 
      content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Gerenciar Banners</title>
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
        <a href="dashboard.php">Voltar ao Menu Principal</a>
        <a href="cadastrar_banner.php">Cadastrar novo Banner</a>

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
        <h1>Gerenciar Banners</h1>

        <div class="banner-grid">
            <?php
            // Consulta: visão completa do Admin
            $sql = "SELECT * FROM banner_site ORDER BY ordem ASC";
            $res = $conexao->query($sql);

            if ($res->num_rows > 0):
                while ($b = $res->fetch_assoc()):
                    $status_agenda = get_schedule_status($b);
                    $preview_image = get_preview_image($conexao, $b);
            ?>
            <div class="banner-card">
                <img src="<?= htmlspecialchars($preview_image) ?>" alt="Banner Preview">

                <div class="banner-info">
                    <div>
                        <h3><?= htmlspecialchars($b['titulo']) ?></h3>
                        <p><?= htmlspecialchars($b['descricao']) ?></p>

                        <p style="font-size: 0.9em; color: #4CAF50; margin: 5px 0;">
                            Destino: <strong><?= htmlspecialchars($b['destino']) ?></strong>
                        </p>

                        <p style="font-size: 0.8em; color: #888; margin: 5px 0;">
                            Início: <strong><?= format_date($b['data_inicio']) ?></strong> |
                            Fim: <strong><?= format_date($b['data_fim']) ?></strong>
                        </p>
                    </div>

                    <div>
                        <span class="badge posicao"><?= ucfirst($b['posicao']) ?></span>

                        <span class="badge <?= $status_agenda['classe'] ?>">
                            Status: <strong><?= $status_agenda['texto'] ?></strong>
                        </span>

                        <span class="badge <?= $b['ativo'] ? 'manual-ativo' : 'manual-inativo' ?>">
                            Manual: <strong><?= $b['ativo'] ? 'Ativo' : 'Inativo' ?></strong>
                        </span>

                        <?php if ($b['posicao'] === 'carrossel'): ?>
                        <?php endif; ?>
                    </div>

                    <div class="actions">
                        <a href="editar_banner.php?id=<?= $b['id_banner'] ?>" class="edit">Editar</a>
                        <a href="excluir_banner.php?id=<?= $b['id_banner'] ?>"
                           class="delete"
                           onclick="return confirm('Tem certeza que deseja excluir este banner?')">
                           Excluir
                        </a>
                    </div>
                </div>
            </div>
            <?php
                endwhile;
            else:
                echo "<p class='no-banners'>Nenhum banner cadastrado ainda.</p>";
            endif;
            ?>
        </div>
    </div>
</body>
</html>
