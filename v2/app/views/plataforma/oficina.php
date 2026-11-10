<?php
/** Espera: $of, $admins, $historico */
$titulo = $of['nome'];
$vencida = Licenca::vencida($of);
$d = $of;
$nomesCampo = ['plano' => 'Plano', 'limite_usuarios' => 'Limite de usuários', 'status' => 'Situação', 'licenca_valida_ate' => 'Validade'];
$fmt = fn($k, $v) => $k === 'licenca_valida_ate' ? data_br((string) $v) : ($k === 'status' ? (Oficina::SITUACOES[$v] ?? $v) : ($k === 'plano' ? (Oficina::PLANOS[$v][0] ?? $v) : (string) $v));
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= e($of['nome']) ?></h1>
    <p class="page-header__subtitle"><a href="./plataforma">Oficinas</a> · cadastrada em <?= data_br($of['criado_em']) ?></p>
  </div>
</div>

<?php if ($msg = Licenca::mensagem($of)) { $alertaTipo = Licenca::permiteEscrita($of) ? 'aviso' : 'erro'; $alertaMensagem = $msg; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

<div class="ficha">
  <div>
    <form class="card" method="post" action="./plataforma-licenca?id=<?= (int) $of['id'] ?>" data-confirmar="Gravar a alteração da licença desta oficina?">
      <?= csrf_field() ?>
      <h2 class="card__titulo">Licença</h2>
      <?php require __DIR__ . '/_licenca.php'; ?>
      <div class="form-field" style="margin-top:12px"><label for="motivo">Motivo / observação (obrigatório para cancelar)</label><input id="motivo" name="motivo" maxlength="255" placeholder="Ex.: pagamento de março confirmado"></div>
      <p class="form-dica" style="margin-top:8px">Suspensa ou vencida: a oficina continua consultando e imprimindo, mas não grava nada. Cancelada: ninguém da oficina entra. Os dados nunca são apagados.</p>
      <div class="form-acoes"><button class="btn btn--primary">Salvar licença</button></div>
    </form>

    <div class="card">
      <h2 class="card__titulo">Histórico da licença</h2>
      <?php if (!$historico): ?><p class="form-dica">Sem registros.</p><?php endif; ?>
      <?php foreach ($historico as $h): $dd = $h['dados'] ? json_decode($h['dados'], true) : []; ?>
        <div class="linha-os">
          <div class="linha-os__topo"><strong><?= $h['acao'] === 'OFICINA_CRIADA' ? 'Oficina cadastrada' : 'Licença alterada' ?></strong><span class="form-dica"><?= data_br($h['criado_em'], true) ?><?= !empty($dd['por']) ? ' · ' . e($dd['por']) : '' ?></span></div>
          <?php if ($h['acao'] === 'LICENCA'): ?>
            <ul><?php foreach ($nomesCampo as $k => $r): if (!isset($dd[$k]['para'])) continue; ?><li><?= $r ?>: <?= e($fmt($k, $dd[$k]['de'])) ?> → <?= e($fmt($k, $dd[$k]['para'])) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?php if ($h['motivo']): ?><div class="form-dica"><?= e($h['motivo']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <h2 class="card__titulo">Cadastro</h2>
      <dl class="dados" style="grid-template-columns:1fr">
        <div><dt>CNPJ</dt><dd><?= $of['cnpj'] ? e(documento_fmt($of['cnpj'])) : '—' ?></dd></div>
        <div><dt>Contato</dt><dd><?= e(implode(' · ', array_filter([$of['telefone'] ? telefone_fmt($of['telefone']) : null, $of['email']])) ?: '—') ?></dd></div>
        <div><dt>Cidade</dt><dd><?= e(trim(($of['cidade'] ?? '') . ($of['uf'] ? '/' . $of['uf'] : '')) ?: '—') ?></dd></div>
        <div><dt>Usuários ativos</dt><dd><?= (int) $of['usuarios_ativos'] ?> de <?= (int) $of['limite_usuarios'] ?><?= (int) $of['usuarios_ativos'] > (int) $of['limite_usuarios'] ? ' · acima do limite (novos usuários ficam bloqueados)' : '' ?></dd></div>
      </dl>
    </div>
    <div class="card">
      <h2 class="card__titulo">Administradores</h2>
      <?php foreach ($admins as $a): ?>
        <div class="linha-os"><strong><?= e($a['nome']) ?></strong><?= $a['ativo'] ? '' : ' <span class="badge badge--neutral">inativo</span>' ?><div class="form-dica"><?= e($a['email']) ?><?= $a['telefone'] ? ' · ' . e(telefone_fmt($a['telefone'])) : '' ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
