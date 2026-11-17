<?php
/**
 * Configurações: uma coluna só, no estilo dos Ajustes do sistema.
 *   1. Cartão de visita da oficina: logotipo, nome e a linha de dados exatamente como sai no orçamento e na OS
 *      impressos (atualiza enquanto se digita: é a prévia do que se está configurando).
 *   2. Listas agrupadas (rótulo à esquerda, valor à direita, editado na própria linha): Oficina, Endereço.
 *   3. Aparência (vale na hora) e Plano (só consulta).
 *   4. Histórico de alterações recolhido numa linha.
 * O "Salvar" só aparece, numa barra flutuante, quando algo mudou.
 * Espera: $of, $historico, $vagas, $erro
 */
$titulo = 'Configurações';
$val = fn($k) => e($of[$k] ?? '');
$lic = Contexto::oficina();
[$ativos, $limite] = $vagas;
$planos = Oficina::PLANOS;
$rotulosCampo = ['nome' => 'nome', 'cnpj' => 'CNPJ', 'email' => 'e-mail', 'telefone' => 'telefone', 'endereco' => 'endereço', 'cidade' => 'cidade', 'uf' => 'UF', 'cep' => 'CEP', 'horario' => 'horário',
                 'plano' => 'plano', 'limite_usuarios' => 'limite de usuários', 'status' => 'situação', 'licenca_valida_ate' => 'validade'];
$cep = ($c = so_digitos($of['cep'] ?? '')) && strlen($c) === 8 ? substr($c, 0, 5) . '-' . substr($c, 5) : ($of['cep'] ?? '');
$temLogo = !empty($of['logo_caminho']);
$diasLicenca = (int) floor((strtotime($lic['licenca_valida_ate'] . ' 23:59:59') - time()) / 86400);
$vencida = Licenca::vencida($lic);
$usoPct = $limite > 0 ? min(100, round($ativos / $limite * 100)) : 0;
$nomesAcao = ['CONFIGURACOES' => 'Dados da oficina alterados', 'LICENCA' => 'Licença atualizada', 'OFICINA_CRIADA' => 'Oficina cadastrada'];
?>
<form class="ajustes" id="ajustes" method="post" action="./configuracoes-salvar" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="remover_logo" value="0" id="remover-logo">

  <h1 class="ajustes__titulo">Configurações</h1>
  <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

  <!-- 1. Cartão de visita: é como a oficina aparece no orçamento e na OS -->
  <section class="ajustes-cartao" aria-label="Como a oficina aparece nos documentos">
    <label class="ajustes-cartao__logo<?= $temLogo ? ' tem-logo' : '' ?>" title="Trocar logotipo">
      <img id="logo-previa" src="<?= $temLogo ? './oficina-logo?v=' . e(substr(md5($of['logo_caminho']), 0, 8)) : '' ?>" alt="Logotipo da oficina"<?= $temLogo ? '' : ' hidden' ?>>
      <span class="ajustes-cartao__vazio"<?= $temLogo ? ' hidden' : '' ?>><?= icone('image', 22) ?><small>Logo</small></span>
      <span class="ajustes-cartao__trocar" aria-hidden="true"><?= icone('upload', 16) ?></span>
      <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="sr-only" id="logo-arquivo" aria-label="Enviar logotipo (JPG, PNG ou WEBP até 5 MB)">
    </label>
    <div class="ajustes-cartao__texto">
      <strong class="ajustes-cartao__nome" data-previa="nome"><?= $val('nome') ?: 'Nome da oficina' ?></strong>
      <span class="ajustes-cartao__linha" data-previa-linha></span>
      <span class="ajustes-cartao__dica" id="logo-dica">
        Assim a oficina aparece no orçamento, na OS impressa e no link de acompanhamento.
        <button type="button" class="ajustes-link" id="logo-remover"<?= $temLogo ? '' : ' hidden' ?>>Remover logo</button>
      </span>
    </div>
  </section>

  <!-- 2. Oficina -->
  <h2 class="ajustes__grupo-titulo">Oficina</h2>
  <div class="ajustes-lista">
    <label class="ajustes-linha"><span>Nome</span><input name="nome" id="nome" value="<?= $val('nome') ?>" required maxlength="150" placeholder="Obrigatório" data-campo="nome" autocomplete="organization"></label>
    <label class="ajustes-linha"><span>CNPJ</span><input name="cnpj" id="cnpj" value="<?= e(documento_fmt($of['cnpj'] ?? '')) ?>" data-mascara="cnpj" inputmode="numeric" placeholder="00.000.000/0000-00" data-campo="cnpj"></label>
    <label class="ajustes-linha"><span>Telefone</span><input name="telefone" id="telefone" value="<?= e(telefone_fmt($of['telefone'] ?? '')) ?>" data-mascara="telefone" inputmode="tel" placeholder="(00) 00000-0000" data-campo="telefone" autocomplete="tel"></label>
    <label class="ajustes-linha"><span>E-mail</span><input name="email" id="email" type="email" value="<?= $val('email') ?>" maxlength="150" placeholder="contato@oficina.com.br" data-campo="email" autocomplete="email"></label>
    <label class="ajustes-linha"><span>Atendimento</span><input name="horario" id="horario" value="<?= $val('horario') ?>" maxlength="150" placeholder="Seg a sex, 8h às 18h" data-campo="horario"></label>
  </div>

  <h2 class="ajustes__grupo-titulo">Endereço</h2>
  <div class="ajustes-lista">
    <label class="ajustes-linha"><span>Endereço</span><input name="endereco" id="endereco" value="<?= $val('endereco') ?>" maxlength="255" placeholder="Rua, número, bairro" data-campo="endereco" autocomplete="street-address"></label>
    <label class="ajustes-linha"><span>Cidade</span><input name="cidade" id="cidade" value="<?= $val('cidade') ?>" maxlength="100" placeholder="Cidade" data-campo="cidade" autocomplete="address-level2"></label>
    <label class="ajustes-linha"><span>Estado</span><input name="uf" id="uf" value="<?= $val('uf') ?>" maxlength="2" placeholder="UF" data-campo="uf" autocomplete="address-level1" class="ajustes-curto"></label>
    <label class="ajustes-linha"><span>CEP</span><input name="cep" id="cep" value="<?= e($cep) ?>" data-mascara="cep" inputmode="numeric" placeholder="00000-000" data-campo="cep" autocomplete="postal-code"></label>
  </div>

  <!-- 3. Aparência (não vai no formulário: vale na hora, para este navegador) -->
  <h2 class="ajustes__grupo-titulo">Aparência</h2>
  <div class="ajustes-lista">
    <div class="ajustes-linha ajustes-linha--tema">
      <span>Tema</span>
      <div class="ajustes-segmento" role="radiogroup" aria-label="Tema deste navegador">
        <?php foreach (['light' => 'Claro', 'dark' => 'Escuro', 'auto' => 'Automático'] as $k => $r): ?>
          <label><input type="radio" name="tema_local" value="<?= $k ?>" form="tema-sem-envio"><span><?= $r ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <p class="ajustes__nota">Vale para este navegador e muda na hora. Cada pessoa escolhe o seu.</p>

  <!-- Plano (só consulta) -->
  <h2 class="ajustes__grupo-titulo">Assinatura</h2>
  <div class="ajustes-lista">
    <div class="ajustes-linha ajustes-linha--info"><span>Plano</span><b><?= e($planos[$of['plano'] ?? '']['0'] ?? ($of['plano'] ?? '')) ?></b></div>
    <div class="ajustes-linha ajustes-linha--info"><span>Situação</span>
      <b class="ajustes-situacao<?= $vencida ? ' is-alerta' : '' ?>"><i aria-hidden="true"></i><?= e(Oficina::SITUACOES[$lic['status']] ?? $lic['status']) ?><?= $vencida ? ' · vencida' : '' ?></b></div>
    <div class="ajustes-linha ajustes-linha--info"><span>Válida até</span>
      <b><?= data_br($lic['licenca_valida_ate']) ?><?php if (!$vencida): ?> <small><?= $diasLicenca === 0 ? 'vence hoje' : 'faltam ' . $diasLicenca . ' dia' . ($diasLicenca === 1 ? '' : 's') ?></small><?php endif; ?></b></div>
    <a class="ajustes-linha ajustes-linha--info ajustes-linha--link" href="./usuarios"><span>Usuários</span>
      <b><span class="ajustes-uso" style="--uso:<?= $usoPct ?>%" aria-hidden="true"></span><?= (int) $ativos ?> de <?= (int) $limite ?><?= icone('chevron-right', 14, 2) ?></b></a>
  </div>
  <p class="ajustes__nota">Para renovar, mudar de plano ou ampliar usuários, fale com o suporte do Mecanize.</p>

  <!-- 4. Histórico -->
  <details class="ajustes-historico">
    <summary class="ajustes-linha ajustes-linha--link"><span>Histórico de alterações</span><b><?= count($historico) ?><?= icone('chevron-down', 14, 2) ?></b></summary>
    <?php if (!$historico): ?>
      <p class="ajustes__nota">Nenhuma alteração registrada.</p>
    <?php else: ?>
      <ol class="ajustes-tempo">
        <?php foreach ($historico as $h): $d = $h['dados'] ? json_decode($h['dados'], true) : [];
          $campos = array_filter(array_keys((array) $d), fn($k) => is_array($d[$k]) && array_key_exists('para', $d[$k])); ?>
          <li>
            <strong><?= e($nomesAcao[$h['acao']] ?? $h['acao']) ?></strong>
            <span><?= data_br($h['criado_em'], true) ?> · <?= e($h['autor'] ?? ($d['por'] ?? 'Mecanize')) ?></span>
            <?php if ($h['motivo'] || $campos): ?><small><?= e($h['motivo'] ?? '') ?><?= $campos ? ($h['motivo'] ? ' · ' : '') . e(implode(', ', array_map(fn($k) => $rotulosCampo[$k] ?? $k, $campos))) : '' ?></small><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </details>

  <!-- Barra de salvar: só aparece quando algo mudou -->
  <div class="ajustes-salvar" id="ajustes-salvar" role="region" aria-label="Alterações não salvas" hidden>
    <span>Alterações não salvas</span>
    <a class="btn btn--ghost btn--sm" href="./configuracoes">Descartar</a>
    <button class="btn btn--primary btn--sm" type="submit"><?= icone('check') ?> Salvar</button>
  </div>
</form>

<script>
  (function () {
    var form = document.getElementById('ajustes');
    if (!form) return;
    var barra = document.getElementById('ajustes-salvar');

    // Tema deste navegador: a mesma chave da barra lateral; "auto" segue o sistema. Não entra no formulário.
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

    // Prévia do cabeçalho impresso: nome e a linha "CNPJ · telefone · cidade/UF".
    var campo = function (n) { var i = form.querySelector('[data-campo="' + n + '"]'); return i ? i.value.trim() : ''; };
    var nomeEl = form.querySelector('[data-previa="nome"]'), linhaEl = form.querySelector('[data-previa-linha]');
    var previa = function () {
      nomeEl.textContent = campo('nome') || 'Nome da oficina';
      nomeEl.classList.toggle('is-vazio', !campo('nome'));
      var local = [campo('cidade'), campo('uf').toUpperCase()].filter(Boolean).join('/');
      var partes = [campo('cnpj') && 'CNPJ ' + campo('cnpj'), campo('telefone'), local].filter(Boolean);
      linhaEl.textContent = partes.length ? partes.join('  ·  ') : 'CNPJ, telefone e cidade aparecem aqui';
      linhaEl.classList.toggle('is-vazio', !partes.length);
    };

    // O que mudou desde que a página abriu. A barra de salvar aparece só se houver diferença.
    var campos = form.querySelectorAll('[data-campo]'), inicial = {};
    campos.forEach(function (i) { inicial[i.name] = i.value; });
    var logoMudou = false;
    var mudou = function () { return logoMudou || [].some.call(campos, function (i) { return i.value !== inicial[i.name]; }); };
    var atualizar = function () {
      var m = mudou();
      if (m && barra.hidden) { barra.hidden = false; requestAnimationFrame(function () { barra.classList.add('is-visivel'); }); }
      if (!m) { barra.classList.remove('is-visivel'); barra.hidden = true; }
    };
    var uf = form.querySelector('[data-campo=uf]');
    uf.addEventListener('input', function () { uf.value = uf.value.toUpperCase().replace(/[^A-Z]/g, ''); });
    campos.forEach(function (i) { ['input', 'change'].forEach(function (ev) { i.addEventListener(ev, function () { i.closest('.ajustes-linha').classList.remove('is-erro'); previa(); atualizar(); }); }); });

    // Logotipo: troca e prévia imediatas; "Remover" marca para apagar ao salvar.
    var arq = document.getElementById('logo-arquivo'), img = document.getElementById('logo-previa'), vazio = form.querySelector('.ajustes-cartao__vazio');
    var remover = document.getElementById('logo-remover'), removerCampo = document.getElementById('remover-logo'), tile = form.querySelector('.ajustes-cartao__logo');
    var dica = document.getElementById('logo-dica'), dicaOriginal = dica.firstChild.textContent;
    var mostrarLogo = function (src) {
      img.hidden = !src; vazio.hidden = !!src; tile.classList.toggle('tem-logo', !!src); remover.hidden = !src;
      if (src) img.src = src;
    };
    arq.addEventListener('change', function () {
      var f = arq.files[0];
      if (!f) return;
      if (f.size > 5 * 1024 * 1024 || !/^image\/(jpeg|png|webp)$/.test(f.type)) {
        dica.firstChild.textContent = 'Use JPG, PNG ou WEBP de até 5 MB. ';
        dica.classList.add('is-erro'); arq.value = ''; return;
      }
      dica.firstChild.textContent = dicaOriginal; dica.classList.remove('is-erro');
      removerCampo.value = '0'; logoMudou = true; mostrarLogo(URL.createObjectURL(f)); atualizar();
    });
    remover.addEventListener('click', function () { arq.value = ''; removerCampo.value = '1'; logoMudou = true; mostrarLogo(''); atualizar(); });

    // Validação antes de enviar; ao sair com alterações pendentes, o navegador pergunta.
    var enviando = false;
    form.addEventListener('submit', function (e) {
      var nome = form.querySelector('[data-campo=nome]'), email = form.querySelector('[data-campo=email]');
      var erro = !nome.value.trim() ? nome : (email.value && !email.checkValidity() ? email : null);
      if (erro) { e.preventDefault(); erro.closest('.ajustes-linha').classList.add('is-erro'); erro.focus(); return; }
      enviando = true;
    });
    window.addEventListener('beforeunload', function (e) { if (!enviando && mudou()) { e.preventDefault(); e.returnValue = ''; } });
    // Enter num campo salva só se houver o que salvar.
    form.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.matches('input') && !mudou()) e.preventDefault(); });

    previa();
  })();
</script>
