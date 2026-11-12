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
        <?= icone('success') ?>
        <span><?= e($mensagem) ?></span>
    </div>

<?php else: ?>

    <p class="auth-subtitulo">Informe o e-mail cadastrado para receber um link de redefinição.</p>


    <form method="POST" action="./recuperar-senha">
        <?= csrf_field() ?>

        <div class="auth-campo">
            <label for="email">E-mail</label>
            <div class="auth-input">
                <?= icone('mail') ?>
                <input type="email" id="email" name="email" placeholder="seu@email.com" autocomplete="username" required autofocus>
            </div>
        </div>

        <button type="submit" class="auth-botao">Enviar link de recuperação</button>
    </form>

<?php endif; ?>

<div class="auth-volta">
    <a href="./login">
        <?= icone('arrow-left') ?>
        Voltar para o login
    </a>
</div>

