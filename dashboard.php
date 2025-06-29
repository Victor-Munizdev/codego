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

$stmt = $pdo->query("SELECT * FROM aulas 
    ORDER BY 
        CASE categoria
            WHEN 'lógica de programação' THEN 1
            WHEN 'front-end' THEN 2
            WHEN 'back-end' THEN 3
            WHEN 'full-stack' THEN 4
            ELSE 5
        END,
        ordem ASC");
$aulas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$aulas_por_categoria = [];
foreach ($aulas as $aula) {
    $categoria = strtolower($aula['categoria']);
    $aulas_por_categoria[$categoria][] = $aula;
}

$stmt = $pdo->prepare("
    SELECT r.aula_id, r.correta
    FROM respostas_aulas r
    INNER JOIN (
        SELECT aula_id, MAX(id) AS max_id
        FROM respostas_aulas
        WHERE usuario_id = ?
        GROUP BY aula_id
    ) AS latest ON r.aula_id = latest.aula_id AND r.id = latest.max_id
    WHERE r.usuario_id = ?
");
$stmt->execute([$usuario_id, $usuario_id]);
$respostas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$respostas_por_aula = [];
foreach ($respostas as $resp) {
    $respostas_por_aula[$resp['aula_id']] = $resp['correta'];
}

$ordem_categorias = [
    'lógica de programação' => '🧠 Lógica de Programação',
    'front-end' => '🎨 Front-End',
    'back-end' => '⚙️ Back-End',
    'full-stack' => '🚀 Full Stack'
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CodeGo</title>
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
                <h1>Suas Aulas</h1>
                <p class="text-center" style="color: white; font-size: 1.1rem; margin-bottom: 3rem;">
                    Bem-vindo(a) de volta, <?= htmlspecialchars($nome_usuario) ?>! Continue sua jornada de aprendizado.
                </p>

                <?php foreach ($ordem_categorias as $categoria_chave => $categoria_nome): ?>
                    <?php if (!empty($aulas_por_categoria[$categoria_chave])): ?>
                        <h2><?= $categoria_nome ?></h2>
                        <div class="aulas-grid">
                            <?php foreach ($aulas_por_categoria[$categoria_chave] as $aula): 
                                $status = $respostas_por_aula[$aula['id']] ?? null;
                                $classe_status = '';
                                $status_icon = '';
                                
                                if ($status === '1' || $status === 1 || $status === true) {
                                    $classe_status = 'concluida';
                                    $status_icon = '✅';
                                } elseif ($status === '0' || $status === 0 || $status === false) {
                                    $classe_status = 'incorreta';
                                    $status_icon = '❌';
                                } elseif ($status !== null) {
                                    $status_icon = '🕒';
                                }
                            ?>
                            <div class="aula-card <?= $classe_status ?>">
                                <?php if ($status_icon): ?>
                                    <span class="status-badge"><?= $status_icon ?></span>
                                <?php endif; ?>
                                
                                <a href="aula?id=<?= $aula['id'] ?>" class="aula-titulo">
                                    <?= htmlspecialchars($aula['titulo']) ?>
                                </a>
                                
                                <div class="aula-descricao">
                                    <?= htmlspecialchars(mb_strimwidth($aula['descricao'], 0, 120, '...')) ?>
                                </div>
                                
                                <a href="aula?id=<?= $aula['id'] ?>" class="btn btn-primary">
                                    <?= $status === '1' || $status === 1 || $status === true ? '🔄 Revisar' : '▶️ Começar' ?>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
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
