<?php
/**
 * Avisos do usuário. Espera: $notificacoes, $soNaoLidas, $naoLidas.
 */
$e = fn($v) => htmlspecialchars((string) $v);
$soNaoLidas = $soNaoLidas ?? false;
$naoLidas = $naoLidas ?? 0;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Avisos</h1>
        <p class="page-header__subtitle"><?= $naoLidas > 0 ? (int) $naoLidas . ' não lido(s)' : 'Tudo em dia.' ?></p>
    </div>
    <div class="page-header__actions">
        <a href="./listNotificacoes<?= $soNaoLidas ? '' : '?nao_lidas=1' ?>" class="btn btn--ghost"><?= $soNaoLidas ? 'Ver todos' : 'Só não lidos' ?></a>
        <?php if ($naoLidas > 0): ?>
            <form action="./lerTodasNotificacoes" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--primary">Marcar todos como lidos</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<section class="card">
    <?php if (empty($notificacoes)): ?>
        <div class="card__body" style="padding:48px 20px;text-align:center;color:var(--color-text-muted);">Nenhum aviso.</div>
    <?php else: ?>
        <ul style="list-style:none;margin:0;padding:0;">
            <?php foreach ($notificacoes as $n): $lida = $n['lida_em'] !== null; ?>
                <li style="border-bottom:1px solid var(--color-border);">
                    <form action="./lerNotificacao" method="POST" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_notificacao" value="<?= (int) $n['id_notificacao'] ?>">
                        <button type="submit" style="display:block;width:100%;text-align:left;padding:14px 20px;background:<?= $lida ? 'transparent' : 'var(--color-primary-soft)' ?>;border:0;cursor:pointer;color:inherit;font:inherit;">
                            <div style="display:flex;justify-content:space-between;gap:12px;">
                                <strong style="font-size:13.5px;"><?= $e($n['titulo']) ?></strong>
                                <span class="text-muted fs-12" style="white-space:nowrap;"><?= date('d/m H:i', strtotime($n['criada_em'])) ?></span>
                            </div>
                            <?php if (!empty($n['mensagem'])): ?><div class="text-muted" style="font-size:12.5px;margin-top:2px;"><?= $e($n['mensagem']) ?></div><?php endif; ?>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
