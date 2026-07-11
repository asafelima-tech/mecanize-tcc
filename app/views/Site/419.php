<!-- 419: token CSRF ausente/inválido — geralmente sessão expirada ou formulário
     aberto há muito tempo em outra aba, não necessariamente um ataque. -->
<div style="display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 62px);padding:40px 20px;">
    <div class="card" style="max-width:460px;width:100%;text-align:center;padding:48px 36px;">

        <div style="width:84px;height:84px;border-radius:var(--radius-xl);background:var(--color-warning-soft,#FFFBEB);color:var(--color-warning,#B45309);display:flex;align-items:center;justify-content:center;margin:0 auto 28px;font-size:34px;box-shadow:var(--shadow-sm);">
            <i class="bi bi-arrow-clockwise"></i>
        </div>

        <div style="font-size:19px;font-weight:700;letter-spacing:-0.01em;color:var(--color-text);margin-bottom:8px;">
            A página expirou
        </div>

        <p style="font-size:13px;color:var(--color-text-muted);line-height:1.7;margin-bottom:30px;">
            Esse formulário ficou aberto por muito tempo, ou você saiu e entrou de novo em outra aba.<br>
            Atualize a página e tente enviar novamente.
        </p>

        <div style="display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;">
            <a href="javascript:history.back()" class="btn btn--ghost">
                <span class="ico-wrap"><i class="bi bi-arrow-left"></i></span> Voltar
            </a>
            <a href="./home" class="btn btn--primary">
                <span class="ico-wrap"><i class="bi bi-house-door"></i></span> Ir para o início
            </a>
        </div>

    </div>
</div>
