<?php
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id_usuario'])) {
    $id = intval($_GET['id_usuario']);
    $stmt = $conexao->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();
    $stmt->close();
}

// Mensagem opcional de sucesso (feedback amigável)
if (isset($_GET['atualizado']) && $_GET['atualizado'] == 1) {
    echo "<div class='sucesso'>✅ Usuário atualizado com sucesso!</div>";
}

$usuario_logado = $_SESSION['usuario'];
$mensagem = "";
$redirecionar = false;
$idperfil = $usuario_logado['idperfil'];


// Função para carregar cidades via província (usada por AJAX)
if (isset($_GET['ajax']) && $_GET['ajax'] == 'cidades') {
    if (!isset($_GET['provincia']) || empty($_GET['provincia'])) {
        exit;
    }

    $idprovincia = $_GET['provincia'];

    $sql = "SELECT idcidade, nome_cidade FROM cidade WHERE idprovincia = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $idprovincia);
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<option value="">Cidade</option>';
    while ($row = $result->fetch_assoc()) {
        echo '<option value="' . $row['idcidade'] . '">' . htmlspecialchars($row['nome_cidade']) . '</option>';
    }
    exit;
}

// Preenche selects para a exibição inicial
$provincias_q = $conexao->query("SELECT idprovincia, nome_provincia FROM provincia");
$provincias = [];
while ($row = $provincias_q->fetch_assoc()) {
    $provincias[] = $row;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id_usuario'] ?? '';
    $nome = $_POST['nome'] ?? '';
    $apelido = $_POST['apelido'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $email = $_POST['email'] ?? '';
    $idcidade = $_POST['cidade'] ?? '';
    $idprovincia = $_POST['provincia'] ?? '';
    $tipo_mensagem = "error"; // padrão

    // Validação de cidade e província
    $sql_check = "SELECT COUNT(*) AS total FROM cidade WHERE idcidade = ? AND idprovincia = ?";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("ii", $idcidade, $idprovincia);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $row = $result->fetch_assoc();
    $stmt_check->close();

    if ($row['total'] == 0) {
        $mensagem = "Erro: A cidade selecionada não existe ou não pertence à província escolhida.";
    } else {
        if ($idperfil == 1) {
            $opc = $_POST['opcao'] ?? '';
            switch ($opc) {
                case 'Funcionário':
                    $idperfil_update = 2;
                    break;
                case 'Cliente':
                    $idperfil_update = 3;
                    break;
                default:
                $tipo_mensagem = "error";
                    $mensagem = "Erro! Perfil inválido.";
                    break;
            }
if (empty($mensagem)) { // ✅ Corrigido
    $sql_update = "UPDATE usuario SET nome = ?, apelido = ?, telefone = ?, email = ?, idprovincia = ?, idcidade = ?, idperfil = ? WHERE id_usuario = ?";
    $stmt = $conexao->prepare($sql_update);
    $stmt->bind_param("ssissiii", $nome, $apelido, $telefone, $email, $idprovincia, $idcidade, $idperfil_update, $id);
}
        } else {
            $sql_update = "UPDATE usuario SET nome = ?, apelido = ?, telefone = ?, email = ?, idprovincia = ?, idcidade = ? WHERE id_usuario = ?";
            $stmt = $conexao->prepare($sql_update);
            $stmt->bind_param("ssissii", $nome, $apelido, $telefone, $email, $idprovincia, $idcidade, $id);
        }


if (isset($stmt)) {
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $tipo_mensagem = "success";
        if ($idperfil == 1) { $mensagem = "Usuário atualizado com sucesso."; 
        } 
        else { $mensagem = "Dados atualizados com sucesso!
             Você será desconectado em breve para aplicar as mudanças."; }
         $redirecionar = true;

        // ✅ Recarrega os dados atualizados
        $stmt_refresh = $conexao->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
        $stmt_refresh->bind_param("i", $id);
        $stmt_refresh->execute();
        $resultado_refresh = $stmt_refresh->get_result();
        $usuario = $resultado_refresh->fetch_assoc();
        $stmt_refresh->close();

    } else {
        $tipo_mensagem = "warning";
        $mensagem = "Nenhuma alteração foi feita.";
    }
    $stmt->close();
}
    }
}elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- EXIBIÇÃO DO FORMULÁRIO (busca do usuário) ---
    if (!isset($_GET['id_usuario'])) {
        die("ID do usuário não foi informado.");
    }

    $id = $_GET['id_usuario'];
    if ($idperfil != 1 && $usuario_logado['id_usuario'] != $id) {
    die("❌ Acesso negado. Você não pode editar o perfil de outro usuário.");
}

    $stmt = $conexao->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();

    if (!$usuario) {
        die("Usuário não encontrado.");
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Editar Usuário</title>
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
    
           <?php if ($idperfil==1): ?>
    <a href="ver_usuarios.php"><img class="icone2" src="icones/voltar2.png" alt="Logout" title="voltar">Voltar aos Usuários</a>
<?php elseif($idperfil==2): ?>
 <a href="dashboard.php"><img class="icone2" src="icones/voltar2.png" alt="Logout" title="voltar">Voltar á Cozinha</a>
    <?php else: ?>
<a href="cardapio.php"><img class="icone2" src="icones/voltar2.png" alt="Logout" title="voltar">Voltar ao Cardápio</a>
       <?php endif; ?>
   
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
    <h2>Editar Usuário</h2>

<?php if (!empty($mensagem)): ?>
    <div class="mensagem <?= $tipo_mensagem ?? 'info' ?>">
        <?= htmlspecialchars($mensagem) ?>
    </div>
<?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="id_usuario" value="<?= htmlspecialchars($usuario['id_usuario']) ?>">
        
        <label>Nome:</label>
        <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>"><br>
        
        <label>Apelido:</label>
        <input type="text" name="apelido" value="<?= htmlspecialchars($usuario['apelido']) ?>"><br>

        <label>Telefone:</label>
        <input type="text" name="telefone" value="<?= htmlspecialchars($usuario['telefone']) ?>"><br>
        
        <label>Email:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>"><br>

        <label>Província:</label>
        <select name="provincia" id="provincia" onchange="carregarCidades()">
            <option value="">Selecione a Província</option>
            <?php foreach ($provincias as $p) { ?>
                <option value="<?= $p['idprovincia'] ?>" <?= ($usuario['idprovincia'] == $p['idprovincia']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nome_provincia']) ?>
                </option>
            <?php } ?>
        </select><br>

        <label>Cidade:</label>
        <select name="cidade" id="cidade">
            <?php
            // Carrega as cidades da província do usuário para pré-selecionar
            $stmt_cidades = $conexao->prepare("SELECT idcidade, nome_cidade FROM cidade WHERE idprovincia = ?");
            $stmt_cidades->bind_param("i", $usuario['idprovincia']);
            $stmt_cidades->execute();
            $resultado_cidades = $stmt_cidades->get_result();

            while ($c = $resultado_cidades->fetch_assoc()) {
                $selected = ($usuario['idcidade'] == $c['idcidade']) ? 'selected' : '';
                echo "<option value='{$c['idcidade']}' {$selected}>" . htmlspecialchars($c['nome_cidade']) . "</option>";
            }
            $stmt_cidades->close();
            ?>
        </select><br>

            <?php if ($idperfil==1): ?>
    
         <label>Perfil:</label>
        <select name="opcao">
            <option value="Funcionário" <?php if ($usuario['idperfil'] == 2) echo 'selected'; ?>>Funcionário</option>
            <option value="Cliente" <?php if ($usuario['idperfil'] == 3) echo 'selected'; ?>>Cliente</option>
        </select><br><br>
           <?php endif; ?>

        <input class="editar" type="submit" value="Atualizar">
    </form>
</div>

<script>
    function carregarCidades() {
        const provincia = document.getElementById("provincia").value;

        if (!provincia) {
            document.getElementById("cidade").innerHTML = '<option value="">Selecione a Província primeiro</option>';
            return;
        }

        fetch(`?ajax=cidades&provincia=${provincia}`)
            .then(res => res.text())
            .then(data => document.getElementById("cidade").innerHTML = data)
            .catch(() => alert("Erro ao carregar cidades."));
    }

    // Chama a função ao carregar a página para garantir que a cidade correta seja exibida
    // window.onload = carregarCidades;

    // Redirecionamento após sucesso
    <?php if($idperfil==1): ?>
    <?php if ($redirecionar): ?>
        setTimeout(() => {
            window.location.href = 'ver_usuarios.php';
        }, 3000); // Redireciona após 3 segundos
    <?php endif; ?>

    <?php elseif($idperfil==2): ?>
        <?php if ($redirecionar): ?>
        setTimeout(() => {
           window.location.href = 'logout.php';

        }, 3000); // Redireciona após 3 segundos
    <?php endif; ?>

        <?php else:?>
        <?php if ($redirecionar): ?>
        setTimeout(() => {
              window.location.href = 'logout.php';

        }, 3000); // Redireciona após 3 segundos
    <?php endif; ?>
     <?php endif; ?>


</script>

</body>
</html>