<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Variáveis esperadas:
// $token         string — token da URL (obrigatório se o token for válido)
// $error         string — mensagem de erro (opcional)
// $tokenInvalido bool   — true se o token não existe/expirou/já foi usado

ob_start();
?>

<?php /* Com o link vencido, o título já dá o recado e o alerta explica o
         resto — um subtítulo no meio só repetiria a mesma frase. */ ?>
<h1 class="auth-titulo"><?= !empty($tokenInvalido) ? 'Link expirado' : 'Redefinir senha' ?></h1>

<?php if (empty($tokenInvalido)): ?>
    <p class="auth-subtitulo">Escolha uma nova senha para sua conta.</p>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="auth-alerta auth-alerta--erro<?= !empty($tokenInvalido) ? ' auth-alerta--solto' : '' ?>" role="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($tokenInvalido)): ?>

    <a href="./recuperar-senha" class="auth-botao">Solicitar novo link</a>

<?php else: ?>

    <form method="POST" action="./redefinir-senha">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

        <div class="auth-campo">
            <label for="nova_senha">Nova senha</label>
            <div class="auth-input">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <input type="password" id="nova_senha" name="nova_senha" placeholder="Mínimo de 6 caracteres" autocomplete="new-password" required minlength="6" autofocus>
                <button type="button" class="auth-olho" data-alvo="nova_senha" aria-label="Mostrar senha">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="auth-campo">
            <label for="confirmar_senha">Confirmar nova senha</label>
            <div class="auth-input">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a nova senha" autocomplete="new-password" required minlength="6">
                <button type="button" class="auth-olho" data-alvo="confirmar_senha" aria-label="Mostrar senha">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
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
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12" />
            <polyline points="12 19 5 12 12 5" />
        </svg>
        Voltar para o login
    </a>
</div>

<?php
$authConteudo = ob_get_clean();
$authTitulo   = 'Redefinir senha';
require __DIR__ . '/../layout/auth.php';
