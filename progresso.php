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

// Total de aulas cadastradas
$stmt = $pdo->query("SELECT COUNT(*) FROM aulas");
$total_aulas = (int)$stmt->fetchColumn();

// Buscar aulas que usuário respondeu corretamente (concluídas)
$stmt = $pdo->prepare("
    SELECT a.titulo, MAX(r.data_hora) AS data_conclusao
    FROM respostas_aulas r
    JOIN aulas a ON r.aula_id = a.id
    WHERE r.usuario_id = ? AND r.correta = 1
    GROUP BY a.id, a.titulo
    ORDER BY data_conclusao DESC
");
$stmt->execute([$usuario_id]);
$concluidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_concluidas = count($concluidas);
$percentual = $total_aulas > 0 ? round(($total_concluidas / $total_aulas) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progresso - CodeGo</title>
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
                    </div>
                </div>
            </nav>
        </header>

        <main>
            <div class="fade-in">
                <h1> Seu Progresso</h1>
                
                <div class="progress-container">
                    <div class="progress-text">
                        <?= $total_concluidas ?> de <?= $total_aulas ?> aulas concluídas
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $percentual ?>%;"></div>
                    </div>
                    <div style="font-size: 2rem; font-weight: bold; color: #4f46e5; margin-top: 1rem;">
                        <?= $percentual ?>% Completo
                    </div>
                </div>

                <?php if ($total_concluidas === 0): ?>
                    <div class="card text-center">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎯</div>
                        <h3>Comece sua jornada!</h3>
                        <p style="color: #6b7280; margin-bottom: 2rem;">
                            Você ainda não concluiu nenhuma aula. Que tal começar agora?
                        </p>
                        <a href="dashboard" class="btn btn-primary">
                            🚀 Ver Aulas Disponíveis
                        </a>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <h3 style="margin-bottom: 1.5rem; color: black;">🏆 Aulas Concluídas</h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>📚 Aula</th>
                                        <th>📅 Data de Conclusão</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($concluidas as $linha): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.5rem; color: black;">
                                                    <span style="color: #10b981;">✅</span>
                                                    <?= htmlspecialchars($linha['titulo']) ?>
                                                </div>
                                            </td>
                                            <td style="color: black;"><?= date('d/m/Y H:i', strtotime($linha['data_conclusao'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Animar a barra de progresso
        document.addEventListener('DOMContentLoaded', () => {
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                setTimeout(() => {
                    progressFill.style.width = '<?= $percentual ?>%';
                }, 500);
            }
        });

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
