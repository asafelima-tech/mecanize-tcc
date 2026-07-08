<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Variáveis esperadas:
// $mensagem string — exibida após o envio (sucesso genérico, POST)
// $error    string — erro inesperado (opcional)
?>

<section class="card" style="max-width:420px;margin:60px auto;">
    <div class="card__body" style="padding:28px;">
        <h1 style="font-size:20px;margin:0 0 8px;">Recuperar senha</h1>
        <p style="font-size:13px;color:var(--color-text-muted);margin:0 0 20px;">
            Informe o e-mail cadastrado para receber um link de redefinição de senha.
        </p>

        <?php if (!empty($mensagem)): ?>
            <div style="
                background: var(--color-success-soft);
                border-radius: var(--radius-md);
                padding: 12px 16px;
                margin-bottom: 20px;
                font-size: 13px;
            ">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

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

        <?php if (empty($mensagem)): ?>
            <form action="./recuperar-senha" method="POST">
                <div class="form-field" style="margin-bottom:18px;">
                    <label>E-mail</label>
                    <input type="email" name="email" required placeholder="seu@email.com">
                </div>

                <button type="submit" class="btn btn--primary" style="width:100%;justify-content:center;">
                    Enviar link de recuperação
                </button>
            </form>
        <?php endif; ?>

        <div style="margin-top:18px;text-align:center;">
            <a href="./login" class="login-link">Voltar para o login</a>
        </div>
    </div>
</section>
