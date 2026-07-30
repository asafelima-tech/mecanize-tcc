<?php
// Variáveis do controller: $clientes, $veiculos, $mecanicos, $old (opcional), $error (opcional)
$valores  = $old ?? [];
$ehEdicao = false;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Novo Agendamento</h1>
        <p class="page-header__subtitle">Marque um horário para o cliente antes de abrir a Ordem de Serviço.</p>
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

<section class="card">
    <div class="card__body" style="padding:24px;">
        <form action="./saveAgendamento" method="POST">
            <?= csrf_field() ?>

            <?php require __DIR__ . '/_formAgendamento.php'; ?>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;">
                <a href="./listAgendamentos" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar agendamento</button>
            </div>
        </form>
    </div>
</section>
