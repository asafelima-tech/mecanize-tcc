<?php
/**
 * Bloco "Link do cliente" (portal) para as fichas do atendimento, do orçamento e da OS.
 *
 * Espera: $linkEscopo ('OCORRENCIA'|'ORCAMENTO'|'OS'), $linkEntidadeId (int).
 * O link bruto só aparece logo depois de gerado (na sessão, uma vez); depois disso só se vê o histórico dos links.
 */
require_once __DIR__ . '/../../models/PortalToken.php';

$ehe = fn($v) => htmlspecialchars((string) $v);

$linkGerado = $_SESSION['link_portal_gerado'] ?? null;
$linkResultado = $_SESSION['link_portal_resultado'] ?? null;

if ($linkGerado && ($linkGerado['escopo'] !== $linkEscopo || (int) $linkGerado['id'] !== (int) $linkEntidadeId)) {
    $linkGerado = null;
} else {
    unset($_SESSION['link_portal_gerado']);
}
unset($_SESSION['link_portal_resultado']);

$linksDoPortal = PortalToken::listarDaEntidade(new Database(), $linkEscopo, (int) $linkEntidadeId);
$ativos = count(array_filter($linksDoPortal, fn($t) => $t['situacao'] === 'ATIVO'));

$rotuloEnvio = [
    'ENVIADA' => 'Enviada ao cliente.', 'PENDENTE' => 'Na fila de envio.', 'FALHA' => 'O envio falhou — veja o motivo na conversa.',
    'NAO_CONFIGURADO' => 'Nenhum canal de envio está configurado: a mensagem ficou só registrada na conversa.',
    'SEM_CONSENTIMENTO' => 'O cliente não autorizou receber mensagens: a mensagem ficou só registrada. Gere o link e passe por outro meio.',
    'SEM_CONTATO' => 'O cliente não tem telefone nem e-mail válido.',
];
?>
<section class="card" id="link-cliente">
    <div class="card__header">
        <div class="card__title">Link do cliente</div>
        <span class="text-muted fs-12">Acompanhar, aprovar, autorizar e pagar sem login</span>
    </div>
    <div class="card__body" style="padding:12px 20px 16px;">
        <?php if ($linkGerado): ?>
            <div style="background:var(--color-success-soft);border:1px solid var(--color-success);border-radius:var(--radius-md);padding:10px 12px;margin-bottom:12px;">
                <div class="fs-12" style="font-weight:700;margin-bottom:6px;">Link gerado — copie agora, ele não aparece de novo:</div>
                <div class="form-field"><input type="text" readonly value="<?= $ehe($linkGerado['url']) ?>" aria-label="Link do cliente" style="font-family:monospace;font-size:12px;" onclick="this.select()"></div>
            </div>
        <?php endif; ?>
        <?php if ($linkResultado): ?>
            <p class="fs-12" style="margin:0 0 12px;font-weight:600;"><?= $ehe($rotuloEnvio[$linkResultado['status']] ?? $linkResultado['status']) ?></p>
        <?php endif; ?>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="post" action="./gerarLinkPortal">
                <?= csrf_field() ?>
                <input type="hidden" name="escopo" value="<?= $ehe($linkEscopo) ?>"><input type="hidden" name="id" value="<?= (int) $linkEntidadeId ?>">
                <button class="btn btn--ghost btn--sm" type="submit">Gerar link para copiar</button>
            </form>
            <form method="post" action="./enviarLinkPortal">
                <?= csrf_field() ?>
                <input type="hidden" name="escopo" value="<?= $ehe($linkEscopo) ?>"><input type="hidden" name="id" value="<?= (int) $linkEntidadeId ?>">
                <button class="btn btn--primary btn--sm" type="submit">Enviar link ao cliente</button>
            </form>
            <?php if ($ativos > 0): ?>
                <form method="post" action="./revogarLinksPortal" onsubmit="return confirm('Cortar todos os links ativos deste caso? O cliente precisará de um novo.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="escopo" value="<?= $ehe($linkEscopo) ?>"><input type="hidden" name="id" value="<?= (int) $linkEntidadeId ?>">
                    <button class="btn btn--danger-ghost btn--sm" type="submit">Revogar links</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($linksDoPortal): ?>
            <table class="table" style="margin-top:12px;font-size:12px;">
                <thead><tr><th>Gerado</th><th>Vale até</th><th>Acessos</th><th>Último acesso</th><th>Situação</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($linksDoPortal, 0, 6) as $t): ?>
                        <tr>
                            <td><?= date('d/m H:i', strtotime($t['criado_em'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($t['expira_em'])) ?></td>
                            <td><?= (int) $t['acessos'] ?></td>
                            <td><?= $t['ultimo_acesso_em'] ? date('d/m H:i', strtotime($t['ultimo_acesso_em'])) : '—' ?></td>
                            <td><span class="badge <?= $t['situacao'] === 'ATIVO' ? 'badge--success' : 'badge--neutral' ?>"><?= $ehe(['ATIVO' => 'Ativo', 'EXPIRADO' => 'Expirado', 'REVOGADO' => 'Revogado'][$t['situacao']] ?? $t['situacao']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted fs-12" style="margin:12px 0 0;">Nenhum link gerado ainda para este caso.</p>
        <?php endif; ?>
    </div>
</section>
