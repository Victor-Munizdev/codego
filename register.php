<?php
session_start();
require 'conexao.php';

if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard");
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $senha_confirm = $_POST['senha_confirm'] ?? '';

    if (!$nome || !$email || !$senha || !$senha_confirm) {
        $erro = "Por favor, preencha todos os campos.";
    } elseif ($senha !== $senha_confirm) {
        $erro = "As senhas não coincidem.";
    } else {
        // Verificar se email já existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erro = "Este email já está cadastrado.";
        } else {
            // Hash da senha
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            // Inserir usuário no banco
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
            $stmt->execute([$nome, $email, $senha_hash]);

            $sucesso = "Cadastro realizado com sucesso! Você pode fazer login agora.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - CodeGo</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="shortcut icon" href="logo.png" type="image/x-icon">
</head>
<body>
    <div class="main-container">
        <main>
            <div class="form-container fade-in">
                <div class="text-center mb-3">
                    <h1> CodeGo</h1>
                    <p style="color: #6b7280; font-size: 1.1rem;">Crie sua conta e comece a aprender</p>
                </div>

                <?php if ($erro): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($sucesso): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($sucesso) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="form-group">
                        <label for="nome">👤 Nome completo</label>
                        <input type="text" id="nome" name="nome" required 
                               value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" 
                               placeholder="Seu nome completo">
                    </div>

                    <div class="form-group">
                        <label for="email">📧 Email</label>
                        <input type="email" id="email" name="email" required 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               placeholder="seu@email.com">
                    </div>

                    <div class="form-group">
                        <label for="senha">🔒 Senha</label>
                        <input type="password" id="senha" name="senha" required 
                               placeholder="Digite uma senha segura">
                    </div>

                    <div class="form-group">
                        <label for="senha_confirm">🔒 Confirmar senha</label>
                        <input type="password" id="senha_confirm" name="senha_confirm" required 
                               placeholder="Confirme sua senha">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        Cadastrar
                    </button>
                </form>

                <div class="text-center mt-3">
                    <p style="color: #6b7280;">
                        Já tem uma conta? 
                        <a href="index" style="color: #4f46e5; text-decoration: none; font-weight: 600;">
                            Faça login aqui
                        </a>
                    </p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
