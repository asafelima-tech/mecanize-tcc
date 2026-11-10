<?php /** Campos de plano, limite, situação e validade. Espera: $d */ ?>
<div class="form-row-4">
  <div class="form-field"><label for="plano">Plano</label>
    <select id="plano" name="plano">
      <?php foreach (Oficina::PLANOS as $k => [$r, $lim]): ?><option value="<?= $k ?>" data-limite="<?= $lim ?>" <?= ($d['plano'] ?? '') === $k ? 'selected' : '' ?>><?= e($r) ?> (até <?= $lim ?>)</option><?php endforeach; ?>
    </select></div>
  <div class="form-field"><label for="limite_usuarios">Limite de usuários</label><input id="limite_usuarios" name="limite_usuarios" type="number" min="1" max="500" value="<?= (int) ($d['limite_usuarios'] ?? 3) ?>"></div>
  <div class="form-field"><label for="status">Situação</label>
    <select id="status" name="status"><?php foreach (Oficina::SITUACOES as $k => $r): ?><option value="<?= $k ?>" <?= ($d['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
  <div class="form-field"><label for="licenca_valida_ate">Válida até</label><input id="licenca_valida_ate" name="licenca_valida_ate" type="date" value="<?= e($d['licenca_valida_ate'] ?? '') ?>" required>
    <span class="form-dica">Renovar: <a href="#" data-somar="1">+1 mês</a> · <a href="#" data-somar="12">+1 ano</a></span></div>
</div>
<script>
  (function () {
    var plano = document.getElementById('plano'), limite = document.getElementById('limite_usuarios'), validade = document.getElementById('licenca_valida_ate');
    plano.addEventListener('change', function () { limite.value = plano.selectedOptions[0].dataset.limite; });
    document.querySelectorAll('[data-somar]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        // Soma a partir da validade atual ou de hoje, o que for depois (renovar não "perde" dias).
        var hoje = new Date(); hoje.setHours(0, 0, 0, 0);
        var base = validade.value ? new Date(validade.value + 'T00:00:00') : hoje;
        if (base < hoje) base = hoje;
        base.setMonth(base.getMonth() + parseInt(a.dataset.somar, 10));
        validade.value = base.getFullYear() + '-' + String(base.getMonth() + 1).padStart(2, '0') + '-' + String(base.getDate()).padStart(2, '0');
      });
    });
  })();
</script>
