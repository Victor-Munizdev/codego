<?php
session_start();
require 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

$stmt = $pdo->prepare("SELECT nome, foto, role FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

$nome_usuario = $usuario['nome'] ?? 'Usuário';
$foto_perfil = !empty($usuario['foto']) ? htmlspecialchars($usuario['foto']) : 'https://via.placeholder.com/45x45/4f46e5/ffffff?text=' . substr($nome_usuario, 0, 1);
$usuario_role = $usuario['role'] ?? '';

$stmt = $pdo->prepare("SELECT role FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario || $usuario['role'] !== 'admin') {
    die("Acesso negado.");
}

$stmt = $pdo->query("SELECT * FROM aulas ORDER BY ordem ASC");
$aulas = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Aulas - CodeGo</title>
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
            <div class="fade-in">
                <h1> Gerenciar Aulas</h1>
                
                <div class="text-center mb-3">
                    <a href="admin_aulas" class="btn btn-primary">
                        ➕ Cadastrar Nova Aula
                    </a>
                </div>

                <div class="card">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>📚 Título</th>
                                    <th>🎯 Tipo</th>
                                    <th>📂 Categoria</th>
                                    <th>⭐ Dificuldade</th>
                                    <th>📊 Ordem</th>
                                    <th>⚙️ Ações</th>
                                </tr>
                            </thead>
                            <tbody style="scroll-x: auto;">
                                <?php foreach ($aulas as $aula): ?>
                                <tr>
                                    <td><?= e($aula['id']) ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: #4338ca;">
                                            <?= e($aula['titulo']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="background: #e0e7ff; color: #4338ca; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.875rem;">
                                            <?= e($aula['tipo']) ?>
                                        </span>
                                    </td>
                                    <td style="color: #4338ca;"><?= e(ucfirst($aula['categoria'])) ?></td>
                                    <td style="color: #4338ca;"><?= e($aula['dificuldade']) ?></td>
                                    <td style="color: black;"><?= e($aula['ordem']) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <a href="editar_aula?id=<?= e($aula['id']) ?>" class="btn btn-secondary" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                                                ✏️ Editar
                                            </a>
                                            <a href="excluir_aula?id=<?= e($aula['id']) ?>" class="btn btn-danger" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;" 
                                               onclick="return confirm('Tem certeza que deseja excluir esta aula?')">
                                                🗑️ Excluir
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
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
