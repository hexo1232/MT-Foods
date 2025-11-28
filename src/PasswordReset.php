<?php
//src/PasswordReset.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/helpers.php';

function sendPasswordResetLink(PDO $pdo, string $email) {
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuario WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    $response = "
    <div style='max-width:500px;margin:100px auto;padding:20px;
                background:#d4edda;color:#155724;border-radius:8px;
                text-align:center;font-family:Arial,sans-serif;'>
        <h3>Se existir uma conta com este e-mail, enviámos um link de redefinição.</h3>
        <p>Podes agora fechar esta janela.</p>
        <button onclick='fecharJanela()'
                style='margin-top:15px;padding:10px 20px;border:none;
                       background:#28a745;color:white;border-radius:6px;
                       cursor:pointer;'>Fechar</button>
    </div>

    <script>
    function fecharJanela() {
        window.open('', '_self');
        window.close();
        setTimeout(() => {
            if (!window.closed) {
                window.location.href = 'https://www.google.com';
            }
        }, 200);
    }
    </script>
    ";

    if (!$user) {
        return $response;
    }

    try {
        error_log("[DEBUG] Usuário encontrado: ID " . $user['id_usuario']);
        
        $id_usuario = $user['id_usuario'];
        $token = generateToken(32);
        $tokenHash = hashToken($token);
        $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        error_log("[DEBUG] Token gerado, salvando no banco...");
        
        // Remove tokens antigos do mesmo usuário
        $pdo->prepare("DELETE FROM password_resets WHERE id_usuario = ?")->execute([$id_usuario]);

        // Insere novo token
        $pdo->prepare("
            INSERT INTO password_resets (id_usuario, token_hash, expires_at)
            VALUES (?, ?, ?)
        ")->execute([$id_usuario, $tokenHash, $expires]);

        error_log("[DEBUG] Token salvo, preparando email...");
        
        // Envia email via API HTTP
        $resetLink = rtrim($_ENV['APP_URL'], '/') . '/public/reset_password.php?token=' . urlencode($token);

        error_log("[DEBUG] Enviando email para: $email");
        
        $emailBody = "
            <p>Olá,</p>
            <p>Recebemos um pedido para redefinir a sua palavra-passe.</p>
            <p><a href='{$resetLink}'>Clique aqui para redefinir</a> (válido por 1 hora)</p>
            <p>Se não foi você, ignore este e-mail.</p>
        ";
        
        if (!sendEmail($email, "Redefinição de Palavra-passe", $emailBody)) {
            error_log("[ERRO] Falha ao enviar email");
            throw new Exception("Falha ao enviar email");
        }

        error_log("[DEBUG] Email enviado com sucesso!");
        
        return $response;
        
    } catch (Exception $e) {
        error_log("Erro em sendPasswordResetLink: " . $e->getMessage());
        // Mesmo com erro, mostra mensagem genérica por segurança
        return $response;
    }
}

function verifyToken(PDO $pdo, string $token) {
    if (empty($token)) {
        return null;
    }
    
    $tokenHash = hashToken($token);
    $stmt = $pdo->prepare("
        SELECT pr.id_reset, pr.id_usuario, u.email
        FROM password_resets pr
        JOIN usuario u ON u.id_usuario = pr.id_usuario
        WHERE pr.token_hash = :th AND pr.used_at IS NULL AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([':th' => $tokenHash]);
    return $stmt->fetch();
}

function updatePassword(PDO $pdo, int $id_usuario, int $id_reset, string $newPassword): bool {
    try {
        $stmt = $pdo->prepare("SELECT senha_hash FROM usuario WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            showAndRedirect("❌ Usuário não encontrado.");
            return false;
        }

        // Verifica se a nova senha é igual à atual
        if (password_verify($newPassword, $user['senha_hash'])) {
            showAndRedirect("❌ Não é permitido reutilizar a senha atual.");
            return false;
        }

        // Verifica senhas antigas
        $stmt = $pdo->prepare("SELECT senha_hash FROM historico_senhas WHERE id_usuario = ? ORDER BY data_alteracao DESC LIMIT 5");
        $stmt->execute([$id_usuario]);
        $oldPasswords = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($oldPasswords as $oldHash) {
            if (password_verify($newPassword, $oldHash)) {
                showAndRedirect("❌ Não é permitido reutilizar senhas anteriores.");
                return false;
            }
        }

        // Inicia transação
        $pdo->beginTransaction();

        // Armazena a senha atual no histórico
        $stmt = $pdo->prepare("INSERT INTO historico_senhas (id_usuario, senha_hash) VALUES (?, ?)");
        $stmt->execute([$id_usuario, $user['senha_hash']]);

        // Atualiza a nova senha
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuario SET senha_hash = ?, primeira_senha = 0 WHERE id_usuario = ?")
            ->execute([$newHash, $id_usuario]);

        // Marca o token como usado
        $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id_reset = ?")
            ->execute([$id_reset]);

        // Mantém apenas as últimas 5 senhas
        $pdo->prepare("
            DELETE FROM historico_senhas
            WHERE id_usuario = ?
            AND id_historico NOT IN (
                SELECT id_historico FROM (
                    SELECT id_historico
                    FROM historico_senhas
                    WHERE id_usuario = ?
                    ORDER BY data_alteracao DESC
                    LIMIT 5
                ) AS ultimas
            )
        ")->execute([$id_usuario, $id_usuario]);

        $pdo->commit();

        showAndRedirect("✅ Palavra-passe atualizada com sucesso!");
        return true;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro em updatePassword: " . $e->getMessage());
        showAndRedirect("❌ Erro ao atualizar senha. Tente novamente.");
        return false;
    }
}

function showAndRedirect(string $msg): void {
    echo "
    <div style='max-width:500px;margin:100px auto;padding:20px;
                background:#c9e7f4;color:#001d7e;border-radius:8px;
                text-align:center;font-family:Arial,sans-serif;'>
        <h3>{$msg}</h3>
        <p>Serás redirecionado para o login em alguns segundos...</p>
    </div>

    <script>
        setTimeout(function() {
            window.location.href = '../login.php';
        }, 3000);
    </script>
    ";
}
