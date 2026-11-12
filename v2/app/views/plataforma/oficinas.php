<?php
/** Espera: $oficinas. Tela da equipe do Mecanize: só dados de cadastro e licença, nada de dentro das oficinas. */
$titulo = 'Oficinas';
$cor = ['TESTE' => 'neutral', 'ATIVA' => 'success', 'SUSPENSA' => 'danger', 'CANCELADA' => 'muted'];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Oficinas</h1>
    <p class="page-header__subtitle"><?= count($oficinas) ?> oficina(s) na plataforma</p>
  </div>
  <a class="btn btn--primary" href="./plataforma-nova"><?= icone('plus') ?> Nova oficina</a>
</div>

<div class="card card--tabela">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Oficina</th><th>Plano</th><th>Situação</th><th>Licença até</th><th class="num">Usuários</th></tr></thead>
      <tbody>
        <?php foreach ($oficinas as $o): $vencida = Licenca::vencida($o); $dias = Licenca::diasRestantes($o); ?>
          <tr data-href="./plataforma-oficina?id=<?= (int) $o['id'] ?>">
            <td><a href="./plataforma-oficina?id=<?= (int) $o['id'] ?>"><?= e($o['nome']) ?></a><span class="sub"><?= e(implode(' · ', array_filter([$o['cnpj'] ? documento_fmt($o['cnpj']) : null, $o['email']]))) ?></span></td>
            <td><?= e(Oficina::PLANOS[$o['plano']][0] ?? $o['plano']) ?></td>
            <td><span class="badge badge--<?= e($vencida && $o['status'] !== 'CANCELADA' ? 'danger' : ($cor[$o['status']] ?? 'neutral')) ?>"><?= e($vencida && $o['status'] !== 'CANCELADA' ? 'Vencida' : (Oficina::SITUACOES[$o['status']] ?? $o['status'])) ?></span></td>
            <td><?= data_br($o['licenca_valida_ate']) ?><?php if (!$vencida && $dias <= Licenca::DIAS_AVISO && $o['status'] !== 'CANCELADA'): ?><span class="sub">vence em <?= $dias ?> dia(s)</span><?php endif; ?></td>
            <td class="num"><?= (int) $o['usuarios_ativos'] ?> / <?= (int) $o['limite_usuarios'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
