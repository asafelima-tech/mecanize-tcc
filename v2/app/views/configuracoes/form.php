<?php
/** Espera: $of, $historico, $vagas, $erro */
$titulo = 'Configurações';
$val = fn($k) => e($of[$k] ?? '');
$lic = Contexto::oficina();
[$ativos, $limite] = $vagas;
$planos = Oficina::PLANOS;
$rotulosCampo = ['nome' => 'nome', 'cnpj' => 'CNPJ', 'email' => 'e-mail', 'telefone' => 'telefone', 'endereco' => 'endereço', 'cidade' => 'cidade', 'uf' => 'UF', 'cep' => 'CEP', 'horario' => 'horário',
                 'plano' => 'plano', 'limite_usuarios' => 'limite de usuários', 'status' => 'situação', 'licenca_valida_ate' => 'validade'];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Configurações da oficina</h1>
    <p class="page-header__subtitle">Os dados abaixo saem no cabeçalho do orçamento e da OS impressos.</p>
  </div>
</div>

<div class="ficha">
  <form class="card" method="post" action="./configuracoes-salvar" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>
    <div class="form-section">
      <div class="form-row-3">
        <div class="form-field span-2"><label for="nome">Nome da oficina *</label><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150"></div>
        <div class="form-field"><label for="cnpj">CNPJ</label><input id="cnpj" name="cnpj" value="<?= e(documento_fmt($of['cnpj'] ?? '')) ?>" data-mascara="cnpj" inputmode="numeric"></div>
      </div>
      <div class="form-row-3" style="margin-top:12px">
        <div class="form-field"><label for="telefone">Telefone</label><input id="telefone" name="telefone" value="<?= e(telefone_fmt($of['telefone'] ?? '')) ?>" data-mascara="telefone"></div>
        <div class="form-field span-2"><label for="email">E-mail</label><input id="email" name="email" type="email" value="<?= $val('email') ?>" maxlength="150"></div>
      </div>
      <div class="form-row-3" style="margin-top:12px">
        <div class="form-field span-3"><label for="endereco">Endereço</label><input id="endereco" name="endereco" value="<?= $val('endereco') ?>" maxlength="255" placeholder="Rua, número, bairro"></div>
      </div>
      <div class="form-row-3" style="margin-top:12px">
        <div class="form-field"><label for="cidade">Cidade</label><input id="cidade" name="cidade" value="<?= $val('cidade') ?>" maxlength="100"></div>
        <div class="form-field"><label for="uf">UF</label><input id="uf" name="uf" value="<?= $val('uf') ?>" maxlength="2" style="text-transform:uppercase"></div>
        <div class="form-field"><label for="cep">CEP</label><input id="cep" name="cep" value="<?= e(($c = so_digitos($of['cep'] ?? '')) && strlen($c) === 8 ? substr($c, 0, 5) . '-' . substr($c, 5) : ($of['cep'] ?? '')) ?>" data-mascara="cep" inputmode="numeric"></div>
      </div>
      <div class="form-field" style="margin-top:12px"><label for="horario">Horário de atendimento</label><input id="horario" name="horario" value="<?= $val('horario') ?>" maxlength="150" placeholder="Ex.: seg a sex, 8h às 18h · sáb, 8h às 12h"></div>
    </div>

    <div class="form-section" style="margin-top:16px">
      <label class="form-dica" style="font-weight:600">Logotipo (JPG, PNG ou WEBP, até 5 MB)</label>
      <div class="logo-linha">
        <?php if (!empty($of['logo_caminho'])): ?>
          <img src="./oficina-logo?v=<?= e(substr(md5($of['logo_caminho']), 0, 8)) ?>" alt="Logotipo atual" class="logo-previa">
          <label class="check"><input type="checkbox" name="remover_logo" value="1"> Remover o logotipo</label>
        <?php else: ?>
          <span class="form-dica">Sem logotipo: a impressão mostra só o nome.</span>
        <?php endif; ?>
        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
      </div>
    </div>

    <div class="form-acoes"><button class="btn btn--primary">Salvar configurações</button></div>
  </form>

  <div>
    <div class="card">
      <h2 class="card__titulo">Assinatura</h2>
      <dl class="dados" style="grid-template-columns:1fr 1fr">
        <div><dt>Plano</dt><dd><?= e($planos[$of['plano'] ?? '']['0'] ?? ($of['plano'] ?? '')) ?></dd></div>
        <div><dt>Situação</dt><dd><?= e(Oficina::SITUACOES[$lic['status']] ?? $lic['status']) ?><?= Licenca::vencida($lic) ? ' · vencida' : '' ?></dd></div>
        <div><dt>Válida até</dt><dd><?= data_br($lic['licenca_valida_ate']) ?></dd></div>
        <div><dt>Usuários ativos</dt><dd><?= (int) $ativos ?> de <?= (int) $limite ?></dd></div>
      </dl>
      <p class="form-dica" style="margin-top:12px">Para renovar, mudar de plano ou ampliar usuários, fale com o suporte do Mecanize.</p>
    </div>
    <div class="card">
      <h2 class="card__titulo">Alterações</h2>
      <?php if (!$historico): ?><p class="form-dica">Nenhuma alteração registrada.</p><?php endif; ?>
      <?php foreach ($historico as $h): $d = $h['dados'] ? json_decode($h['dados'], true) : []; ?>
        <div class="linha-os">
          <div class="linha-os__topo"><strong><?= ['CONFIGURACOES' => 'Configurações', 'LICENCA' => 'Licença', 'OFICINA_CRIADA' => 'Oficina cadastrada'][$h['acao']] ?? e($h['acao']) ?></strong>
            <span class="form-dica"><?= data_br($h['criado_em'], true) ?> · <?= e($h['autor'] ?? ($d['por'] ?? 'Mecanize')) ?></span></div>
          <div class="form-dica"><?= e($h['motivo'] ?? '') ?><?php
            $campos = array_filter(array_keys($d), fn($k) => is_array($d[$k]) && array_key_exists('para', $d[$k]));
            echo $campos ? ($h['motivo'] ? ' · ' : '') . e(implode(', ', array_map(fn($k) => $rotulosCampo[$k] ?? $k, $campos))) : ''; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
