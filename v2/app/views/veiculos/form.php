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
          <div class="mf-linha__rotulo"><label for="placa">Placa</label><small>Modelo antigo ou Mercosul.</small></div>
          <div class="mf-linha__campo"><input id="placa" name="placa" class="campo-placa" value="<?= $val('placa') ?>" data-mascara="placa" required placeholder="ABC1D23" autocomplete="off" style="text-transform:uppercase"></div>
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
  </script>
</form>
