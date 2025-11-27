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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
   <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Monitoramento de Fidelidade</title>
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
        
            <a href="dashboard.php">Voltar ao Menu Principal</a>
 
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
        <h1>Monitoramento do Programa de Fidelidade</h1>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Apelido</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Pontos de Fidelidade</th>
                        <th>Última Compra</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        // Inclui o arquivo de conexão
                        include 'conexao.php';

                        // Consulta SQL para buscar os dados de fidelidade e de usuário
                        $sql = "SELECT 
                                    u.nome, 
                                    u.apelido, 
                                    u.email, 
                                    u.telefone,
                                    f.pontos, 
                                    f.data_ultima_compra
                                FROM 
                                    fidelidade f
                                JOIN 
                                    usuario u ON f.id_usuario = u.id_usuario
                                ORDER BY 
                                    f.pontos DESC, f.data_ultima_compra DESC";
                        
                        $resultado = $conexao->query($sql);

                        if ($resultado->num_rows > 0) {
                            // Loop para exibir os dados de cada usuário
                            while($row = $resultado->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row["nome"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["apelido"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["telefone"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["pontos"]) . "</td>";
                                echo "<td>" . htmlspecialchars(date("d/m/Y H:i:s", strtotime($row["data_ultima_compra"]))) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='no-data'>Nenhum registro de fidelidade encontrado.</td></tr>";
                        }
                        
                        // Fecha a conexão com o banco de dados
                        $conexao->close();
                    ?>
                </tbody>
            </table>
        </div>
        <!-- <a href="fidelidade.php" class="btn-refresh">Atualizar Dados</a> -->
    </div>
</body>
</html>
