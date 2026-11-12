<?php
// Variáveis esperadas:
// $token    string — token da URL
// $erro     string — mensagem de erro (opcional)
// $invalido bool   — link inexistente, vencido ou já usado

$authTitulo = 'Redefinir senha';
?>

<?php /* Com o link vencido, o título já dá o recado e o alerta explica o
         resto — um subtítulo no meio só repetiria a mesma frase. */ ?>
<h1 class="auth-titulo"><?= !empty($invalido) ? 'Link expirado' : 'Redefinir senha' ?></h1>

<?php if (empty($invalido)): ?>
    <p class="auth-subtitulo">Escolha uma nova senha para sua conta.</p>
<?php endif; ?>

<?php $erro = !empty($invalido) ? 'Este link é inválido ou já expirou. Peça a recuperação de novo.' : ($erro ?? ''); ?>
<?php if ($erro !== ''): ?>
    <div class="auth-alerta auth-alerta--erro<?= !empty($invalido) ? ' auth-alerta--solto' : '' ?>" role="alert">
        <?= icone('error') ?>
        <span><?= e($erro) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($invalido)): ?>

    <a href="./recuperar-senha" class="auth-botao">Solicitar novo link</a>

<?php else: ?>

    <form method="POST" action="./redefinir-senha">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">

        <div class="auth-campo">
            <label for="nova_senha">Nova senha</label>
            <div class="auth-input">
                <?= icone('lock') ?>
                <input type="password" id="nova_senha" name="nova_senha" placeholder="Mínimo de 6 caracteres" autocomplete="new-password" required minlength="6" autofocus>
                <button type="button" class="auth-olho" data-alvo="nova_senha" aria-label="Mostrar senha">
                    <?= icone('eye') ?>
                </button>
            </div>
        </div>

        <div class="auth-campo">
            <label for="confirmar_senha">Confirmar nova senha</label>
            <div class="auth-input">
                <?= icone('lock') ?>
                <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a nova senha" autocomplete="new-password" required minlength="6">
                <button type="button" class="auth-olho" data-alvo="confirmar_senha" aria-label="Mostrar senha">
                    <?= icone('eye') ?>
                </button>
            </div>
        </div>

        <button type="submit" class="auth-botao">Redefinir senha</button>
    </form>

    <script>
        // Mostrar/ocultar, campo a campo.
        document.querySelectorAll('.auth-olho').forEach(botao => {
            botao.addEventListener('click', () => {
                const campo = document.getElementById(botao.dataset.alvo);
                if (campo) {
                    campo.type = campo.type === 'password' ? 'text' : 'password';
                }
            });
        });
    </script>

<?php endif; ?>

<div class="auth-volta">
    <a href="./login">
        <?= icone('arrow-left') ?>
        Voltar para o login
    </a>
</div>

