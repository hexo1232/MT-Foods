<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

// ===============================================
// NOTA: Removemos a lógica de salvar em SESSÃO aqui.
// O Dashboard já cuida do alerta via AJAX. 
// Aqui focamos apenas na visualização da lista.
// ===============================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}


?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Lista de ingredientes</title>
    
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
          
                <a href="dashboard.php">Voltar ao Menu Principal</a>
          
            <a href="cadastroingrediente.php">Cadastrar novo Ingrediente</a>
            <a href="ver_categoria_ingrediente.php">Ver categorias de ingredientes</a>
   
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
            <a href='editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>'>Editar Dados Pessoais</a>
            <a href="alterar_senha2.php">Alterar Senha</a>
            <a href="logout.php">Sair</a>
        </div>

    </div>

    <img class="dark-toggle" id="darkToggle"
         src="icones/lua.png"
         alt="Modo Escuro"
         title="Alternar modo escuro">
</div>

       
    </sidebar>

        <div class="conteudo">
            
<h2>Ingredientes Cadastrados</h2>
<?php
// ============================================================
// LÓGICA DO BANNER DE TOPO (CORRIGIDA E APRIMORADA)
// ============================================================

// Contamos separadamente: 
// 1. Críticos (menos de 10)
// 2. Baixos (entre 10 e 20)
$sql_count = "SELECT 
                SUM(CASE WHEN quantidade_estoque < 10 THEN 1 ELSE 0 END) as criticos,
                SUM(CASE WHEN quantidade_estoque >= 10 AND quantidade_estoque <= 20 THEN 1 ELSE 0 END) as baixos
              FROM ingrediente";

$res_count = $conexao->query($sql_count);
$dados_count = $res_count->fetch_assoc();

// Garante que sejam inteiros (para evitar erros caso venha nulo)
$total_criticos = (int)$dados_count['criticos'];
$total_baixos   = (int)$dados_count['baixos'];

// Se houver qualquer tipo de alerta (crítico ou baixo), mostramos o banner
if ($total_criticos > 0 || $total_baixos > 0) {
    
    $msg_banner = "";
    $classe_banner = "";

    // CENÁRIO 1: Tem Críticos E Baixos (O pior caso)
    if ($total_criticos > 0 && $total_baixos > 0) {
        $classe_banner = 'vermelho'; // Cor vermelha predomina pela urgência
        $msg_banner = "⚠️ <strong>AÇÃO NECESSÁRIA:</strong> Você tem <strong>$total_criticos itens CRÍTICOS</strong> (vermelho) e <strong>$total_baixos com estoque baixo</strong> (laranja).";
    } 
    // CENÁRIO 2: Apenas Críticos
    elseif ($total_criticos > 0) {
        $classe_banner = 'vermelho';
        $msg_banner = "⚠️ <strong>URGENTE:</strong> Você tem <strong>$total_criticos ingredientes com estoque CRÍTICO</strong> (abaixo de 10 un).";
    } 
    // CENÁRIO 3: Apenas Baixos
    else {
        $classe_banner = 'laranja';
        $msg_banner = "⚠️ <strong>ATENÇÃO:</strong> Você tem <strong>$total_baixos ingredientes com estoque baixo</strong> (abaixo de 20 un).";
    }

    $detalhe = "Role a página para identificar os produtos marcados.";

    echo "
    <div class='banner-alerta $classe_banner'>
        <span class='banner-resumo'>$msg_banner</span>
        <small>$detalhe</small>
    </div>
    ";
}
?>


<?php if (isset($_GET['msg']) && $_GET['msg'] == 'excluido'): ?>
    <p style="color: green; padding-left: 20px;">Produto excluído com sucesso!</p>
<?php endif; ?>

<div class="ingredientes">
    <?php
    $sql = "SELECT 
                i.id_ingrediente, 
                i.nome_ingrediente,
                i.preco_adicional,
                i.quantidade_estoque,
                i.disponibilidade,          
                iim.caminho_imagem,
                i.descricao
            FROM ingrediente i
                             LEFT JOIN ingrediente_imagem iim ON i.id_ingrediente = iim.id_ingrediente AND iim.imagem_principal = 1
            ORDER BY i.quantidade_estoque ASC, i.id_ingrediente DESC"; // Alterei a ordem para mostrar os com estoque baixo primeiro!

    $resultado = $conexao->query($sql);
if ($resultado->num_rows > 0) {
    while ($ingrediente = $resultado->fetch_assoc()) {

        $imagem = $ingrediente['caminho_imagem'] ?: 'uploads/sem_imagem.png'; 

        // Lógica para determinar a classe de alerta (Bordas)
        $alerta_class = '';
        if ($ingrediente['quantidade_estoque'] < 10) {
            $alerta_class = 'alerta-vermelho';
        } elseif ($ingrediente['quantidade_estoque'] <= 20) {
            $alerta_class = 'alerta-laranja';
        }

        echo "
        <div class='card-ingrediente {$alerta_class}'>
            <img src='{$imagem}' alt='Imagem do Ingrediente'>
            <div class='info'>
                <h3>" . htmlspecialchars($ingrediente['nome_ingrediente']) . "</h3>
                <p><strong>Preço:</strong> MT " . number_format($ingrediente['preco_adicional'], 2, ',', '.') . "</p>
                
                <p><strong>Estoque:</strong> 
                    <span style='" . ($alerta_class ? "font-weight:bold; color:" . ($alerta_class == 'alerta-vermelho' ? '#cc0000' : '#d68100') : "") . "'>
                        {$ingrediente['quantidade_estoque']} unidades
                    </span>
                </p>
                
                <p><strong>Descrição:</strong> {$ingrediente['descricao']}</p>
            </div>

            <div class='acoes'>
                <a href='editaringrediente.php?id={$ingrediente['id_ingrediente']}' class='editar'>Editar</a>
                <a href='excluiringrediente.php?id={$ingrediente['id_ingrediente']}' 
                   class='excluir' 
                   onclick=\"return confirm('Deseja realmente excluir este Ingrediente?')\">
                    Excluir
                </a>
            </div>
        </div>";
    }
} else {
    echo "<p style='padding-left: 20px;'>Nenhum produto encontrado.</p>";
}

    ?>
</div>
     </div>

</body>
</html>