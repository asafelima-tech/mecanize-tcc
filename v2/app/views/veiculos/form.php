<?php
/**
 * Cadastro e edição de veículo, no padrão de janela (modal) das telas de criar e editar.
 * Topo com título e fechar, seções à esquerda, linhas "rótulo à esquerda, campo à direita" e o rodapé
 * com as ações. Aberta pelo js/modal.js sobre a tela de onde veio; acessada direto, ocupa a página.
 * Os campos, nomes, validações e o destino do formulário são os mesmos de sempre.
 * Espera: $v, $id, $erro, $clientes, $marcas
 */
$titulo = $id ? 'Editar veículo' : 'Novo veículo';
$val = fn($k) => e($v[$k] ?? '');
$marcaAtual = $v['marca'] ?? ($_POST['marca'] ?? '');
$semModelo = empty($v['modelo_id']) && !empty($v['modelo_texto']);
$placa = trim((string) ($v['placa'] ?? ''));
$voltar = $id ? './veiculo?id=' . (int) $id : './veiculos';
$secoes = [['identificacao', 'plate', 'Identificação'], ['caracteristicas', 'car', 'Características'], ['detalhes', 'checklist', 'Detalhes']];
?>
<form class="mf" method="post" action="./veiculo-salvar<?= $id ? '?id=' . (int) $id : '' ?>" aria-labelledby="mf-titulo">
  <?= csrf_field() ?>

  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo"><?= $titulo ?><?php if ($id && $placa !== ''): ?><span><?= e(mb_strtoupper($placa)) ?></span><?php endif; ?></h2>
    <a class="mf__fechar" href="<?= $voltar ?>" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <nav class="mf__nav" aria-label="Seções do cadastro">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
      <?php if ($id && !empty($v['criado_em'])): ?>
        <dl class="mf__nav-rodape">
          <div><dt>Cadastrado em</dt><dd><?= data_br($v['criado_em']) ?></dd></div>
          <div><dt>Situação</dt><dd><?= !isset($v['ativo']) || $v['ativo'] ? 'Ativo' : 'Inativo' ?></dd></div>
        </dl>
      <?php endif; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-identificacao" aria-labelledby="mf-h-identificacao">
        <h3 id="mf-h-identificacao">Identificação</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cliente_id">Cliente</label><small>Não está na lista? <a href="./cliente-form">Cadastre o cliente</a>.</small></div>
          <div class="mf-linha__campo">
            <select id="cliente_id" name="cliente_id" required>
              <option value="">Escolha o cliente</option>
              <?php foreach ($clientes as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($v['cliente_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?><?= $c['documento'] ? ' · ' . e(documento_fmt($c['documento'])) : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="placa">Placa</label><small data-placa-dica>Digite ou leia pela foto.</small></div>
          <div class="mf-linha__campo">
            <div class="placa-entrada">
              <input id="placa" name="placa" class="campo-placa" value="<?= $val('placa') ?>" data-mascara="placa" required placeholder="ABC1D23" autocomplete="off" style="text-transform:uppercase">
              <button type="button" class="btn btn--ghost placa-entrada__foto" data-ler-placa title="Ler a placa por uma foto"><?= icone('scan-plate', 16, 1.8) ?><span>Ler pela foto</span></button>
              <input type="file" accept="image/*" capture="environment" class="sr-only" data-placa-arquivo tabindex="-1" aria-hidden="true">
            </div>
            <div class="placa-leitura" data-placa-leitura hidden aria-live="polite"></div>
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-caracteristicas" aria-labelledby="mf-h-caracteristicas">
        <h3 id="mf-h-caracteristicas">Características</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="marca">Marca</label></div>
          <div class="mf-linha__campo">
            <select id="marca" name="marca">
              <option value="">Escolha a marca</option>
              <?php foreach ($marcas as $m): ?><option <?= $marcaAtual === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="<?= $semModelo ? 'modelo_texto' : 'modelo_id' ?>" id="rotulo-modelo">Modelo</label><small><a href="#" id="alternar-modelo"><?= $semModelo ? 'Escolher da lista' : 'Não está na lista?' ?></a></small></div>
          <div class="mf-linha__campo">
            <select id="modelo_id" name="modelo_id" data-atual="<?= (int) ($v['modelo_id'] ?? 0) ?>" class="<?= $semModelo ? 'oculto' : '' ?>">
              <option value="">Escolha a marca primeiro</option>
            </select>
            <input id="modelo_texto" name="modelo_texto" value="<?= $val('modelo_texto') ?>" maxlength="120" placeholder="Ex.: Fiat Uno Mille 1.0" class="<?= $semModelo ? '' : 'oculto' ?>">
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="ano">Ano</label></div>
          <div class="mf-linha__campo"><input id="ano" name="ano" type="number" min="1900" max="<?= (int) date('Y') + 1 ?>" value="<?= $val('ano') ?>" placeholder="<?= (int) date('Y') ?>"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-detalhes" aria-labelledby="mf-h-detalhes">
        <h3 id="mf-h-detalhes">Detalhes<small>Opcional</small></h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cor">Cor</label></div>
          <div class="mf-linha__campo">
            <select id="cor" name="cor"><option value="">Não informada</option>
              <?php foreach (Veiculo::CORES as $cor): ?><option <?= ($v['cor'] ?? '') === $cor ? 'selected' : '' ?>><?= e($cor) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="combustivel">Combustível</label></div>
          <div class="mf-linha__campo">
            <select id="combustivel" name="combustivel"><option value="">Não informado</option>
              <?php foreach (Veiculo::COMBUSTIVEIS as $cb): ?><option <?= ($v['combustivel'] ?? '') === $cb ? 'selected' : '' ?>><?= e($cb) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><label for="observacoes">Observações</label><small>Avarias, acessórios e particularidades do veículo.</small></div>
          <div class="mf-linha__campo"><textarea id="observacoes" name="observacoes" rows="3"><?= $val('observacoes') ?></textarea></div>
        </div>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">Cliente, placa e modelo são obrigatórios.</span>
    <a class="btn btn--ghost" href="<?= $voltar ?>" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit"><?= $id ? 'Salvar alterações' : 'Cadastrar veículo' ?></button>
  </footer>

  <script>
    (function () {
      // Modelo: a lista acompanha a marca escolhida; fora da lista, o modelo é escrito à mão.
      var raiz = document.currentScript.closest('.mf');
      var marca = raiz.querySelector('#marca'), modelo = raiz.querySelector('#modelo_id'),
          texto = raiz.querySelector('#modelo_texto'), alternar = raiz.querySelector('#alternar-modelo'),
          rotulo = raiz.querySelector('#rotulo-modelo');

      function carregar() {
        var atual = modelo.dataset.atual;
        modelo.innerHTML = '<option value="">' + (marca.value ? 'Carregando…' : 'Escolha a marca primeiro') + '</option>';
        if (!marca.value) return;
        fetch('./veiculo-modelos?marca=' + encodeURIComponent(marca.value), { headers: { 'X-Requested-With': 'fetch' } })
          .then(function (r) { return r.json(); })
          .then(function (lista) {
            modelo.innerHTML = '<option value="">Escolha o modelo</option>';
            lista.forEach(function (m) {
              var o = document.createElement('option');
              o.value = m.id; o.textContent = m.nome; o.selected = String(m.id) === atual;
              modelo.appendChild(o);
            });
          });
      }
      marca.addEventListener('change', function () { modelo.dataset.atual = ''; carregar(); });
      alternar.addEventListener('click', function (e) {
        e.preventDefault();
        var usarTexto = texto.classList.contains('oculto');
        texto.classList.toggle('oculto', !usarTexto);
        modelo.classList.toggle('oculto', usarTexto);
        if (usarTexto) { modelo.value = ''; texto.focus(); } else { texto.value = ''; }
        alternar.textContent = usarTexto ? 'Escolher da lista' : 'Não está na lista?';
        rotulo.setAttribute('for', usarTexto ? 'modelo_texto' : 'modelo_id');
      });
      carregar();
    })();

    (function () {
      // Placa: mostra o padrão enquanto digita e lê a placa por uma foto. A leitura é só uma sugestão: aparece
      // embaixo do campo, desenhada no padrão dela, e só vai para o campo quando a pessoa escolhe "Usar".
      var raiz = document.currentScript.closest('.mf');
      var campo = raiz.querySelector('#placa'), dica = raiz.querySelector('[data-placa-dica]'), dicaPadrao = dica.textContent;
      var botao = raiz.querySelector('[data-ler-placa]'), arquivo = raiz.querySelector('[data-placa-arquivo]');
      var painel = raiz.querySelector('[data-placa-leitura]'), token = (raiz.querySelector('[name=csrf_token]') || {}).value || '';

      function limpar(t) { return String(t || '').toUpperCase().replace(/[^A-Z0-9]/g, ''); }
      function tipo(p) { return /^[A-Z]{3}\d[A-Z]\d{2}$/.test(p) ? 'mercosul' : (/^[A-Z]{3}\d{4}$/.test(p) ? 'antiga' : ''); }
      function plaquinha(p) {
        var t = tipo(p), s = document.createElement('span');
        s.className = 'placa-caixa placa-caixa--p' + (t ? ' placa-caixa--' + t : '');
        s.textContent = t === 'antiga' ? p.slice(0, 3) + '-' + p.slice(3) : p;
        return s;
      }
      function atualizarDica() {
        var t = tipo(limpar(campo.value));
        dica.textContent = t === 'mercosul' ? 'Placa Mercosul.' : (t === 'antiga' ? 'Placa modelo antigo.' : dicaPadrao);
      }
      campo.addEventListener('input', atualizarDica);
      atualizarDica();

      function usar(p) {
        if (campo._mascara) campo._mascara.value = p; else campo.value = p;
        campo.dispatchEvent(new Event('input', { bubbles: true }));
        campo.dispatchEvent(new Event('change', { bubbles: true }));
        painel.hidden = true; painel.innerHTML = '';
        campo.focus();
      }
      function mostrar(html, classe) { painel.className = 'placa-leitura' + (classe ? ' ' + classe : ''); painel.innerHTML = html; painel.hidden = false; }

      function resultado(r) {
        if (!r || !r.ok || !r.placa) { mostrar('', 'is-erro'); painel.textContent = (r && r.erro) || 'Não foi possível ler a placa. Digite a placa.'; return; }
        mostrar('<span class="placa-leitura__rotulo">Lemos esta placa</span><span class="placa-leitura__placa"></span>'
          + '<button type="button" class="btn btn--primary btn--sm" data-usar>Usar</button>', r.baixa_confianca ? 'is-incerta' : '');
        painel.querySelector('.placa-leitura__placa').appendChild(plaquinha(r.placa));
        painel.querySelector('[data-usar]').addEventListener('click', function () { usar(r.placa); });
        var aviso = r.erro || (r.baixa_confianca ? 'A leitura ficou incerta: confira com a placa do carro.' : '');
        if (aviso) { var a = document.createElement('small'); a.className = 'placa-leitura__aviso'; a.textContent = aviso; painel.appendChild(a); }
        if (r.candidatos && r.candidatos.length) {
          var alt = document.createElement('div'); alt.className = 'placa-leitura__outras';
          alt.appendChild(document.createTextNode('Ou: '));
          r.candidatos.forEach(function (c) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'placa-leitura__opcao'; b.title = 'Usar ' + c.placa;
            b.appendChild(plaquinha(c.placa)); b.addEventListener('click', function () { usar(c.placa); });
            alt.appendChild(b);
          });
          painel.appendChild(alt);
        }
      }

      // A foto é reduzida no navegador (até 1600 px, JPEG) antes de enviar: sobe mais rápido e fica abaixo do limite.
      function reduzir(f) {
        return new Promise(function (ok) {
          if (!window.createImageBitmap || !HTMLCanvasElement.prototype.toBlob) { ok(f); return; }
          createImageBitmap(f).then(function (img) {
            var esc = Math.min(1, 1600 / Math.max(img.width, img.height)), c = document.createElement('canvas');
            c.width = Math.round(img.width * esc); c.height = Math.round(img.height * esc);
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            c.toBlob(function (b) { ok(b || f); }, 'image/jpeg', .88);
          }).catch(function () { ok(f); });
        });
      }

      botao.addEventListener('click', function () { arquivo.value = ''; arquivo.click(); });
      arquivo.addEventListener('change', function (e) {
        e.stopPropagation();
        var f = arquivo.files && arquivo.files[0];
        if (!f) return;
        botao.disabled = true; botao.classList.add('is-enviando');
        mostrar('<span class="placa-leitura__carregando" aria-hidden="true"></span>Lendo a placa…', 'is-lendo');
        reduzir(f).then(function (img) {
          var dados = new FormData();
          dados.append('imagem', img, 'placa.jpg');
          dados.append('csrf_token', token);
          return fetch('./veiculo-placa', { method: 'POST', body: dados, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': token } });
        }).then(function (r) { return r.json(); })
          .then(resultado)
          .catch(function () { resultado({ ok: false, erro: 'Sem conexão com o servidor. Tente de novo ou digite a placa.' }); })
          .then(function () { botao.disabled = false; botao.classList.remove('is-enviando'); arquivo.value = ''; });
      });
    })();
  </script>
</form>
