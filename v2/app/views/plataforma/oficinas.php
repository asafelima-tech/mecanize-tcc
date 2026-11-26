<?php
/**
 * Oficinas da plataforma (equipe do Mecanize): só dados de cadastro e licença, nada de dentro das oficinas.
 * Números do topo contados a partir da própria lista; tabela no padrão das listas do sistema.
 * Espera: $oficinas
 */
$titulo = 'Oficinas';
$cor = ['TESTE' => 'neutral', 'ATIVA' => 'success', 'SUSPENSA' => 'danger', 'CANCELADA' => 'muted'];
$conta = ['ATIVA' => 0, 'TESTE' => 0, 'atencao' => 0, 'usuarios' => 0];
foreach ($oficinas as $o) {
    $conta[$o['status']] = ($conta[$o['status']] ?? 0) + 1;
    $conta['usuarios'] += (int) $o['usuarios_ativos'];
    if ($o['status'] !== 'CANCELADA' && (Licenca::vencida($o) || $o['status'] === 'SUSPENSA' || Licenca::diasRestantes($o) <= Licenca::DIAS_AVISO)) {
        $conta['atencao']++;
    }
}
$iniciais = function (string $nome): string { $p = preg_split('/\s+/', trim($nome)); return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : '')); };
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Oficinas</h1>
    <p class="page-header__subtitle"><?= count($oficinas) ?> oficina<?= count($oficinas) === 1 ? '' : 's' ?> na plataforma · cadastro e licenças</p>
  </div>
  <div class="page-header__actions"><a class="btn btn--primary" href="./plataforma-nova"><?= icone('plus') ?> Nova oficina</a></div>
</div>

<div class="stat-grid stat-grid--icone">
  <div class="stat-card"><div class="stat-card__topo"><span class="stat-card__label">Ativas</span><?= icone('success') ?></div><span class="stat-card__value"><?= (int) $conta['ATIVA'] ?></span><span class="stat-card__pe">licença paga</span></div>
  <div class="stat-card"><div class="stat-card__topo"><span class="stat-card__label">Em teste</span><?= icone('clock') ?></div><span class="stat-card__value"><?= (int) $conta['TESTE'] ?></span><span class="stat-card__pe">período de avaliação</span></div>
  <div class="stat-card"><div class="stat-card__topo"><span class="stat-card__label">Pedem atenção</span><?= icone('warning') ?></div><span class="stat-card__value"><?= (int) $conta['atencao'] ?></span><span class="stat-card__pe">vencidas, suspensas ou vencendo em <?= Licenca::DIAS_AVISO ?> dias</span></div>
  <div class="stat-card"><div class="stat-card__topo"><span class="stat-card__label">Usuários ativos</span><?= icone('users') ?></div><span class="stat-card__value"><?= (int) $conta['usuarios'] ?></span><span class="stat-card__pe">somando todas as oficinas</span></div>
</div>

<div class="card card--tabela card--espaco">
  <?php if (!$oficinas): ?>
    <div class="vazio"><?= icone('dashboard', 32, 1.25) ?>Nenhuma oficina cadastrada ainda.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Oficina</th><th>Plano</th><th>Situação</th><th>Licença até</th><th class="num">Usuários</th><th><span class="sr-only">Abrir</span></th></tr></thead>
        <tbody>
          <?php foreach ($oficinas as $o): $vencida = Licenca::vencida($o); $dias = Licenca::diasRestantes($o); $link = './plataforma-oficina?id=' . (int) $o['id']; ?>
            <tr data-href="<?= $link ?>">
              <td><span class="celula-pessoa"><span class="icone-caixa plataforma-sigla" aria-hidden="true"><?= e($iniciais($o['nome'])) ?></span>
                <span><a href="<?= $link ?>" class="link-discreto"><strong><?= e($o['nome']) ?></strong></a><span class="sub"><?= e(implode(' · ', array_filter([$o['cnpj'] ? documento_fmt($o['cnpj']) : null, $o['email']])) ?: '-') ?></span></span></span></td>
              <td><?= e(Oficina::PLANOS[$o['plano']][0] ?? $o['plano']) ?></td>
              <td><span class="badge badge--<?= e($vencida && $o['status'] !== 'CANCELADA' ? 'danger' : ($cor[$o['status']] ?? 'neutral')) ?>"><?= e($vencida && $o['status'] !== 'CANCELADA' ? 'Vencida' : (Oficina::SITUACOES[$o['status']] ?? $o['status'])) ?></span></td>
              <td class="tabular"><?= data_br($o['licenca_valida_ate']) ?><?php if (!$vencida && $dias <= Licenca::DIAS_AVISO && $o['status'] !== 'CANCELADA'): ?><span class="sub">vence em <?= plural((int) $dias, 'dia', 'dias') ?></span><?php endif; ?></td>
              <td class="num"><strong><?= (int) $o['usuarios_ativos'] ?></strong> <span class="secundario">/ <?= (int) $o['limite_usuarios'] ?></span></td>
              <td class="num"><a class="btn btn--ghost btn--sm btn--icone" href="<?= $link ?>" aria-label="Abrir <?= e($o['nome']) ?>"><?= icone('chevron-right') ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
