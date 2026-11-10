<?php
// Variáveis esperadas:
// $mensagem string — exibida após o envio (sucesso genérico, POST)

$authTitulo = 'Recuperar senha';
?>

<?php /* No estado final o título já é o recado; repetir a instrução acima do
         alerta só empilharia duas frases dizendo a mesma coisa. */ ?>
<h1 class="auth-titulo"><?= !empty($mensagem) ? 'Verifique seu e-mail' : 'Recuperar senha' ?></h1>

<?php if (!empty($mensagem)): ?>

    <div class="auth-alerta auth-alerta--sucesso auth-alerta--solto" role="status">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.1V12a10 10 0 1 1-5.9-9.1" />
            <polyline points="22 4 12 14.01 9 11.01" />
        </svg>
        <span><?= e($mensagem) ?></span>
    </div>

<?php else: ?>

    <p class="auth-subtitulo">Informe o e-mail cadastrado para receber um link de redefinição.</p>


    <form method="POST" action="./recuperar-senha">
        <?= csrf_field() ?>

        <div class="auth-campo">
            <label for="email">E-mail</label>
            <div class="auth-input">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2" />
                    <path d="m22 7-10 6L2 7" />
                </svg>
                <input type="email" id="email" name="email" placeholder="seu@email.com" autocomplete="username" required autofocus>
            </div>
        </div>

        <button type="submit" class="auth-botao">Enviar link de recuperação</button>
    </form>

<?php endif; ?>

<div class="auth-volta">
    <a href="./login">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12" />
            <polyline points="12 19 5 12 12 5" />
        </svg>
        Voltar para o login
    </a>
</div>

