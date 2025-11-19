<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";
// ===============================================
// NOVO CÓDIGO PARA GESTÃO DE ALERTA DE INGREDIENTES
// ===============================================

// 1. Busca todos os ingredientes com estoque baixo
$alerta_sql = "SELECT id_ingrediente, nome_ingrediente, quantidade_estoque FROM ingrediente WHERE quantidade_estoque <= 20 ORDER BY quantidade_estoque ASC";
$alerta_resultado = $conexao->query($alerta_sql);

$ingredientes_alerta = [];
$alerta_vermelho_presente = false;
$alerta_laranja_presente = false;

if ($alerta_resultado->num_rows > 0) {
    while ($ing = $alerta_resultado->fetch_assoc()) {
        $nivel = '';
        if ($ing['quantidade_estoque'] < 10) {
            $nivel = 'vermelho';
            $alerta_vermelho_presente = true;
        } elseif ($ing['quantidade_estoque'] <= 20) {
            $nivel = 'laranja';
            $alerta_laranja_presente = true;
        }
        
        if ($nivel) {
            $ingredientes_alerta[] = [
                'id' => $ing['id_ingrediente'],
                'nome' => $ing['nome_ingrediente'],
                'estoque' => $ing['quantidade_estoque'],
                'nivel' => $nivel
            ];
        }
    }
}

// 2. Persiste o estado do alerta na sessão (para o popup)
if (!empty($ingredientes_alerta)) {
    // Armazena a lista de ingredientes em alerta na sessão
    $_SESSION['alerta_estoque_ingredientes'] = $ingredientes_alerta;
} else {
    // Limpa a sessão se não houver mais alertas
    unset($_SESSION['alerta_estoque_ingredientes']);
}

// ===============================================
// FIM NOVO CÓDIGO PARA GESTÃO DE ALERTA DE INGREDIENTES
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

            <style>
/* NOVO CÓDIGO CSS */
.card.alerta-laranja {
    border: 3px solid #ff9900 !important; /* Laranja mais escuro */
}
.card.alerta-vermelho {
    border: 3px solid #cc0000 !important; /* Vermelho forte */
}

/* Estilo para o Toast/Popup */
.toast-ingrediente {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    padding: 15px 25px;
    border-radius: 8px;
    color: white;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    opacity: 0;
    transition: opacity 0.5s, transform 0.5s;
    transform: translateX(100%);
}
.toast-ingrediente.show {
    opacity: 1;
    transform: translateX(0);
}
.toast-ingrediente.laranja {
    background-color: #ff9900;
}
.toast-ingrediente.vermelho {
    background-color: #cc0000;
}
.toast-ingrediente ul {
    list-style-type: none;
    padding-left: 0;
    margin-top: 5px;
}
.toast-ingrediente li {
    margin-top: 3px;
    font-weight: bold;
}
.toast-ingrediente .close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    float: right;
    margin-left: 10px;
}
/* FIM NOVO CÓDIGO CSS */
</style>
</head>
<body>

<button class="menu-btn">☰</button>

<!-- Overlay -->
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
       <br><br>
          
                <a href="dashboard.php">Voltar ao Menu Principal</a>
          
            <a href="cadastroingrediente.php">Cadastrar novo Ingrediente</a>
            <a href="ver_categoria_ingrediente.php">Ver categorias de ingredientes</a>
   
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
            
<h2>Produtos Cadastrados</h2>


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
            ORDER BY i.id_ingrediente DESC";

    $resultado = $conexao->query($sql);
if ($resultado->num_rows > 0) {
    while ($ingrediente = $resultado->fetch_assoc()) {

        $imagem = $ingrediente['caminho_imagem'] ?: 'uploads/sem_imagem.png'; // imagem padrão

        // NOVO: Lógica para determinar a classe de alerta
        $alerta_class = '';
        if ($ingrediente['quantidade_estoque'] < 10) {
            $alerta_class = 'alerta-vermelho';
        } elseif ($ingrediente['quantidade_estoque'] <= 20) {
            $alerta_class = 'alerta-laranja';
        }
        // FIM NOVO

        // NOVO: Aplicando a classe de alerta ao div.card
        echo "
        <div class='card {$alerta_class}'>
            <img src='{$imagem}' alt='Imagem do Ingrediente'>
            <div class='info'>
                <h3>" . htmlspecialchars($ingrediente['nome_ingrediente']) . "</h3>
                <p><strong>Preço:</strong> MT " . number_format($ingrediente['preco_adicional'], 2, ',', '.') . "</p>
                <p><strong>Estoque:</strong> {$ingrediente['quantidade_estoque']} unidades</p>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['alerta_estoque_ingredientes'])): 
        $alerta_tipo = $alerta_vermelho_presente ? 'vermelho' : 'laranja';
    ?>
        // Dados de alerta persistidos
        const alertas = <?php echo json_encode($_SESSION['alerta_estoque_ingredientes']); ?>;
        const tipoAlerta = '<?php echo $alerta_tipo; ?>';
        
        let listaItens = alertas.map(item => `<li>${item.nome} (${item.estoque} unid.)</li>`).join('');
        
        const popup = document.createElement('div');
        popup.className = `toast-ingrediente ${tipoAlerta}`;
        
        popup.innerHTML = `
            <button class="close-btn" aria-label="Fechar Alerta">&times;</button>
            <h4>⚠️ ALERTA DE ESTOQUE - Reposição Urgente</h4>
            <p>Os seguintes ingredientes estão com estoque **${tipoAlerta === 'vermelho' ? 'CRÍTICO' : 'BAIXO'}**:</p>
            <ul>${listaItens}</ul>
        `;
        
        document.body.appendChild(popup);
        
        // Exibe o popup
        setTimeout(() => popup.classList.add('show'), 100);

        // Funcionalidade para fechar manualmente (não remove da sessão, apenas oculta temporariamente)
        popup.querySelector('.close-btn').addEventListener('click', function() {
            popup.classList.remove('show');
            setTimeout(() => popup.remove(), 500); // Remove do DOM após a transição
        });
        
    <?php endif; ?>
});
</script>
</body>
</html>
