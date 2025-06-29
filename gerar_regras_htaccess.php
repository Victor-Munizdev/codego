<?php
// Caminho base (raiz do projeto)
$baseDir = __DIR__;

// Arquivos a serem ignorados
$ignorar = [
    'conexao.php',
    'gerar_regras_htaccess.php',
    '.htaccess'
];

// Inicializa o conteúdo do .htaccess
$htaccess = "RewriteEngine On\n";
$htaccess .= "RewriteBase /\n\n";
$htaccess .= "# Regras geradas automaticamente:\n";

// Percorre diretórios e arquivos recursivamente
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $arquivo) {
    // Ignora se não for arquivo .php
    if (pathinfo($arquivo, PATHINFO_EXTENSION) !== 'php') continue;

    // Caminho relativo ao diretório base
    $caminhoRelativo = str_replace($baseDir . '/', '', $arquivo);

    // Ignora arquivos definidos
    if (in_array(basename($caminhoRelativo), $ignorar)) continue;

    // Remove extensão .php para criar a regra
    $semExtensao = preg_replace('/\.php$/', '', $caminhoRelativo);

    // Converte para URL amigável (troca \ por / em Windows, remove index se quiser)
    $url = str_replace('\\', '/', $semExtensao);

    // Adiciona a regra ao .htaccess
    $htaccess .= "RewriteRule ^{$url}$ {$caminhoRelativo} [L,QSA]\n";
}

// Escreve ou sobrescreve o arquivo .htaccess
file_put_contents($baseDir . '/.htaccess', $htaccess);

echo "✅ Arquivo .htaccess gerado com sucesso com suporte a subpastas!\n";
