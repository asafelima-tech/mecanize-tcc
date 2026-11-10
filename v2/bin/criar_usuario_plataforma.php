<?php

/**
 * Cria (ou reativa) um usuário da PLATAFORMA — a equipe do Mecanize, que cadastra oficinas e
 * licenças e não enxerga dados de dentro das oficinas.
 *
 * USO: C:\xampp\php\php.exe v2\bin\criar_usuario_plataforma.php "Nome" email@exemplo.com
 * A senha é pedida no terminal (não fica no histórico de comandos).
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

[$script, $nome, $email] = array_pad($argv, 3, null);

if (!$nome || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php {$script} \"Nome\" email@exemplo.com\n");
    exit(1);
}

echo 'Senha (mínimo 8 caracteres): ';
$senha = trim((string) fgets(STDIN));

if (mb_strlen($senha) < 8) {
    fwrite(STDERR, "Senha curta demais.\n");
    exit(1);
}

$db = Banco::conexao();
$existente = $db->prepare('SELECT id, perfil FROM usuarios WHERE email = ?');
$existente->execute([mb_strtolower($email)]);
$linha = $existente->fetch();

if ($linha && $linha['perfil'] !== 'PLATAFORMA') {
    fwrite(STDERR, "Este e-mail já é de um usuário de oficina. Use outro.\n");
    exit(1);
}

if ($linha) {
    $db->prepare('UPDATE usuarios SET nome = ?, senha_hash = ?, ativo = 1, atualizado_em = NOW() WHERE id = ?')
       ->execute([$nome, password_hash($senha, PASSWORD_DEFAULT), $linha['id']]);
    echo "Usuário da plataforma atualizado ({$email}).\n";
} else {
    $db->prepare("INSERT INTO usuarios (oficina_id, nome, email, senha_hash, perfil) VALUES (NULL, ?, ?, ?, 'PLATAFORMA')")
       ->execute([$nome, mb_strtolower($email), password_hash($senha, PASSWORD_DEFAULT)]);
    echo "Usuário da plataforma criado ({$email}). Banco: " . Banco::nome() . "\n";
}
