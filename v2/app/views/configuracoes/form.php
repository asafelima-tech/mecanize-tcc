<?php
/**
 * Configurações da oficina, no padrão de janela (modal) das telas de criar e editar.
 * Topo com título e fechar, seções à esquerda (Oficina, Endereço, Aparência, Assinatura, Histórico), linhas
 * "rótulo à esquerda, controle à direita" e o rodapé com as ações. Aberta pelo js/modal.js sobre a tela de
 * onde veio; acessada direto, ocupa a página.
 * Os campos, nomes, o logotipo (envio, prévia e remoção) e o destino do formulário são os mesmos de sempre.
 * O tema não entra no formulário: vale na hora, para este navegador.
 * Espera: $of, $historico, $vagas, $erro (opcional)
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
$inicio = './' . e(Permissao::inicio(Contexto::perfil()));
$nomeOficina = trim((string) ($of['nome'] ?? ''));
$secoes = [['oficina', 'home', 'Oficina'], ['endereco', 'location', 'Endereço'], ['aparencia', 'sun', 'Aparência'], ['assinatura', 'credit-card', 'Assinatura'], ['historico', 'history', 'Histórico']];
?>
<form class="mf" id="ajustes" method="post" action="./configuracoes-salvar" enctype="multipart/form-data" novalidate aria-labelledby="mf-titulo">
  <?= csrf_field() ?>
  <input type="hidden" name="remover_logo" value="0" id="remover-logo">

  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo">Configurações<?php if ($nomeOficina !== ''): ?><span><?= e($nomeOficina) ?></span><?php endif; ?></h2>
    <a class="mf__fechar" href="<?= $inicio ?>" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <nav class="mf__nav" aria-label="Seções das configurações">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-oficina" aria-labelledby="mf-h-oficina">
        <h3 id="mf-h-oficina">Oficina</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span id="rotulo-logo">Logotipo</span><small id="logo-dica">JPG, PNG ou WEBP até 5 MB. Sai no orçamento, na OS e no acompanhamento.</small></div>
          <div class="mf-linha__campo mf-linha__campo--fim">
            <div class="mf-logo<?= $temLogo ? ' tem-logo' : '' ?>">
              <label class="mf-logo__miniatura" for="logo-arquivo" title="Trocar logotipo">
                <img id="logo-previa" src="<?= $temLogo ? './oficina-logo?v=' . e(substr(md5($of['logo_caminho']), 0, 8)) : '' ?>" alt="Logotipo da oficina"<?= $temLogo ? '' : ' hidden' ?>>
                <span class="mf-logo__vazio"<?= $temLogo ? ' hidden' : '' ?>><?= icone('image', 18) ?></span>
              </label>
              <label class="btn btn--ghost btn--sm mf-logo__botao">
                <span id="logo-acao"><?= $temLogo ? 'Trocar' : 'Enviar' ?></span>
                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="sr-only" id="logo-arquivo" aria-describedby="logo-dica" aria-label="Enviar logotipo (JPG, PNG ou WEBP até 5 MB)">
              </label>
              <button type="button" class="btn btn--ghost btn--sm" id="logo-remover"<?= $temLogo ? '' : ' hidden' ?>>Remover</button>
            </div>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="nome">Nome</label><small>Como aparece no orçamento e na OS.</small></div>
          <div class="mf-linha__campo"><input name="nome" id="nome" value="<?= $val('nome') ?>" required maxlength="150" data-campo="nome" autocomplete="organization" autofocus></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cnpj">CNPJ</label></div>
          <div class="mf-linha__campo"><input name="cnpj" id="cnpj" value="<?= e(documento_fmt($of['cnpj'] ?? '')) ?>" data-mascara="cnpj" inputmode="numeric" placeholder="00.000.000/0000-00" data-campo="cnpj"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="telefone">Telefone</label></div>
          <div class="mf-linha__campo"><input name="telefone" id="telefone" value="<?= e(telefone_fmt($of['telefone'] ?? '')) ?>" data-mascara="telefone" inputmode="tel" placeholder="(00) 00000-0000" data-campo="telefone" autocomplete="tel"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="email">E-mail</label></div>
          <div class="mf-linha__campo"><input name="email" id="email" type="email" value="<?= $val('email') ?>" maxlength="150" placeholder="contato@oficina.com.br" data-campo="email" autocomplete="email"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="horario">Atendimento</label><small>Dias e horário de funcionamento.</small></div>
          <div class="mf-linha__campo"><input name="horario" id="horario" value="<?= $val('horario') ?>" maxlength="150" placeholder="Seg a sex, 8h às 18h" data-campo="horario"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-endereco" aria-labelledby="mf-h-endereco">
        <h3 id="mf-h-endereco">Endereço</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="endereco">Endereço</label></div>
          <div class="mf-linha__campo"><input name="endereco" id="endereco" value="<?= $val('endereco') ?>" maxlength="255" placeholder="Rua, número, bairro" data-campo="endereco" autocomplete="street-address"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cidade">Cidade e UF</label></div>
          <div class="mf-linha__campo mf-linha__campo--par">
            <input name="cidade" id="cidade" value="<?= $val('cidade') ?>" maxlength="100" placeholder="Cidade" data-campo="cidade" autocomplete="address-level2">
            <input name="uf" id="uf" value="<?= $val('uf') ?>" maxlength="2" placeholder="UF" aria-label="UF" data-campo="uf" autocomplete="address-level1" style="text-transform:uppercase">
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cep">CEP</label></div>
          <div class="mf-linha__campo"><input name="cep" id="cep" value="<?= e($cep) ?>" data-mascara="cep" inputmode="numeric" placeholder="00000-000" data-campo="cep" autocomplete="postal-code"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-aparencia" aria-labelledby="mf-h-aparencia">
        <h3 id="mf-h-aparencia">Aparência</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span id="rotulo-tema">Tema</span><small>Vale para este navegador e muda na hora. Cada pessoa escolhe o seu.</small></div>
          <div class="mf-linha__campo">
            <!-- form="tema-sem-envio" (que não existe) tira o tema do envio: ele não é dado da oficina. -->
            <div class="segmento" role="radiogroup" aria-labelledby="rotulo-tema">
              <?php foreach (['light' => 'Claro', 'dark' => 'Escuro', 'auto' => 'Automático'] as $k => $r): ?>
                <label><input type="radio" name="tema_local" value="<?= $k ?>" form="tema-sem-envio"><span><?= $r ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-assinatura" aria-labelledby="mf-h-assinatura">
        <h3 id="mf-h-assinatura">Assinatura</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span>Plano</span></div>
          <div class="mf-linha__campo mf-linha__campo--fim"><span class="mf-leitura"><?= e($planos[$of['plano'] ?? '']['0'] ?? ($of['plano'] ?? '')) ?></span></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span>Situação</span></div>
          <div class="mf-linha__campo mf-linha__campo--fim">
            <span class="mf-leitura mf-situacao<?=$vencida ? ' is-alerta' : '' ?>"><i aria-hidden="true"></i><?= e(Oficina::SITUACOES[$lic['status']] ?? $lic['status']) ?><?= $vencida ? ' · vencida' : '' ?></span>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span>Válida até</span><?php if (!$vencida): ?><small><?= $diasLicenca === 0 ? 'Vence hoje' : 'Faltam ' . $diasLicenca . ' dia' . ($diasLicenca === 1 ? '' : 's') ?></small><?php endif; ?></div>
          <div class="mf-linha__campo mf-linha__campo--fim"><span class="mf-leitura"><?= data_br($lic['licenca_valida_ate']) ?></span></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span>Usuários</span><small>Para renovar, mudar de plano ou ampliar usuários, fale com o suporte do Mecanize.</small></div>
          <div class="mf-linha__campo mf-linha__campo--fim">
            <a class="mf-leitura mf-leitura--link" href="./usuarios"><span class="mf-uso" style="--uso:<?= $usoPct ?>%" aria-hidden="true"></span><?= (int) $ativos ?> de <?= (int) $limite ?><?= icone('chevron-right', 14, 2) ?></a>
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-historico" aria-labelledby="mf-h-historico">
        <h3 id="mf-h-historico">Histórico<small><?= count($historico) ?> alteraç<?= count($historico) === 1 ? 'ão' : 'ões' ?></small></h3>
        <?php if (!$historico): ?>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><small>Nenhuma alteração registrada.</small></div>
          </div>
        <?php else: ?>
          <ol class="mf-historico">
            <?php foreach ($historico as $h): $d = $h['dados'] ? json_decode($h['dados'], true) : [];
              $campos = array_filter(array_keys((array) $d), fn($k) => is_array($d[$k]) && array_key_exists('para', $d[$k])); ?>
              <li>
                <div>
                  <strong><?= e($nomesAcao[$h['acao']] ?? $h['acao']) ?></strong>
                  <?php if ($h['motivo'] || $campos): ?><small><?= e($h['motivo'] ?? '') ?><?= $campos ? ($h['motivo'] ? ' · ' : '') . e(implode(', ', array_map(fn($k) => $rotulosCampo[$k] ?? $k, $campos))) : '' ?></small><?php endif; ?>
                </div>
                <span><?= data_br($h['criado_em'], true) ?> · <?= e($h['autor'] ?? ($d['por'] ?? 'Mecanize')) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">O nome é obrigatório.</span>
    <a class="btn btn--ghost" href="<?= $inicio ?>" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit">Salvar</button>
  </footer>

  <script>
    (function () {
      var form = document.currentScript.closest('.mf');
      if (!form) return;
      var naJanela = !!form.closest('.mf-dlg');

      // Tema deste navegador: a mesma chave da barra lateral; "auto" segue o sistema. Não entra no formulário
      // nem conta como alteração da janela (o evento não sobe até o formulário).
      var html = document.documentElement, salvo = null;
      try { salvo = localStorage.getItem('mecanize-theme'); } catch (e) {}
      var atual = salvo === 'dark' || salvo === 'light' ? salvo : 'auto';
      form.querySelectorAll('input[name=tema_local]').forEach(function (r) {
        r.checked = r.value === atual;
        r.addEventListener('input', function (e) { e.stopPropagation(); });
        r.addEventListener('change', function (e) {
          e.stopPropagation();
          var escuro = r.value === 'dark' || (r.value === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
          html.setAttribute('data-theme', escuro ? 'dark' : 'light');
          try { r.value === 'auto' ? localStorage.removeItem('mecanize-theme') : localStorage.setItem('mecanize-theme', r.value); } catch (e2) {}
        });
      });

      // O que mudou desde que a tela abriu (Enter só salva se houver o que salvar).
      var campos = form.querySelectorAll('[data-campo]'), inicial = {};
      campos.forEach(function (i) { inicial[i.name] = i.value; });
      var logoMudou = false;
      var mudou = function () { return logoMudou || [].some.call(campos, function (i) { return i.value !== inicial[i.name]; }); };
      var uf = form.querySelector('[data-campo=uf]');
      uf.addEventListener('input', function () { uf.value = uf.value.toUpperCase().replace(/[^A-Z]/g, ''); });
      campos.forEach(function (i) { ['input', 'change'].forEach(function (ev) { i.addEventListener(ev, function () { i.removeAttribute('aria-invalid'); }); }); });

      // Logotipo: troca e prévia imediatas; "Remover" marca para apagar ao salvar.
      var arq = form.querySelector('#logo-arquivo'), img = form.querySelector('#logo-previa'), vazio = form.querySelector('.mf-logo__vazio');
      var remover = form.querySelector('#logo-remover'), removerCampo = form.querySelector('#remover-logo'), caixa = form.querySelector('.mf-logo');
      var acao = form.querySelector('#logo-acao'), dica = form.querySelector('#logo-dica'), dicaOriginal = dica.textContent;
      var mostrarLogo = function (src) {
        img.hidden = !src; vazio.hidden = !!src; caixa.classList.toggle('tem-logo', !!src); remover.hidden = !src;
        acao.textContent = src ? 'Trocar' : 'Enviar';
        if (src) img.src = src;
      };
      arq.addEventListener('change', function () {
        var f = arq.files[0];
        if (!f) return;
        if (f.size > 5 * 1024 * 1024 || !/^image\/(jpeg|png|webp)$/.test(f.type)) {
          dica.textContent = 'Use JPG, PNG ou WEBP de até 5 MB.';
          dica.classList.add('is-erro'); arq.value = ''; return;
        }
        dica.textContent = dicaOriginal; dica.classList.remove('is-erro');
        removerCampo.value = '0'; logoMudou = true; mostrarLogo(URL.createObjectURL(f));
      });
      remover.addEventListener('click', function () {
        arq.value = ''; removerCampo.value = '1'; logoMudou = true; mostrarLogo('');
        form.dataset.alterado = '1';
      });

      // Validação antes de enviar (registrada antes do envio da janela, que respeita o preventDefault).
      var enviando = false;
      form.addEventListener('submit', function (e) {
        var nome = form.querySelector('[data-campo=nome]'), email = form.querySelector('[data-campo=email]');
        var erro = !nome.value.trim() ? nome : (email.value && !email.checkValidity() ? email : null);
        if (erro) { e.preventDefault(); erro.setAttribute('aria-invalid', 'true'); erro.focus(); return; }
        enviando = true;
      });
      // Aberta como página: ao sair com alterações pendentes, o navegador pergunta (na janela, quem pergunta é o modal.js).
      if (!naJanela) {
        window.addEventListener('beforeunload', function (e) { if (!enviando && document.contains(form) && mudou()) { e.preventDefault(); e.returnValue = ''; } });
      }
      form.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.matches('input') && !mudou()) e.preventDefault(); });
    })();
  </script>
</form>
