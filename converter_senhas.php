<?php
include "conexao.php"; // sua conexão com o banco

// Buscar todos os usuários
$sql = "SELECT id_usuario, senha_hash FROM usuario";
$resultado = $conexao->query($sql);

$contador = 0;

while ($linha = $resultado->fetch_assoc()) {
    $id = $linha['id_usuario'];
    $senhaAntiga = $linha['senha_hash'];

    // Ignora se já estiver com hash
    if (password_get_info($senhaAntiga)['algoName'] !== 'unknown') {
        continue;
    }

    // Gerar hash seguro
    $novaSenhaHash = password_hash($senhaAntiga, PASSWORD_DEFAULT);

    // Atualizar no banco
    $stmt = $conexao->prepare("UPDATE usuario SET senha_hash = ? WHERE id_usuario = ?");
    $stmt->bind_param("si", $novaSenhaHash, $id);
    if ($stmt->execute()) {
        $contador++;
    }
}

echo "✅ $contador senha(s) convertidas com sucesso.";
?>
