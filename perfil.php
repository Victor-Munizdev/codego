<?php
session_start();
require 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$erro = '';
$sucesso = '';

$stmt = $pdo->prepare("SELECT nome, foto, role FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

$nome_usuario = $usuario['nome'] ?? 'Usuário';
$foto_perfil = !empty($usuario['foto']) ? htmlspecialchars($usuario['foto']) : 'https://via.placeholder.com/45x45/4f46e5/ffffff?text=' . substr($nome_usuario, 0, 1);
$usuario_role = $usuario['role'] ?? '';

// Buscar dados atuais
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    die("Usuário não encontrado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $senha_confirm = $_POST['senha_confirm'] ?? '';

    if (!$nome || !$email) {
        $erro = "Nome e email são obrigatórios.";
    } elseif ($senha !== '' && $senha !== $senha_confirm) {
        $erro = "Senhas não conferem.";
    } else {
        // Upload de foto
        $foto = $usuario['foto'];

        if (!empty($_FILES['foto']['name'])) {
            $arquivo = $_FILES['foto'];
            $permitidos = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($arquivo['type'], $permitidos)) {
                $ext = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
                $novo_nome = 'uploads/' . uniqid() . '.' . $ext;
                if (move_uploaded_file($arquivo['tmp_name'], $novo_nome)) {
                    $foto = $novo_nome;
                } else {
                    $erro = "Erro ao enviar a foto.";
                }
            } else {
                $erro = "Formato da foto não permitido.";
            }
        }

        if (!$erro) {
            if ($senha !== '') {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ?, foto = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $senha_hash, $foto, $usuario_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, foto = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $foto, $usuario_id]);
            }
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_foto'] = $foto;
            $sucesso = "Perfil atualizado com sucesso!";
            
            // Recarregar dados do usuário
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch();
        }
    }
}

$foto_perfil = !empty($usuario['foto']) ? htmlspecialchars($usuario['foto']) : 'https://via.placeholder.com/100x100/4f46e5/ffffff?text=' . substr($usuario['nome'], 0, 1);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - CodeGo</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="shortcut icon" href="logo.png" type="image/x-icon">
</head>
<body>
    <div class="main-container">
     <header>
            <nav>
                <a href="dashboard" class="logo">🚀 CodeGo</a>
                <div class="usuario-menu" id="usuarioMenu">
                    <img src="<?= $foto_perfil ?>" alt="Foto do usuário" class="foto-perfil">
                    <span><?= htmlspecialchars($nome_usuario) ?></span>
                    <div class="dropdown">
                        <a href="perfil">👤 Perfil</a>
                        <a href="progresso">📊 Progresso</a>
                        <?php if ($usuario_role === 'admin'): ?>
                            <a href="admin_aulas">➕ Cadastrar Aulas</a>
                            <a href="listar_aulas">📋 Aulas Cadastradas</a>
                        <?php endif; ?>
                        <a href="logout">🚪 Sair</a>
                        <a href="dashboard">voltar para as lições</a>
                    </div>
                </div>
            </nav>
        </header>

        <main>
            <div class="form-container fade-in">
                <div class="text-center mb-3">
                    <img src="<?= $foto_perfil ?>" alt="Foto do usuário" 
                         style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #4f46e5; margin-bottom: 1rem;">
                    <h1> Editar Perfil</h1>
                    <p style="color: #6b7280;">Atualize suas informações pessoais</p>
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

                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="foto">📷 Foto do Perfil</label>
                        <input type="file" id="foto" name="foto" accept="image/*" 
                               style="padding: 0.5rem;">
                    </div>
                        
                    <div class="form-group">
                        <label for="nome">👤 Nome</label>
                        <input type="text" id="nome" name="nome" 
                               value="<?= htmlspecialchars($usuario['nome']) ?>" required 
                               placeholder="Seu nome completo">
                    </div>

                    <div class="form-group">
                        <label for="email">📧 Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($usuario['email']) ?>" required 
                               placeholder="seu@email.com">
                    </div>

                    <div class="form-group">
                        <label for="senha">🔒 Nova Senha</label>
                        <input type="password" id="senha" name="senha" 
                               placeholder="Deixe em branco para manter a atual">
                    </div>

                    <div class="form-group">
                        <label for="senha_confirm">🔒 Confirmar Nova Senha</label>
                        <input type="password" id="senha_confirm" name="senha_confirm" 
                               placeholder="Confirme a nova senha">
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" onclick="toggleSenha()" style="width: auto;">
                            👁️ Mostrar Senhas
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        💾 Salvar Alterações
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
        function toggleSenha() {
            const senha = document.getElementById('senha');
            const senhaConfirm = document.getElementById('senha_confirm');
            const type = senha.type === 'password' ? 'text' : 'password';
            senha.type = type;
            senhaConfirm.type = type;
        }

          document.addEventListener('DOMContentLoaded', () => {
            const menu = document.getElementById('usuarioMenu');

            menu.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.toggle('ativo');
            });

            document.addEventListener('click', (e) => {
                if (!menu.contains(e.target)) {
                    menu.classList.remove('ativo');
                }
            });
        });
    </script>
</body>
</html>
