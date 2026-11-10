<?php
/** Espera: $d, $erro, $clientes, $fornecedores, $funcionarios */
$tipo = ($d['tipo'] ?? 'RECEBER') === 'PAGAR' ? 'PAGAR' : 'RECEBER';
$titulo = $tipo === 'PAGAR' ? 'Nova conta a pagar' : 'Nova conta a receber';
$val = fn($k) => e($d[$k] ?? '');
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./financeiro?tipo=<?= $tipo ?>">Financeiro</a><?= $tipo === 'RECEBER' ? ' · cobrança de OS é feita na própria OS' : '' ?></p>
  </div>
</div>
<form class="card form-card" method="post" action="./conta-salvar">
  <?= csrf_field() ?><input type="hidden" name="tipo" value="<?= $tipo ?>">
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>
  <div class="form-section">
    <div class="form-row-3">
      <div class="form-field span-2"><label for="descricao">Descrição *</label><input id="descricao" name="descricao" value="<?= $val('descricao') ?>" required maxlength="200" autofocus placeholder="<?= $tipo === 'PAGAR' ? 'Ex.: Aluguel de novembro' : 'Ex.: Venda de pneu usado' ?>"></div>
      <div class="form-field"><label for="categoria">Categoria *</label>
        <select id="categoria" name="categoria" required><?php foreach (Lancamento::CATEGORIAS[$tipo] as $k => $r): ?><option value="<?= $k ?>" <?= ($d['categoria'] ?? '') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row-4" style="margin-top:12px">
      <div class="form-field"><label for="valor">Valor total (R$) *</label><input id="valor" name="valor" value="<?= $val('valor') ?>" inputmode="decimal" required></div>
      <div class="form-field"><label for="parcelas">Parcelas</label><select id="parcelas" name="parcelas"><?php for ($i = 1; $i <= 12; $i++): ?><option <?= (int) ($d['parcelas'] ?? 1) === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></div>
      <div class="form-field"><label for="vencimento">1º vencimento *</label><input type="date" id="vencimento" name="vencimento" value="<?= $val('vencimento') ?>" required></div>
      <div class="form-field"><label for="competencia">Competência</label><input type="date" id="competencia" name="competencia" value="<?= $val('competencia') ?>"><span class="form-dica">Mês do resultado</span></div>
    </div>
  </div>
  <div class="form-section">
    <div class="form-row-3">
      <?php if ($tipo === 'RECEBER'): ?>
        <div class="form-field span-2"><label for="cliente_id">Cliente</label><select id="cliente_id" name="cliente_id"><option value="">—</option><?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($d['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select></div>
      <?php else: ?>
        <div class="form-field"><label for="fornecedor_id">Fornecedor</label><select id="fornecedor_id" name="fornecedor_id"><option value="">—</option><?php foreach ($fornecedores as $fo): ?><option value="<?= (int) $fo['id'] ?>" <?= (int) ($d['fornecedor_id'] ?? 0) === (int) $fo['id'] ? 'selected' : '' ?>><?= e($fo['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="funcionario_id">Funcionário</label><select id="funcionario_id" name="funcionario_id"><option value="">—</option><?php foreach ($funcionarios as $fu): ?><option value="<?= (int) $fu['id'] ?>" <?= (int) ($d['funcionario_id'] ?? 0) === (int) $fu['id'] ? 'selected' : '' ?>><?= e($fu['nome']) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="form-field"><label for="forma_prevista">Forma prevista</label><select id="forma_prevista" name="forma_prevista"><option value="">—</option><?php foreach (Lancamento::FORMAS as $k => $r): ?><option value="<?= $k ?>" <?= ($d['forma_prevista'] ?? '') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row-3" style="margin-top:12px">
      <div class="form-field"><label for="documento">Documento</label><input id="documento" name="documento" value="<?= $val('documento') ?>" maxlength="60" placeholder="Nº do boleto / nota"></div>
      <div class="form-field span-2"><label for="observacao">Observação</label><input id="observacao" name="observacao" value="<?= $val('observacao') ?>" maxlength="255"></div>
    </div>
  </div>
  <div class="form-acoes"><a class="btn btn--ghost" href="./financeiro?tipo=<?= $tipo ?>">Cancelar</a><button class="btn btn--primary">Lançar</button></div>
</form>
