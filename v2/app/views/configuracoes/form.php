<?php
/**
 * Configurações (Design System, tela 24): menu de seções, dados da oficina com o logotipo, aparência
 * (tema deste navegador), assinatura e o histórico de alterações.
 * Espera: $of, $historico, $vagas, $erro
 */
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
    <h1 class="page-header__title">Configurações</h1>
    <p class="page-header__subtitle">Dados da oficina, aparência e assinatura. Os dados saem no cabeçalho do orçamento e da OS impressos.</p>
  </div>
</div>

<div class="config">
  <nav class="config__menu" aria-label="Seções">
    <a href="#oficina" class="ativa"><?= icone('home') ?> Oficina</a>
    <a href="#aparencia"><?= icone('eye') ?> Aparência</a>
    <a href="#assinatura"><?= icone('receipt') ?> Assinatura</a>
    <a href="#alteracoes"><?= icone('history') ?> Alterações</a>
  </nav>

  <div class="config__conteudo">
    <form class="card config__secao" id="oficina" method="post" action="./configuracoes-salvar" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <h2 class="card__titulo">Dados da oficina</h2>
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <div class="logo-config">
        <span class="logo-config__previa">
          <?php if (!empty($of['logo_caminho'])): ?><img src="./oficina-logo?v=<?= e(substr(md5($of['logo_caminho']), 0, 8)) ?>" alt="Logotipo atual"><?php else: ?><?= icone('image', 24) ?><?php endif; ?>
        </span>
        <span class="logo-config__texto"><strong>Logo da oficina</strong><small id="logo-nome">Aparece nos orçamentos, nas OS impressas e no link de acompanhamento. JPG, PNG ou WEBP até 5 MB.</small></span>
        <span class="logo-config__acoes">
          <label class="btn btn--contorno btn--sm"><?= icone('upload') ?> Enviar logo<input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="sr-only" id="logo-arquivo"></label>
          <?php if (!empty($of['logo_caminho'])): ?><label class="btn btn--fantasma btn--sm"><input type="checkbox" name="remover_logo" value="1"> Remover</label><?php endif; ?>
        </span>
      </div>

      <div class="form-row-2">
        <div class="form-field"><label for="nome">Nome da oficina *</label><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150"></div>
        <div class="form-field"><label for="cnpj">CNPJ</label><input id="cnpj" name="cnpj" value="<?= e(documento_fmt($of['cnpj'] ?? '')) ?>" data-mascara="cnpj" inputmode="numeric"></div>
      </div>
      <div class="form-row-2">
        <div class="form-field"><label for="telefone">Telefone / WhatsApp</label><span class="campo-icone"><?= icone('phone') ?><input id="telefone" name="telefone" value="<?= e(telefone_fmt($of['telefone'] ?? '')) ?>" data-mascara="telefone"></span></div>
        <div class="form-field"><label for="email">E-mail</label><span class="campo-icone"><?= icone('mail') ?><input id="email" name="email" type="email" value="<?= $val('email') ?>" maxlength="150"></span></div>
      </div>
      <div class="form-row-2">
        <div class="form-field"><label for="cep">CEP</label><input id="cep" name="cep" value="<?= e(($c = so_digitos($of['cep'] ?? '')) && strlen($c) === 8 ? substr($c, 0, 5) . '-' . substr($c, 5) : ($of['cep'] ?? '')) ?>" data-mascara="cep" inputmode="numeric"></div>
        <div class="form-field"><label for="endereco">Endereço</label><input id="endereco" name="endereco" value="<?= $val('endereco') ?>" maxlength="255" placeholder="Rua, número, bairro"></div>
      </div>
      <div class="form-row-3">
        <div class="form-field"><label for="cidade">Cidade</label><input id="cidade" name="cidade" value="<?= $val('cidade') ?>" maxlength="100"></div>
        <div class="form-field"><label for="uf">UF</label><input id="uf" name="uf" value="<?= $val('uf') ?>" maxlength="2" style="text-transform:uppercase"></div>
        <div class="form-field"><label for="horario">Horário de atendimento</label><input id="horario" name="horario" value="<?= $val('horario') ?>" maxlength="150" placeholder="Ex.: seg a sex, 8h às 18h"></div>
      </div>

      <div class="form-acoes">
        <a class="btn btn--ghost" href="./configuracoes">Descartar</a>
        <button class="btn btn--primary"><?= icone('check') ?> Salvar alterações</button>
      </div>
    </form>

    <div class="grade-2 grade-2--espaco">
      <section class="card config__secao" id="aparencia">
        <h2 class="card__titulo">Aparência</h2>
        <p class="form-dica">Vale para este navegador. Cada pessoa também pode trocar o tema na barra lateral.</p>
        <div class="temas" role="radiogroup" aria-label="Tema">
          <?php foreach (['light' => 'Claro', 'dark' => 'Escuro', 'auto' => 'Automático'] as $k => $r): ?>
            <label class="tema tema--<?= $k ?>">
              <span class="tema__previa" aria-hidden="true"><span></span><span></span></span>
              <span class="tema__nome"><input type="radio" name="tema_local" value="<?= $k ?>"> <?= $r ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card config__secao" id="assinatura">
        <h2 class="card__titulo">Assinatura</h2>
        <dl class="dados" style="grid-template-columns:1fr 1fr">
          <div><dt>Plano</dt><dd><?= e($planos[$of['plano'] ?? '']['0'] ?? ($of['plano'] ?? '')) ?></dd></div>
          <div><dt>Situação</dt><dd><?= e(Oficina::SITUACOES[$lic['status']] ?? $lic['status']) ?><?= Licenca::vencida($lic) ? ' · vencida' : '' ?></dd></div>
          <div><dt>Válida até</dt><dd><?= data_br($lic['licenca_valida_ate']) ?></dd></div>
          <div><dt>Usuários ativos</dt><dd><?= (int) $ativos ?> de <?= (int) $limite ?></dd></div>
        </dl>
        <p class="nota"><?= icone('info') ?><span>Para renovar, mudar de plano ou ampliar usuários, fale com o suporte do Mecanize.</span></p>
      </section>
    </div>

    <section class="card config__secao" id="alteracoes">
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
    </section>
  </div>
</div>

<script>
  (function () {
    // Tema deste navegador: a mesma chave da barra lateral; "auto" segue o sistema.
    var html = document.documentElement, salvo = null;
    try { salvo = localStorage.getItem('mecanize-theme'); } catch (e) {}
    var atual = salvo === 'dark' || salvo === 'light' ? salvo : 'auto';
    document.querySelectorAll('input[name=tema_local]').forEach(function (r) {
      r.checked = r.value === atual;
      r.addEventListener('change', function () {
        var escuro = r.value === 'dark' || (r.value === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        html.setAttribute('data-theme', escuro ? 'dark' : 'light');
        try { r.value === 'auto' ? localStorage.removeItem('mecanize-theme') : localStorage.setItem('mecanize-theme', r.value); } catch (e) {}
      });
    });

    // Nome do arquivo escolhido para o logotipo.
    var arq = document.getElementById('logo-arquivo');
    if (arq) arq.addEventListener('change', function () {
      if (arq.files[0]) document.getElementById('logo-nome').textContent = 'Novo logotipo: ' + arq.files[0].name + ' (salve para aplicar)';
    });

    // Seção ativa no menu ao rolar.
    var links = document.querySelectorAll('.config__menu a');
    links.forEach(function (a) { a.addEventListener('click', function () { links.forEach(function (b) { b.classList.remove('ativa'); }); a.classList.add('ativa'); }); });
  })();
</script>
