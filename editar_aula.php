<?php
session_start();
require 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Verifica se é admin
$stmt = $pdo->prepare("SELECT role FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$usuario || $usuario['role'] !== 'admin') {
    die("Acesso negado.");
}

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM aulas WHERE id = ?");
$stmt->execute([$id]);
$aula = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aula) {
    die("Aula não encontrada.");
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $video = trim($_POST['video'] ?? '');
    $tipo = $_POST['tipo'] ?? 'quiz';
    $dificuldade = $_POST['dificuldade'] ?? 'médio';
    $categoria = $_POST['categoria'] ?? 'lógica de programação';
    $ordem = intval($_POST['ordem'] ?? $aula['ordem']);

    if ($titulo === '') {
        $erro = "Título obrigatório.";
    } else {
        $stmt = $pdo->prepare("UPDATE aulas SET titulo=?, descricao=?, video=?, tipo=?, dificuldade=?, categoria=?, ordem=? WHERE id=?");
        $res = $stmt->execute([$titulo, $descricao, $video, $tipo, $dificuldade, $categoria, $ordem, $id]);
        if ($res) {
            $sucesso = "Aula atualizada com sucesso.";
            // Recarregar dados atualizados
            $stmt = $pdo->prepare("SELECT * FROM aulas WHERE id = ?");
            $stmt->execute([$id]);
            $aula = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $erro = "Erro ao atualizar aula.";
        }
    }
}

function e($v) { 
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); 
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Aula - CodeGo</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="shortcut icon" href="logo.png" type="image/x-icon">
</head>
<body>
    <div class="main-container">
        <header>
            <nav>
                <a href="listar_aulas" class="back-link">
                    ← Voltar para Lista de Aulas
                </a>
                <a href="dashboard" class="logo">🚀 CodeGo</a>
            </nav>
        </header>

        <main>
            <div class="admin-form fade-in">
                <h1>✏️ Editar Aula</h1>
                <p class="text-center" style="color: white; font-size: 1.1rem; margin-bottom: 2rem;">
                    Modifique as informações da aula abaixo
                </p>

                <?php if ($erro): ?>
                    <div class="alert alert-error">
                        <?= e($erro) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($sucesso): ?>
                    <div class="alert alert-success">
                        <?= e($sucesso) ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-section">
                        <h3>📚 Informações Básicas</h3>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="titulo">📝 Título da Aula</label>
                                <input type="text" id="titulo" name="titulo" required 
                                       value="<?= e($aula['titulo']) ?>" 
                                       placeholder="Digite o título da aula">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="categoria">📂 Categoria</label>
                                <select id="categoria" name="categoria" required>
                                    <?php 
                                    $categorias = [
                                        'lógica de programação' => '🧠 Lógica de Programação',
                                        'front-end' => '🎨 Front-End',
                                        'back-end' => '⚙️ Back-End',
                                        'full-stack' => '🚀 Full Stack'
                                    ];
                                    foreach ($categorias as $valor => $nome): 
                                    ?>
                                        <option value="<?= $valor ?>" <?= $aula['categoria'] === $valor ? 'selected' : '' ?>>
                                            <?= $nome ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="tipo">🎯 Tipo de Exercício</label>
                                <select id="tipo" name="tipo" required>
                                    <option value="quiz" <?= $aula['tipo'] === 'quiz' ? 'selected' : '' ?>>
                                        ❓ Quiz (Múltipla Escolha)
                                    </option>
                                    <option value="codigo" <?= $aula['tipo'] === 'codigo' ? 'selected' : '' ?>>
                                        💻 Código (Programação)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="dificuldade">⭐ Dificuldade</label>
                                <select id="dificuldade" name="dificuldade" required>
                                    <?php 
                                    $dificuldades = [
                                        'extremamente fácil' => '⭐ Extremamente Fácil',
                                        'muito fácil' => '⭐⭐ Muito Fácil',
                                        'fácil' => '⭐⭐⭐ Fácil',
                                        'médio' => '⭐⭐⭐⭐ Médio',
                                        'difícil' => '⭐⭐⭐⭐⭐ Difícil',
                                        'muito difícil' => '⭐⭐⭐⭐⭐⭐ Muito Difícil',
                                        'extremamente difícil' => '⭐⭐⭐⭐⭐⭐⭐ Extremamente Difícil'
                                    ];
                                    foreach ($dificuldades as $valor => $nome): 
                                    ?>
                                        <option value="<?= $valor ?>" <?= $aula['dificuldade'] === $valor ? 'selected' : '' ?>>
                                            <?= $nome ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="ordem">📊 Ordem de Exibição</label>
                                <input type="number" id="ordem" name="ordem" min="1" 
                                       value="<?= e($aula['ordem']) ?>" 
                                       placeholder="1">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>📖 Conteúdo da Aula</h3>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="descricao">📄 Descrição/Enunciado</label>
                                <textarea id="descricao" name="descricao" rows="8" 
                                          placeholder="Descreva o exercício, explique os conceitos e forneça instruções claras..."><?= e($aula['descricao']) ?></textarea>
                            </div>
                        </div>

                        <div class="form-row full">
                            <div class="form-group">
                                <label for="video">🎥 Link do Vídeo (Opcional)</label>
                                <input type="url" id="video" name="video" 
                                       value="<?= e($aula['video']) ?>" 
                                       placeholder="https://youtube.com/watch?v=...">
                                <small style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                                    Cole o link do YouTube ou outro vídeo explicativo
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                            💾 Salvar Alterações
                        </button>
                        <a href="listar_aulas" class="btn btn-secondary" style="padding: 1rem 2rem; font-size: 1.1rem; margin-left: 1rem;">
                            ❌ Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
