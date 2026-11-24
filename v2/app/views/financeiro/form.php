<?php
/**
 * Nova conta a receber ou a pagar, no padrão de janela (modal) das telas de criar e editar.
 * Topo com título e fechar, seções à esquerda, linhas "rótulo à esquerda, campo à direita" e o rodapé
 * com as ações. As parcelas são só leitura e seguem a regra de Lancamento::criar (parcelas iguais,
 * mensais, centavos que sobram na última). Os campos, nomes e o destino do formulário são os mesmos de sempre.
 * Espera: $d, $erro, $clientes, $fornecedores, $funcionarios
 */
$tipo = ($d['tipo'] ?? 'RECEBER') === 'PAGAR' ? 'PAGAR' : 'RECEBER';
$receber = $tipo === 'RECEBER';
$titulo = $receber ? 'Nova conta a receber' : 'Nova conta a pagar';
$val = fn($k) => e($d[$k] ?? '');
$sel = fn($k, $v) => (string) ($d[$k] ?? '') === (string) $v ? 'selected' : '';
$voltar = './financeiro?tipo=' . $tipo;
$secoes = [['conta', $receber ? 'download' : 'upload', 'Conta'], ['valor', 'coins', 'Valor'], ['vencimento', 'calendar', 'Vencimento'], ['detalhes', 'file', 'Detalhes']];
?>
<form class="mf" method="post" action="./conta-salvar" id="form-conta" aria-labelledby="mf-titulo">
  <?= csrf_field() ?>
  <input type="hidden" name="tipo" value="<?= $tipo ?>">

  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo"><?= $titulo ?></h2>
    <a class="mf__fechar" href="<?= $voltar ?>" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <nav class="mf__nav" aria-label="Seções da conta">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-conta" aria-labelledby="mf-h-conta">
        <h3 id="mf-h-conta">Conta</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="descricao">Descrição</label><small><?= $receber ? 'A cobrança de uma OS é feita na própria OS.' : 'Despesa da oficina, à vista ou parcelada.' ?></small></div>
          <div class="mf-linha__campo"><input id="descricao" name="descricao" value="<?= $val('descricao') ?>" required maxlength="200" autocomplete="off" autofocus placeholder="<?= $receber ? 'Ex.: Venda de pneu usado' : 'Ex.: Aluguel de novembro' ?>"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="categoria">Categoria</label></div>
          <div class="mf-linha__campo">
            <select id="categoria" name="categoria" required><?php foreach (Lancamento::CATEGORIAS[$tipo] as $k => $r): ?><option value="<?= $k ?>" <?= $sel('categoria', $k) ?>><?= e($r) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <?php if ($receber): ?>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="cliente_id">Cliente</label><small>Opcional.</small></div>
            <div class="mf-linha__campo">
              <select id="cliente_id" name="cliente_id"><option value="">Sem cliente vinculado</option><?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $sel('cliente_id', $c['id']) ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
        <?php else: ?>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="fornecedor_id">Fornecedor</label><small>Opcional.</small></div>
            <div class="mf-linha__campo">
              <select id="fornecedor_id" name="fornecedor_id"><option value="">Sem fornecedor</option><?php foreach ($fornecedores as $fo): ?><option value="<?= (int) $fo['id'] ?>" <?= $sel('fornecedor_id', $fo['id']) ?>><?= e($fo['nome']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="mf-linha">
            <div class="mf-linha__rotulo"><label for="funcionario_id">Funcionário</label><small>Opcional.</small></div>
            <div class="mf-linha__campo">
              <select id="funcionario_id" name="funcionario_id"><option value="">Sem funcionário</option><?php foreach ($funcionarios as $fu): ?><option value="<?= (int) $fu['id'] ?>" <?= $sel('funcionario_id', $fu['id']) ?>><?= e($fu['nome']) ?></option><?php endforeach; ?></select>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <section class="mf__secao" id="mf-valor" aria-labelledby="mf-h-valor">
        <h3 id="mf-h-valor">Valor</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="valor">Valor total</label></div>
          <div class="mf-linha__campo"><span class="mf-prefixo"><span>R$</span><input id="valor" name="valor" value="<?= $val('valor') ?>" inputmode="decimal" required placeholder="0,00" autocomplete="off"></span></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="parcelas">Parcelas</label><small>Parcelas iguais; os centavos que sobram vão para a última.</small></div>
          <div class="mf-linha__campo">
            <select id="parcelas" name="parcelas"><?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= $i ?>" <?= (int) ($d['parcelas'] ?? 1) === $i ? 'selected' : '' ?>><?= $i === 1 ? 'À vista' : $i . '×' ?></option><?php endfor; ?></select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span>Parcelamento</span><small data-plano>Informe o valor para ver as parcelas.</small></div>
          <div class="mf-linha__campo mf-linha__campo--fim"><output class="mf-valor" data-previa-total aria-live="polite"></output></div>
        </div>
        <div class="mf-linha mf-linha--bloco" data-previa-parcelas-linha hidden>
          <div class="mf-linha__campo"><ol class="mf-parcelas" data-previa-parcelas aria-label="Parcelas"></ol></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-vencimento" aria-labelledby="mf-h-vencimento">
        <h3 id="mf-h-vencimento">Vencimento</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="vencimento">1º vencimento</label><small>As demais parcelas vencem todo mês.</small></div>
          <div class="mf-linha__campo"><input type="date" id="vencimento" name="vencimento" value="<?= $val('vencimento') ?>" required></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="competencia">Competência</label><small>Mês em que a conta entra no resultado.</small></div>
          <div class="mf-linha__campo"><input type="date" id="competencia" name="competencia" value="<?= $val('competencia') ?>"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-detalhes" aria-labelledby="mf-h-detalhes">
        <h3 id="mf-h-detalhes">Detalhes<small>Opcional</small></h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="forma_prevista">Forma prevista</label></div>
          <div class="mf-linha__campo">
            <select id="forma_prevista" name="forma_prevista"><option value="">A definir</option><?php foreach (Lancamento::FORMAS as $k => $r): ?><option value="<?= $k ?>" <?= $sel('forma_prevista', $k) ?>><?= e($r) ?></option><?php endforeach; ?></select>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="documento">Documento</label><small>Número do boleto ou da nota.</small></div>
          <div class="mf-linha__campo"><input id="documento" name="documento" value="<?= $val('documento') ?>" maxlength="60" autocomplete="off"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="observacao">Observação</label></div>
          <div class="mf-linha__campo"><input id="observacao" name="observacao" value="<?= $val('observacao') ?>" maxlength="255"></div>
        </div>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">Descrição, valor e vencimento são obrigatórios.</span>
    <a class="btn btn--ghost" href="<?= $voltar ?>" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit">Lançar conta</button>
  </footer>

  <script>
    (function () {
      // Só leitura: o parcelamento acompanha os campos (mesma regra de Lancamento::criar).
      var raiz = document.currentScript.closest('.mf');
      var q = function (s) { return raiz.querySelector(s); };
      var moeda = function (v) { return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
      var numero = function (t) {
        t = String(t || '').replace(/\s|R\$/g, '');
        if (t.indexOf(',') >= 0) t = t.replace(/\./g, '').replace(',', '.');
        var n = parseFloat(t); return isNaN(n) ? 0 : Math.round(n * 100) / 100;
      };
      var mes = function (iso, i) { if (!iso) return null; var p = iso.split('-'); return new Date(+p[0], +p[1] - 1 + i, +p[2]); };
      var br = function (d) { return d ? d.toLocaleDateString('pt-BR') : ''; };
      // Pelo nome: o campo de data visível (id) e o valor enviado (name) são elementos diferentes depois do campos.js.
      var venc = function () { var c = q('[name="vencimento"]'); return c ? c.value : ''; };
      var cValor = q('#valor'), cParcelas = q('#parcelas');
      var pPlano = q('[data-plano]'), oTotal = q('[data-previa-total]'), linhaLista = q('[data-previa-parcelas-linha]'), ol = q('[data-previa-parcelas]');

      function atualizar() {
        var valor = numero(cValor.value), n = parseInt(cParcelas.value, 10) || 1;
        var base = Math.floor(valor / n * 100) / 100, ultima = Math.round((valor - base * (n - 1)) * 100) / 100;
        if (valor > 0) {
          oTotal.textContent = n === 1 ? moeda(valor) : n + '× ' + moeda(base);
          var partes = [n === 1 ? 'Pagamento único' : 'Total de ' + moeda(valor)];
          if (n > 1 && ultima !== base) partes.push('a última fica com ' + moeda(ultima));
          if (n > 1 && venc()) partes.push('última em ' + br(mes(venc(), n - 1)));
          pPlano.textContent = partes.join(' · ') + '.';
        } else {
          oTotal.textContent = '';
          pPlano.textContent = 'Informe o valor para ver as parcelas.';
        }
        ol.innerHTML = '';
        linhaLista.hidden = !(n > 1 && valor > 0 && venc());
        if (!linhaLista.hidden) for (var i = 0; i < n; i++) {
          var li = document.createElement('li'), s = document.createElement('span'), b = document.createElement('b');
          s.textContent = (i + 1) + 'ª · ' + br(mes(venc(), i));
          b.textContent = moeda(i < n - 1 ? base : ultima);
          li.appendChild(s); li.appendChild(b); ol.appendChild(li);
        }
      }
      ['input', 'change'].forEach(function (ev) { raiz.addEventListener(ev, atualizar); });
      atualizar();
    })();
  </script>
</form>
