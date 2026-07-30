<?php
// Partial compartilhado por saveAgendamento.php e editAgendamento.php.
// Espera:
//   $clientes, $veiculos, $mecanicos  — de Agendamento::getDadosFormulario()
//   $valores  array — valores atuais do formulário (vazio no cadastro)
//   $ehEdicao bool  — mostra o campo de status só na edição

$valores  = $valores  ?? [];
$ehEdicao = $ehEdicao ?? false;

$v = fn($campo, $padrao = '') => htmlspecialchars((string) ($valores[$campo] ?? $padrao));

$statusLabel = [
    'AGENDADO'   => 'Agendado',
    'CONFIRMADO' => 'Confirmado',
    'CONCLUIDO'  => 'Concluído',
    'CANCELADO'  => 'Cancelado',
    'FALTOU'     => 'Não compareceu',
];

$duracaoLabel = [
    30  => '30 minutos',
    60  => '1 hora',
    90  => '1h30',
    120 => '2 horas',
    180 => '3 horas',
    240 => '4 horas',
    480 => 'Dia inteiro (8h)',
];
?>

<div class="form-grid">

    <div class="form-field">
        <label for="cliente_id">Cliente <span style="color:var(--color-danger)">*</span></label>
        <select name="cliente_id" id="cliente_id" required>
            <option value="">Selecione</option>
            <?php foreach ($clientes as $c): ?>
                <option value="<?= (int) $c['id_cliente'] ?>"
                    <?= (string) ($valores['cliente_id'] ?? '') === (string) $c['id_cliente'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-field">
        <label for="veiculo_id">Veículo</label>
        <select name="veiculo_id" id="veiculo_id">
            <option value="">Sem veículo definido</option>
            <?php foreach ($veiculos as $ve): ?>
                <option value="<?= (int) $ve['id_veiculo'] ?>"
                        data-cliente-id="<?= (int) $ve['cliente_id'] ?>"
                    <?= (string) ($valores['veiculo_id'] ?? '') === (string) $ve['id_veiculo'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(strtoupper($ve['placa']) . ' — ' . trim(($ve['marca'] ?? '') . ' ' . ($ve['modelo'] ?? ''))) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small style="display:block;margin-top:2px;font-size:12px;color:var(--color-text-muted);">A lista filtra pelos veículos do cliente escolhido.</small>
    </div>

    <div class="form-field">
        <label for="data">Data <span style="color:var(--color-danger)">*</span></label>
        <input type="date" name="data" id="data" value="<?= $v('data', date('Y-m-d')) ?>" required>
    </div>

    <div class="form-field">
        <label for="hora">Hora <span style="color:var(--color-danger)">*</span></label>
        <input type="time" name="hora" id="hora" value="<?= $v('hora', '08:00') ?>" required>
    </div>

    <div class="form-field">
        <label for="duracao_min">Duração prevista <span style="color:var(--color-danger)">*</span></label>
        <select name="duracao_min" id="duracao_min" required>
            <?php foreach ($duracaoLabel as $min => $rotulo): ?>
                <option value="<?= $min ?>" <?= (int) ($valores['duracao_min'] ?? 60) === $min ? 'selected' : '' ?>>
                    <?= $rotulo ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small style="display:block;margin-top:2px;font-size:12px;color:var(--color-text-muted);">Usada para avisar quando dois serviços se sobrepõem.</small>
    </div>

    <div class="form-field">
        <label for="mecanico_id">Mecânico responsável</label>
        <select name="mecanico_id" id="mecanico_id">
            <option value="">A definir</option>
            <?php foreach ($mecanicos as $m): ?>
                <option value="<?= (int) $m['id_usuario'] ?>"
                    <?= (string) ($valores['mecanico_id'] ?? '') === (string) $m['id_usuario'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-field" style="grid-column: span 2;">
        <label for="servico">Serviço <span style="color:var(--color-danger)">*</span></label>
        <input type="text" name="servico" id="servico" maxlength="160" required
               placeholder="Ex.: Revisão de 10.000 km, troca de pastilhas..."
               value="<?= $v('servico') ?>">
    </div>

    <?php if ($ehEdicao): ?>
        <div class="form-field">
            <label for="status">Status <span style="color:var(--color-danger)">*</span></label>
            <select name="status" id="status" required>
                <?php foreach ($statusLabel as $chave => $rotulo): ?>
                    <option value="<?= $chave ?>" <?= ($valores['status'] ?? '') === $chave ? 'selected' : '' ?>>
                        <?= $rotulo ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div class="form-field" style="grid-column: span <?= $ehEdicao ? '1' : '2' ?>;">
        <label for="observacoes">Observações</label>
        <input type="text" name="observacoes" id="observacoes" maxlength="500"
               placeholder="Opcional" value="<?= $v('observacoes') ?>">
    </div>

</div>

<script>
    (function () {
        // ── Filtra os veículos pelo cliente escolhido ──
        // O select é repovoado via innerHTML; o MutationObserver do
        // select-enhance.js detecta a troca de <option>s e reconstrói o
        // Tom Select sozinho, então não precisamos mexer nele aqui.
        const selCliente = document.getElementById('cliente_id');
        const selVeiculo = document.getElementById('veiculo_id');

        if (!selCliente || !selVeiculo) return;

        const todas = Array.from(selVeiculo.options).map(o => o.cloneNode(true));

        function filtrar(preservar) {
            const clienteId = selCliente.value;
            const anterior  = preservar ? selVeiculo.value : '';

            const permitidas = todas.filter(o =>
                o.value === '' || !clienteId || o.dataset.clienteId === clienteId
            );

            selVeiculo.innerHTML = '';
            permitidas.forEach(o => selVeiculo.appendChild(o.cloneNode(true)));

            // Só restaura se o veículo continuar pertencendo ao cliente.
            if (anterior && permitidas.some(o => o.value === anterior)) {
                selVeiculo.value = anterior;
            }
        }

        selCliente.addEventListener('change', () => filtrar(false));
        filtrar(true); // na abertura, mantém o veículo já gravado
    })();
</script>
