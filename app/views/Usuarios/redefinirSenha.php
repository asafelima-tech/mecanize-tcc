<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Variáveis esperadas:
// $token         string — token da URL (obrigatório se o token for válido)
// $error         string — mensagem de erro (opcional)
// $tokenInvalido bool   — true se o token não existe/expirou/já foi usado
?>

<section class="card" style="max-width:420px;margin:60px auto;">
    <div class="card__body" style="padding:28px;">
        <h1 style="font-size:20px;margin:0 0 8px;">Redefinir senha</h1>

        <?php if (!empty($error)): ?>
            <div style="
                background: var(--color-danger-soft);
                color: #991B1B;
                border: 1px solid #FECACA;
                border-radius: var(--radius-md);
                padding: 12px 16px;
                margin-bottom: 20px;
                font-size: 13px;
            ">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($tokenInvalido)): ?>
            <div style="text-align:center;margin-top:12px;">
                <a href="./recuperar-senha" class="btn btn--primary" style="width:100%;justify-content:center;">
                    Solicitar novo link
                </a>
            </div>
        <?php else: ?>
            <p style="font-size:13px;color:var(--color-text-muted);margin:0 0 20px;">
                Escolha uma nova senha para sua conta.
            </p>

            <form action="./redefinir-senha" method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="form-field" style="margin-bottom:16px;">
                    <label>Nova senha</label>
                    <input type="password" name="nova_senha" required minlength="6">
                </div>

                <div class="form-field" style="margin-bottom:20px;">
                    <label>Confirmar nova senha</label>
                    <input type="password" name="confirmar_senha" required minlength="6">
                </div>

                <button type="submit" class="btn btn--primary" style="width:100%;justify-content:center;">
                    Redefinir senha
                </button>
            </form>
        <?php endif; ?>

        <div style="margin-top:18px;text-align:center;">
            <a href="./login" class="login-link">Voltar para o login</a>
        </div>
    </div>
</section>
