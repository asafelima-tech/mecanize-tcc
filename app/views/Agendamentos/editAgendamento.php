<?php
// Variáveis do controller: $clientes, $veiculos, $mecanicos, $agendamento, $error (opcional)
$ehEdicao = true;

// O banco guarda data_hora num campo só; o formulário separa data e hora.
// Quando a validação falha, o controller devolve o POST mesclado no registro —
// aí 'data'/'hora' já vêm prontos e não devem ser sobrescritos.
$valores = $agendamento;

if (!isset($valores['data']) && !empty($agendamento['data_hora'])) {
    $valores['data'] = date('Y-m-d', strtotime($agendamento['data_hora']));
    $valores['hora'] = date('H:i',   strtotime($agendamento['data_hora']));
}

$jaVirouOS = !empty($agendamento['ordem_servico_id']);
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Editar Agendamento</h1>
        <p class="page-header__subtitle">
            <?= htmlspecialchars($agendamento['cliente_nome'] ?? '') ?>
            <?php if (!empty($agendamento['veiculo_placa'])): ?>
                · <?= htmlspecialchars(strtoupper($agendamento['veiculo_placa'])) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="./listAgendamentos" class="btn btn--ghost">Voltar para a agenda</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div style="background:var(--color-danger-soft);color:#991B1B;border:1px solid #FECACA;border-radius:var(--radius-md);padding:12px 16px;margin-bottom:16px;font-size:13px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($jaVirouOS): ?>
    <div style="background:var(--color-success-soft);color:#166534;border:1px solid #BBF7D0;border-radius:var(--radius-md);padding:12px 16px;margin-bottom:16px;font-size:13px;">
        Este agendamento já virou a
        <a href="./viewOrdemServico?id=<?= (int) $agendamento['ordem_servico_id'] ?>" style="color:#166534;font-weight:600;">
            OS <?= htmlspecialchars($agendamento['os_numero'] ?? '') ?>
        </a>.
    </div>
<?php endif; ?>

<section class="card">
    <div class="card__body" style="padding:24px;">
        <form action="./editAgendamento" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="id_agendamento" value="<?= (int) $agendamento['id_agendamento'] ?>">

            <?php require __DIR__ . '/_formAgendamento.php'; ?>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;">
                <a href="./listAgendamentos" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar alterações</button>
            </div>
        </form>
    </div>
</section>
