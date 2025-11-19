<?php
// Garante que o usuário esteja autenticado e disponível
require_once "require_login.php";

// Gera as iniciais do nome e apelido
$nome = $usuario['nome'] ?? '';
$apelido = $usuario['apelido'] ?? '';
$iniciais = strtoupper(substr($nome, 0, 1) . substr($apelido, 0, 1));
$nomeCompleto = "$nome $apelido";

// Função para gerar cor única baseada no nome
function gerarCor($texto) {
    $hash = md5($texto);
    $r = hexdec(substr($hash, 0, 2));
    $g = hexdec(substr($hash, 2, 2));
    $b = hexdec(substr($hash, 4, 2));
    return "rgb($r, $g, $b)";
}

$corAvatar = gerarCor($nomeCompleto);
?>
<!-- <style>
.usuario-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    font-family: Arial, sans-serif;
      margin-left: 265px;
           
}

.usuario-iniciais {
    color: white;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.usuario-nome {
    font-weight: bold;
}
</style>

<div class="usuario-info">
    <div class="usuario-iniciais" style="background-color: <?= $corAvatar ?>"><?= $iniciais ?></div>
    <div class="usuario-nome"><?= $nomeCompleto ?></div>
</div> -->
