<?php
/**
 * Ficha da oficina (equipe do Mecanize), no padrão das fichas de consulta: identidade e situação no topo,
 * os números da licença e, em coluna única, o formulário de licença, o cadastro (duas colunas), os administradores
 * numa faixa de cartões e o histórico da licença.
 * O formulário, os campos e o destino são os mesmos de antes.
 * Espera: $of, $admins, $historico
 */
$titulo = $of['nome'];
$vencida = Licenca::vencida($of);
$dias = Licenca::diasRestantes($of);
$d = $of;
$nomesCampo = ['plano' => 'Plano', 'limite_usuarios' => 'Limite de usuários', 'status' => 'Situação', 'licenca_valida_ate' => 'Validade'];
$fmt = fn($k, $v) => $k === 'licenca_valida_ate' ? data_br((string) $v) : ($k === 'status' ? (Oficina::SITUACOES[$v] ?? $v) : ($k === 'plano' ? (Oficina::PLANOS[$v][0] ?? $v) : (string) $v));
$cor = ['TESTE' => 'neutral', 'ATIVA' => 'success', 'SUSPENSA' => 'danger', 'CANCELADA' => 'muted'];
$cancelada = $of['status'] === 'CANCELADA';
$acima = (int) $of['usuarios_ativos'] > (int) $of['limite_usuarios'];
$iniciais = function (string $nome): string { $p = preg_split('/\s+/', trim($nome)); return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : '')); };
?>
<header class="ver-topo">
  <div class="ver-topo__id">
    <span class="ver-topo__avatar ver-topo__avatar--quadrado" aria-hidden="true"><?= icone('dashboard', 30, 1.5) ?></span>
    <div class="ver-topo__texto">
      <p class="ver-topo__voltar"><a href="./plataforma"><?= icone('chevron-left', 14, 2) ?> Oficinas</a></p>
      <h1><?= e($of['nome']) ?></h1>
      <p class="ver-topo__meta">
        <span class="badge badge--<?= e($vencida && !$cancelada ? 'danger' : ($cor[$of['status']] ?? 'neutral')) ?>"><?= e($vencida && !$cancelada ? 'Vencida' : (Oficina::SITUACOES[$of['status']] ?? $of['status'])) ?></span>
        <span class="ver-chip"><?= icone('chart', 13) ?> <?= e(Oficina::PLANOS[$of['plano']][0] ?? $of['plano']) ?></span>
        <?php if ($of['cnpj']): ?><span class="ver-chip ver-chip--mono"><?= e(documento_fmt($of['cnpj'])) ?></span><?php endif; ?>
        <span class="ver-chip ver-chip--mono"><?= icone('calendar', 13) ?> Cadastrada em <?= data_br($of['criado_em']) ?></span>
      </p>
    </div>
  </div>
</header>

<?php if ($msg = Licenca::mensagem($of)) { $alertaTipo = Licenca::permiteEscrita($of) ? 'aviso' : 'erro'; $alertaMensagem = $msg; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

<div class="ver-numeros" aria-label="Licença">
  <div class="<?= $vencida && !$cancelada ? 'ver-numeros__alerta' : '' ?>">
    <span>Licença válida até</span><strong><?= data_br($of['licenca_valida_ate']) ?></strong>
    <small><?= $cancelada ? 'oficina cancelada' : ($vencida ? 'vencida' : ($dias === 0 ? 'vence hoje' : 'faltam ' . $dias . ' dia' . ($dias === 1 ? '' : 's'))) ?></small>
  </div>
  <div><span>Plano</span><strong><?= e(Oficina::PLANOS[$of['plano']][0] ?? $of['plano']) ?></strong><small>até <?= (int) (Oficina::PLANOS[$of['plano']][1] ?? $of['limite_usuarios']) ?> usuários no plano</small></div>
  <div class="<?= $acima ? 'ver-numeros__alerta' : '' ?>"><span>Usuários ativos</span><strong><?= (int) $of['usuarios_ativos'] ?> <span class="ver-numeros__de">de <?= (int) $of['limite_usuarios'] ?></span></strong><small><?= $acima ? 'acima do limite' : 'dentro do limite' ?></small></div>
  <div><span>Administradores</span><strong><?= count($admins) ?></strong><small><?= count(array_filter($admins, fn($a) => $a['ativo'])) === 1 ? '1 ativo' : count(array_filter($admins, fn($a) => $a['ativo'])) . ' ativos' ?></small></div>
</div>

<!-- Coluna única: licença, cadastro em duas colunas, administradores numa faixa de cartões e o histórico, tudo em largura cheia. -->
<div class="ver-principal ver-principal--cheia">
    <form class="card form-ficha__secao grupos" method="post" action="./plataforma-licenca?id=<?= (int) $of['id'] ?>" data-confirmar="Gravar a alteração da licença desta oficina?">
      <?= csrf_field() ?>
      <section class="grupo" aria-labelledby="g-licenca">
        <header class="grupo__cabeca"><span class="grupo__n grupo__n--escuro"><?= icone('key', 13, 2) ?></span><h2 id="g-licenca">Licença</h2></header>
        <div class="plataforma-licenca"><?php require __DIR__ . '/_licenca.php'; ?></div>
        <div class="form-field plataforma-motivo"><label for="motivo">Motivo / observação (obrigatório para cancelar)</label><input id="motivo" name="motivo" maxlength="255" placeholder="Ex.: pagamento de março confirmado"></div>
        <div class="plataforma-rodape">
          <p class="form-dica">Suspensa ou vencida: a oficina continua consultando e imprimindo, mas não grava nada. Cancelada: ninguém da oficina entra. Os dados nunca são apagados.</p>
          <button class="btn btn--primary"><?= icone('check') ?> Salvar licença</button>
        </div>
      </section>
    </form>

    <section class="card ver-dados" aria-labelledby="pl-cadastro">
      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo" id="pl-cadastro">Cadastro</h2>
        <dl class="ver-lista">
          <div><dt>CNPJ</dt><dd class="tabular"><?= $of['cnpj'] ? e(documento_fmt($of['cnpj'])) : '-' ?></dd></div>
          <div><dt>Cidade</dt><dd><?= e(trim(($of['cidade'] ?? '') . ($of['uf'] ? '/' . $of['uf'] : '')) ?: '-') ?></dd></div>
        </dl>
        <?php if ($acima): ?><p class="form-dica">Acima do limite: novos usuários ficam bloqueados.</p><?php endif; ?>
      </div>
      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo">Contato</h2>
        <dl class="ver-lista">
          <div><dt>Telefone</dt><dd class="tabular"><?= $of['telefone'] ? e(telefone_fmt($of['telefone'])) : '-' ?></dd></div>
          <div><dt>E-mail</dt><dd><?= e($of['email'] ?: '-') ?></dd></div>
        </dl>
      </div>
    </section>

    <section class="ver-secao" aria-labelledby="pl-admins">
      <header class="ver-secao__cabeca"><h2 id="pl-admins">Administradores</h2><span><?= count($admins) ?></span></header>
      <?php if (!$admins): ?>
        <div class="vazio cu-vazio"><?= icone('users', 28, 1.25) ?>Nenhum administrador.</div>
      <?php else: ?>
        <div class="ver-cartoes">
          <?php foreach ($admins as $a): ?>
            <div class="card ver-cartao cu-cartao<?= $a['ativo'] ? '' : ' is-inativo' ?>">
              <span class="avatar avatar--g" aria-hidden="true"><?= e($iniciais($a['nome'])) ?></span>
              <?php if (!$a['ativo']): ?><span class="badge badge--muted">inativo</span><?php endif; ?>
              <span class="ver-cartao__texto"><strong><?= e($a['nome']) ?></strong><small><?= e($a['email']) ?><?= $a['telefone'] ? ' · ' . e(telefone_fmt($a['telefone'])) : '' ?></small></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card card--tabela ver-historico" aria-labelledby="pl-hist">
      <header class="ver-historico__cabeca"><div><h2 id="pl-hist">Histórico da licença</h2><p>Cada mudança com o antes e o depois.</p></div></header>
      <?php if (!$historico): ?>
        <div class="vazio"><?= icone('history', 28, 1.25) ?>Sem registros.</div>
      <?php else: ?>
        <ol class="conta-linha-tempo os-linha-tempo">
          <?php foreach ($historico as $h): $dd = $h['dados'] ? json_decode($h['dados'], true) : []; ?>
            <li>
              <strong><?= $h['acao'] === 'OFICINA_CRIADA' ? 'Oficina cadastrada' : 'Licença alterada' ?></strong>
              <?php if ($h['acao'] === 'LICENCA'): ?>
                <?php foreach ($nomesCampo as $k => $r): if (!isset($dd[$k]['para'])) continue; ?>
                  <span class="os-linha-tempo__texto"><?= $r ?>: <?= e($fmt($k, $dd[$k]['de'])) ?> → <b><?= e($fmt($k, $dd[$k]['para'])) ?></b></span>
                <?php endforeach; ?>
              <?php endif; ?>
              <?php if ($h['motivo']): ?><span class="os-linha-tempo__texto">“<?= e($h['motivo']) ?>”</span><?php endif; ?>
              <small><?= data_br($h['criado_em'], true) ?><?= !empty($dd['por']) ? ' · ' . e($dd['por']) : '' ?></small>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
</div>
