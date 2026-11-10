<?php /** Espera: $pecas, $d, $erro */ $titulo = 'Ajuste de estoque'; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Ajuste de estoque</h1>
    <p class="page-header__subtitle"><a href="./estoque">Estoque</a> · acertar o saldo pela contagem física</p>
  </div>
</div>

<form class="card form-card" method="post" action="./estoque-ajuste" style="max-width:640px">
  <?= csrf_field() ?>
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>
  <div class="form-field">
    <label for="produto_id">Peça *</label>
    <select id="produto_id" name="produto_id" required>
      <option value="">Escolha a peça</option>
      <?php foreach ($pecas as $p): ?>
        <option value="<?= (int) $p['id'] ?>" data-saldo="<?= e(rtrim(rtrim((string) $p['estoque_atual'], '0'), '.')) ?>" <?= (int) ($d['produto_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="form-dica" id="saldo-atual"></span>
  </div>
  <div class="form-field" style="margin-top:12px"><label for="contado">Quantidade contada na prateleira *</label><input id="contado" name="contado" value="<?= e($d['contado'] ?? '') ?>" inputmode="decimal" required></div>
  <div class="form-field" style="margin-top:12px"><label for="justificativa">Motivo *</label><input id="justificativa" name="justificativa" value="<?= e($d['justificativa'] ?? '') ?>" maxlength="255" required placeholder="Ex.: inventário do mês, peça avariada"></div>
  <div class="form-acoes"><a class="btn btn--ghost" href="./estoque">Cancelar</a><button class="btn btn--primary">Ajustar</button></div>
</form>
<script>
  (function () {
    var s = document.getElementById('produto_id'), t = document.getElementById('saldo-atual');
    var sync = function () { var o = s.options[s.selectedIndex]; t.textContent = o && o.dataset.saldo !== undefined ? 'Saldo no sistema: ' + o.dataset.saldo : ''; };
    s.addEventListener('change', sync); sync();
  })();
</script>
