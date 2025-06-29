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

if (!isset($_GET['id'])) {
    die("Aula não especificada.");
}

$aula_id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM aulas WHERE id = ?");
$stmt->execute([$aula_id]);
$aula = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aula) {
    die("Aula não encontrada.");
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Verificar se o usuário já completou esta aula corretamente
$stmt = $pdo->prepare("
    SELECT correta, resposta, data_hora 
    FROM respostas_aulas 
    WHERE usuario_id = ? AND aula_id = ? 
    ORDER BY data_hora DESC 
    LIMIT 1
");
$stmt->execute([$usuario_id, $aula_id]);
$ultima_resposta = $stmt->fetch(PDO::FETCH_ASSOC);

$ja_completou = $ultima_resposta && $ultima_resposta['correta'] == 1;

$opcoes = [];
if ($aula['tipo'] === 'quiz') {
    if ($aula['conteudo']) {
        $opcoes_decoded = json_decode($aula['conteudo'], true);
        if (is_array($opcoes_decoded) && count($opcoes_decoded) > 0) {
            $opcoes = $opcoes_decoded;
        }
    }

    // Se não há opções válidas, gerar via IA
    if (count($opcoes) === 0) {
        $GEMINI_API_KEY = 'AIzaSyAjxkQAHgDHLWAVImb7VfndZRXDrKqsxsU';
        $GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';

        $prompt_text = "Crie um quiz de múltipla escolha para o exercício:\n"
            . "Título: " . $aula['titulo'] . "\n"
            . "Descrição: " . $aula['descricao'] . "\n"
            . "Categoria: " . $aula['categoria'] . "\n\n"
            . "Retorne APENAS um JSON válido no formato:\n"
            . '[\n'
            . '  {"opcao": "Opção A", "correta": true},\n'
            . '  {"opcao": "Opção B", "correta": false},\n'
            . '  {"opcao": "Opção C", "correta": false},\n'
            . '  {"opcao": "Opção D", "correta": false}\n'
            . ']\n'
            . "Crie 4 opções, sendo apenas 1 correta. Não adicione texto extra, apenas o JSON.";

        $prompt = [
            "contents" => [[
                "role" => "user",
                "parts" => [["text" => $prompt_text]]
            ]]
        ];

        $curl = curl_init($GEMINI_API_URL . '?key=' . $GEMINI_API_KEY);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($prompt));
        $response = curl_exec($curl);
        curl_close($curl);

        $response_data = json_decode($response, true);

        if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
            $texto_gerado = trim($response_data['candidates'][0]['content']['parts'][0]['text']);
            
            // Tentar extrair JSON do texto
            $json_encontrado = null;
            if (preg_match('/(\[.*\])/s', $texto_gerado, $matches)) {
                $json_encontrado = $matches[1];
            } else {
                // Se não encontrou com regex, tentar o texto completo
                $json_encontrado = $texto_gerado;
            }

            if ($json_encontrado) {
                $opcoes_extraidas = json_decode($json_encontrado, true);
                if (is_array($opcoes_extraidas) && count($opcoes_extraidas) > 0) {
                    // Validar estrutura das opções
                    $opcoes_validas = true;
                    foreach ($opcoes_extraidas as $opcao) {
                        if (!isset($opcao['opcao']) || !isset($opcao['correta'])) {
                            $opcoes_validas = false;
                            break;
                        }
                    }
                    
                    if ($opcoes_validas) {
                        $opcoes = $opcoes_extraidas;
                        $json_encode = json_encode($opcoes);
                        $stmt = $pdo->prepare("UPDATE aulas SET conteudo = ? WHERE id = ?");
                        $stmt->execute([$json_encode, $aula_id]);
                    }
                }
            }
        }
    }
}

$resposta_usuario = null;
$resultado = null;
$tentar_novamente = false;
$correta = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tentar_novamente'])) {
        $tentar_novamente = true;
    } else {
        $resposta_usuario = trim($_POST['resposta'] ?? '');

        if ($aula['tipo'] === 'quiz') {
            $encontrou = false;
            $correta = false;
            
            foreach ($opcoes as $op) {
                if (isset($op['opcao']) && $op['opcao'] === $resposta_usuario) {
                    $correta = (bool)($op['correta'] ?? false);
                    $encontrou = true;
                    break;
                }
            }
            
            if ($encontrou) {
                if ($correta) {
                    $resultado = "🎉 Correto! Parabéns, você acertou!";
                } else {
                    $resultado = "❌ Incorreto. Tente novamente para aprender mais!";
                }
            } else {
                $resultado = "⚠️ Resposta inválida. Por favor, selecione uma das opções.";
                $correta = false;
            }
        } else {
            // Exercício de código - avaliação via IA
            $GEMINI_API_KEY = 'AIzaSyAjxkQAHgDHLWAVImb7VfndZRXDrKqsxsU';
            $GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';

            $considerar_frontend = ($aula['categoria'] === 'front-end');

            $prompt_text = "Avalie se o código abaixo resolve corretamente o exercício:\n\n"
                . "EXERCÍCIO:\n"
                . "Título: " . $aula['titulo'] . "\n"
                . "Descrição: " . $aula['descricao'] . "\n"
                . "Categoria: " . $aula['categoria'] . "\n\n"
                . "CÓDIGO DO ALUNO:\n" . $resposta_usuario . "\n\n"
                . "INSTRUÇÕES DE AVALIAÇÃO:\n";
            
            if ($considerar_frontend) {
                $prompt_text .= "- Este é um exercício de FRONT-END\n"
                    . "- Avalie apenas HTML, CSS e JavaScript\n"
                    . "- Não exija conexão com banco de dados ou backend\n"
                    . "- Foque na funcionalidade visual e comportamental\n";
            } else {
                $prompt_text .= "- Avalie a lógica de programação completa\n"
                    . "- Considere boas práticas de código\n"
                    . "- Verifique se resolve o problema proposto\n";
            }
            
            $prompt_text .= "\nRESPONDA EXATAMENTE no formato:\n"
                . "RESULTADO: [Correto/Incorreto]\n"
                . "EXPLICAÇÃO: [Explicação detalhada do resultado]";

            $prompt = [
                "contents" => [[
                    "role" => "user",
                    "parts" => [["text" => $prompt_text]]
                ]]
            ];

            $curl = curl_init($GEMINI_API_URL . '?key=' . $GEMINI_API_KEY);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($prompt));
            $response = curl_exec($curl);
            curl_close($curl);

            $response_data = json_decode($response, true);
            if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
                $resultado_ia = trim($response_data['candidates'][0]['content']['parts'][0]['text']);
                
                // Verificar se a resposta contém "Correto" de forma mais rigorosa
                if (preg_match('/RESULTADO:\s*(Correto|correto)/i', $resultado_ia)) {
                    $correta = true;
                    $resultado = "🎉 " . $resultado_ia;
                } else if (preg_match('/RESULTADO:\s*(Incorreto|incorreto)/i', $resultado_ia)) {
                    $correta = false;
                    $resultado = "❌ " . $resultado_ia;
                } else {
                    // Fallback para análise mais simples
                    $correta = stripos($resultado_ia, 'correto') !== false && stripos($resultado_ia, 'incorreto') === false;
                    $resultado = ($correta ? "🎉 " : "❌ ") . $resultado_ia;
                }
            } else {
                $resultado = "⚠️ Erro ao avaliar o código. Tente novamente.";
                $correta = false;
            }
        }

        // Salvar resposta no banco de dados
        $stmt = $pdo->prepare("INSERT INTO respostas_aulas (usuario_id, aula_id, resposta, correta, data_hora) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$usuario_id, $aula_id, $resposta_usuario, $correta ? 1 : 0]);
        
        // Atualizar status de conclusão
        if ($correta) {
            $ja_completou = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($aula['titulo']) ?> - CodeGo</title>
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
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <h1 style="margin: 0;"><?= e($aula['titulo']) ?></h1>
                        <?php if ($ja_completou): ?>
                            <div style="background: #10b981; color: white; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.875rem;">
                                ✅ Concluído
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="background: #f8fafc; color:black; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <strong>📂 Categoria:</strong> <?= e(ucfirst($aula['categoria'])) ?>
                        <span style="margin-left: 1rem;"><strong>⭐ Dificuldade:</strong> <?= e(ucfirst($aula['dificuldade'])) ?></span>
                    </div>
                    
                    <div style="line-height: 1.6; margin-bottom: 2rem; color: #374151;">
                        <?= nl2br(e($aula['descricao'])) ?>
                    </div>
                </div>

                <?php if (!empty($aula['video'])): ?>
                    <div class="video-container">
                        <h3 style="margin-bottom: 1rem;">📹 Vídeo da Aula</h3>
                        <?php
                        if (preg_match('/youtube\.com|youtu\.be/', $aula['video'])) {
                            preg_match('/(youtu\.be\/|v=)([a-zA-Z0-9_-]+)/', $aula['video'], $matches);
                            $video_id = $matches[2] ?? null;
                            if ($video_id):
                        ?>
                            <iframe src="https://www.youtube.com/embed/<?= e($video_id) ?>" 
                                    frameborder="0" allowfullscreen></iframe>
                        <?php else: ?>
                            <p>Link de vídeo inválido.</p>
                        <?php endif; 
                        } else {
                            echo '<a href="' . e($aula['video']) . '" target="_blank" class="btn btn-primary">Assista o vídeo</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <h3 style="margin-bottom: 1.5rem; color: #374151;">
                        <?= $aula['tipo'] === 'quiz' ? '❓ Quiz' : '💻 Exercício de Código' ?>
                    </h3>

                    <?php if ($ja_completou && !$tentar_novamente && $resultado === null): ?>
                        <div class="alert alert-success">
                            <p><strong>🎉 Parabéns!</strong> Você já completou esta aula com sucesso!</p>
                            <p>Última resposta em: <?= date('d/m/Y H:i', strtotime($ultima_resposta['data_hora'])) ?></p>
                        </div>
                        <form method="post">
                            <input type="hidden" name="tentar_novamente" value="1">
                            <button type="submit" class="btn btn-secondary">
                                🔄 Fazer Novamente
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post">
                            <?php if (!$tentar_novamente): ?>
                                <?php if (count($opcoes) > 0): ?>
                                    <div class="quiz-options">
                                        <?php foreach ($opcoes as $op): ?>
                                            <label class="quiz-option">
                                                <input type="radio" name="resposta" value="<?= e($op['opcao']) ?>" required>
                                                <span><?= e($op['opcao']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($aula['tipo'] === 'codigo'): ?>
                                    <label for="resposta" style="display: block; margin-bottom: 1rem; font-weight: 600;">
                                        Digite seu código abaixo:
                                    </label>
                                    <?php include("editor.php"); ?>
                                <?php else: ?>
                                    <div class="alert alert-error">
                                        <p>⚠️ Nenhuma atividade definida para esta aula.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (count($opcoes) > 0 || $aula['tipo'] === 'codigo'): ?>
                                    <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">
                                        🚀 Enviar Resposta
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <p>✨ Você pode tentar novamente!</p>
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($resultado !== null): ?>
                    <div class="card">
                        <h3 style="margin-bottom: 1rem; color: #374151;">📊 Resultado</h3>
                        <div style="background: <?= $correta ? '#f0fdf4' : '#fef2f2' ?>; border: 2px solid <?= $correta ? '#bbf7d0' : '#fecaca' ?>; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; color: #374151;">
                            <?= nl2br(e($resultado)) ?>
                        </div>
                        
                        <?php if ($correta): ?>
                            <div style="text-align: center; margin-bottom: 1.5rem;">
                                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎉</div>
                                <p style="color: #10b981; font-weight: 600; font-size: 1.1rem;">
                                    Excelente! Você completou esta aula!
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 1rem; justify-content: center;">
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="tentar_novamente" value="1">
                                <button type="submit" class="btn btn-secondary">
                                    🔄 Tentar Novamente
                                </button>
                            </form>
                            
                            <?php if ($correta): ?>
                                <a href="dashboard" class="btn btn-primary">
                                    📚 Próximas Aulas
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
