<?php
session_start();
require 'conexao.php';

if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard");
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $senha = $_POST['senha'] ?? '';

    if (!$email || !$senha) {
        $erro = "Preencha todos os campos.";
    } else {
        $stmt = $pdo->prepare("SELECT id, nome, senha FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            header("Location: dashboard");
            exit;
        } else {
            $erro = "Email ou senha incorretos.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CodeGo</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="shortcut icon" href="logo.png" type="image/x-icon">
</head>
<body>
    <div class="main-container">
        <main>
            <div class="form-container fade-in">
                <div class="text-center mb-3">
                    <h1> CodeGo</h1>
                    <p style="color: #6b7280; font-size: 1.1rem;">Faça login para continuar sua jornada</p>
                </div>

                <?php if ($erro): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="form-group">
                        <label for="email">📧 Email</label>
                        <input type="email" id="email" name="email" required 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               placeholder="seu@email.com">
                    </div>

                    <div class="form-group">
                        <label for="senha">🔒 Senha</label>
                        <input type="password" id="senha" name="senha" required 
                               placeholder="Digite sua senha">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        Entrar
                    </button>
                </form>

                <div class="text-center mt-3">
                    <p style="color: #6b7280;">
                        Não tem conta? 
                        <a href="register" style="color: #4f46e5; text-decoration: none; font-weight: 600;">
                            Cadastre-se aqui
                        </a>
                    </p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
