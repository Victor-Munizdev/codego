<?php
session_start();
require 'conexao.php';

$GEMINI_API_KEY = 'AIzaSyAjxkQAHgDHLWAVImb7VfndZRXDrKqsxsU';
$GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';

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

if (!$usuario || $usuario['role'] !== 'admin') {
    die("Acesso negado.");
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

$aula_form = [
    'titulo' => '',
    'descricao' => '',
    'conteudo' => '',
    'tipo' => 'quiz',
    'dificuldade' => 'médio',
    'categoria' => 'lógica de programação',
    'ordem' => 1,
    'video' => '',
];

$erro = '';
$sucesso = '';
$aula_gerada = null;

$stmt = $pdo->query("SELECT MAX(ordem) FROM aulas");
$max_ordem = (int)$stmt->fetchColumn();
$proxima_ordem = $max_ordem + 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $dificuldade = $_POST['dificuldade'] ?? 'médio';
    $tipo = $_POST['tipo'] ?? 'quiz';
    $categoria = $_POST['categoria'] ?? 'lógica de programação';
    $ordem = intval($_POST['ordem'] ?? $proxima_ordem);

    $valores_dificuldade = ['extremamente fácil', 'muito fácil', 'fácil', 'médio', 'difícil', 'muito difícil', 'extremamente difícil'];
    $valores_tipo = ['quiz', 'codigo'];
    $valores_categoria = ['lógica de programação', 'front-end', 'back-end', 'full-stack'];

    if ($titulo === '') {
        $erro = "O título é obrigatório.";
    } elseif (!in_array($dificuldade, $valores_dificuldade)) {
        $erro = "Dificuldade inválida.";
    } elseif (!in_array($tipo, $valores_tipo)) {
        $erro = "Formato inválido.";
    } elseif (!in_array($categoria, $valores_categoria)) {
        $erro = "Categoria inválida.";
    }

    if (!$erro) {
        // Geração do conteúdo da aula
        $prompt_text = $tipo === 'quiz'
            ? "Crie um exercício no formato QUIZ com título '$titulo', dificuldade '$dificuldade' e categoria '$categoria'. Gere a descrição teórica do exercício e pelo menos 3 perguntas com múltiplas opções e uma resposta correta para cada. Não use código exato na resposta."
            : "Crie um exercício no formato CÓDIGO com título '$titulo', dificuldade '$dificuldade' e categoria '$categoria'. Gere a descrição teórica do exercício e um enunciado que permita ao usuário criar um código que será avaliado no final.";

        $prompt = [
            "contents" => [[
                "role" => "user",
                "parts" => [["text" => $prompt_text]]
            ]]
        ];

        // Requisição para gerar conteúdo
        $curl = curl_init($GEMINI_API_URL . '?key=' . $GEMINI_API_KEY);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($prompt));
        $response = curl_exec($curl);
        curl_close($curl);

        $response_data = json_decode($response, true);

        if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
            $gerado = $response_data['candidates'][0]['content']['parts'][0]['text'];
            $partes = explode("\n\n", $gerado, 2);
            $descricao = trim($partes[0]);
            $conteudo = isset($partes[1]) ? trim($partes[1]) : '';

            // 🔍 Prompt para buscar vídeo do YouTube
            $prompt_video = [
                "contents" => [[
                    "role" => "user",
                    "parts" => [[
                        "text" => "Me forneça um link direto de um vídeo do YouTube que ensine a teoria sobre o seguinte assunto: '$titulo', na área de '$categoria'. Retorne apenas a URL do vídeo, sem nenhum texto adicional."
                    ]]
                ]]
            ];

            $curl = curl_init($GEMINI_API_URL . '?key=' . $GEMINI_API_KEY);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($prompt_video));
            $response_video = curl_exec($curl);
            curl_close($curl);

            $video_url = '';
            $video_data = json_decode($response_video, true);
            if (isset($video_data['candidates'][0]['content']['parts'][0]['text'])) {
                $url = trim($video_data['candidates'][0]['content']['parts'][0]['text']);
                if (filter_var($url, FILTER_VALIDATE_URL) && preg_match('/youtu\.?be/', $url)) {
                    $video_url = $url;
                }
            }

            $aula_gerada = [
                'titulo' => $titulo,
                'descricao' => $descricao,
                'conteudo' => $conteudo,
                'tipo' => $tipo,
                'dificuldade' => $dificuldade,
                'categoria' => $categoria,
                'ordem' => $ordem,
                'video' => $video_url
            ];

            $stmt = $pdo->prepare("INSERT INTO aulas (titulo, descricao, tipo, conteudo, dificuldade, categoria, ordem, video, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $res = $stmt->execute([$titulo, $descricao, $tipo, $conteudo, $dificuldade, $categoria, $ordem, $video_url]);

            if ($res) {
                $sucesso = "Aula gerada e cadastrada com sucesso!";
                $aula_form = [
                    'titulo' => '',
                    'descricao' => '',
                    'conteudo' => '',
                    'tipo' => 'quiz',
                    'dificuldade' => 'médio',
                    'categoria' => 'lógica de programação',
                    'ordem' => $ordem + 1,
                    'video' => '',
                ];
                $proxima_ordem = $ordem + 1;
            } else {
                $erro = "Erro ao salvar a aula.";
            }
        } else {
            $erro = "Erro na geração do exercício pela IA.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Aula com IA - CodeGo</title>
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
            <div class="admin-form fade-in">
                <div class="text-center mb-3">
                    <h1> Cadastrar Aula com IA</h1>
                    <p style="color: white; font-size: 1.1rem;">
                        Crie exercícios automaticamente com inteligência artificial
                    </p>
                </div>

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

                <form method="POST">
                    <div class="form-section">
                        <h3>🎯 Configurações do Exercício</h3>
                        <p style="color: #6b7280; margin-bottom: 1.5rem;">
                            Defina os parâmetros básicos e a IA criará o conteúdo completo
                        </p>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="titulo">📝 Título do Exercício</label>
                                <input type="text" id="titulo" name="titulo" required 
                                       value="<?= e($aula_form['titulo']) ?>" 
                                       placeholder="Ex: Introdução às Variáveis em JavaScript">
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
                                        <option value="<?= $valor ?>" <?= $aula_form['categoria'] === $valor ? 'selected' : '' ?>>
                                            <?= $nome ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="tipo">🎯 Tipo de Exercício</label>
                                <select id="tipo" name="tipo" required>
                                    <option value="quiz" <?= $aula_form['tipo'] === 'quiz' ? 'selected' : '' ?>>
                                        ❓ Quiz (Múltipla Escolha)
                                    </option>
                                    <option value="codigo" <?= $aula_form['tipo'] === 'codigo' ? 'selected' : '' ?>>
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
                                        <option value="<?= $valor ?>" <?= $aula_form['dificuldade'] === $valor ? 'selected' : '' ?>>
                                            <?= $nome ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="ordem">📊 Ordem de Exibição</label>
                                <input type="number" id="ordem" name="ordem" min="1" 
                                       value="<?= $proxima_ordem ?>" 
                                       placeholder="<?= $proxima_ordem ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>🎥 Recursos Adicionais (Opcional)</h3>
                        
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="video">🎥 Link do Vídeo Explicativo</label>
                                <input type="url" id="video" name="video" 
                                       value="<?= e($aula_form['video']) ?>" 
                                       placeholder="https://youtube.com/watch?v=...">
                                <small style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                                    Cole o link do YouTube ou outro vídeo que complemente o exercício
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                            🤖 Gerar Exercício com IA
                        </button>
                        <a href="listar_aulas" class="btn btn-secondary" style="padding: 1rem 2rem; font-size: 1.1rem; margin-left: 1rem;">
                            📋 Ver Aulas Cadastradas
                        </a>
                    </div>
                </form>

                <?php if ($aula_gerada): ?>
                    <div class="preview-section">
                        <h4>👀 Prévia da Aula Gerada</h4>
                        <div style="background: white; padding: 1.5rem; border-radius: 8px; margin-top: 1rem;">
                            <h5 style="color: #4f46e5; margin-bottom: 1rem;">
                                <?= e($aula_gerada['titulo']) ?>
                            </h5>
                            <div style="color: #6b7280; line-height: 1.6;">
                                <?= nl2br(e(substr($aula_gerada['descricao'], 0, 300))) ?>
                                <?= strlen($aula_gerada['descricao']) > 300 ? '...' : '' ?>
                            </div>
                            <div style="margin-top: 1rem; font-size: 0.875rem; color: #9ca3af;">
                                <strong>Categoria:</strong> <?= e(ucfirst($aula_gerada['categoria'])) ?> | 
                                <strong>Tipo:</strong> <?= e(ucfirst($aula_gerada['tipo'])) ?> | 
                                <strong>Dificuldade:</strong> <?= e(ucfirst($aula_gerada['dificuldade'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Adicionar feedback visual ao gerar exercício
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '⏳ Gerando com IA...';
            button.disabled = true;
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
