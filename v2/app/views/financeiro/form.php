<?php
/**
 * Nova conta a receber / a pagar, no formato de ficha dos outros cadastros: um cartão com três grupos
 * (identificação; valor e vencimento em destaque; complementares) e, ao lado, o resumo com as parcelas
 * e as ações. Os campos, nomes e o destino do formulário são os mesmos de sempre; a lista de parcelas ao
 * lado só mostra a regra de Lancamento::criar (parcelas iguais, mensais, centavos que sobram na última).
 * Espera: $d, $erro, $clientes, $fornecedores, $funcionarios
 */
$tipo = ($d['tipo'] ?? 'RECEBER') === 'PAGAR' ? 'PAGAR' : 'RECEBER';
$receber = $tipo === 'RECEBER';
$titulo = $receber ? 'Nova conta a receber' : 'Nova conta a pagar';
$val = fn($k) => e($d[$k] ?? '');
$sel = fn($k, $v) => (string) ($d[$k] ?? '') === (string) $v ? 'selected' : '';
$categoria = Lancamento::CATEGORIAS[$tipo][$d['categoria'] ?? ''] ?? array_values(Lancamento::CATEGORIAS[$tipo])[0];
$voltar = './financeiro?tipo=' . $tipo;
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="<?= $voltar ?>"><?= $receber ? 'Contas a receber' : 'Contas a pagar' ?></a> · <?= $receber ? 'a cobrança de uma OS é feita na própria OS' : 'despesa da oficina, à vista ou parcelada' ?></p>
  </div>
</div>

<form class="form-ficha" method="post" action="./conta-salvar" id="form-conta">
  <?= csrf_field() ?><input type="hidden" name="tipo" value="<?= $tipo ?>">

  <div class="form-ficha__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <div class="card form-ficha__secao grupos">
      <!-- 1. Identificação -->
      <section class="grupo" aria-labelledby="g-identificacao">
        <header class="grupo__cabeca"><span class="grupo__n">1</span><h2 id="g-identificacao">Identificação</h2></header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c8"><label for="descricao">Descrição *</label>
            <input id="descricao" name="descricao" value="<?= $val('descricao') ?>" required maxlength="200" autofocus placeholder="<?= $receber ? 'Ex.: Venda de pneu usado' : 'Ex.: Aluguel de novembro' ?>"></div>
          <div class="form-field form-ficha__c4"><label for="categoria">Categoria *</label>
            <select id="categoria" name="categoria" required><?php foreach (Lancamento::CATEGORIAS[$tipo] as $k => $r): ?><option value="<?= $k ?>" <?= $sel('categoria', $k) ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
          <?php if ($receber): ?>
            <div class="form-field form-ficha__c8"><label for="cliente_id">Cliente</label>
              <select id="cliente_id" name="cliente_id"><option value="">Sem cliente vinculado</option><?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $sel('cliente_id', $c['id']) ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select></div>
            <div class="form-field form-ficha__c4"><label for="documento">Documento</label>
              <input id="documento" name="documento" value="<?= $val('documento') ?>" maxlength="60" placeholder="Nº do boleto / nota" class="campo-codigo"></div>
          <?php else: ?>
            <div class="form-field form-ficha__c6"><label for="fornecedor_id">Fornecedor</label>
              <select id="fornecedor_id" name="fornecedor_id"><option value="">Sem fornecedor</option><?php foreach ($fornecedores as $fo): ?><option value="<?= (int) $fo['id'] ?>" <?= $sel('fornecedor_id', $fo['id']) ?>><?= e($fo['nome']) ?></option><?php endforeach; ?></select></div>
            <div class="form-field form-ficha__c6"><label for="funcionario_id">Funcionário</label>
              <select id="funcionario_id" name="funcionario_id"><option value="">Sem funcionário</option><?php foreach ($funcionarios as $fu): ?><option value="<?= (int) $fu['id'] ?>" <?= $sel('funcionario_id', $fu['id']) ?>><?= e($fu['nome']) ?></option><?php endforeach; ?></select></div>
          <?php endif; ?>
        </div>
      </section>

      <!-- 2. Valor e vencimento (em destaque) -->
      <section class="grupo grupo--destaque" aria-labelledby="g-valor">
        <header class="grupo__cabeca"><span class="grupo__n">2</span><h2 id="g-valor">Valor e vencimento</h2></header>
        <div class="painel-numeros">
          <div class="painel-numeros__bloco">
            <span class="painel-numeros__titulo"><?= icone('coins', 15) ?> Valor</span>
            <div class="painel-numeros__campos">
              <div class="form-field"><label for="valor">Valor total *</label>
                <span class="campo-prefixo"><span>R$</span><input id="valor" name="valor" value="<?= $val('valor') ?>" inputmode="decimal" required placeholder="0,00"></span></div>
              <div class="form-field"><label for="parcelas">Parcelas</label>
                <select id="parcelas" name="parcelas"><?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= $i ?>" <?= (int) ($d['parcelas'] ?? 1) === $i ? 'selected' : '' ?>><?= $i === 1 ? 'À vista' : $i . '×' ?></option><?php endfor; ?></select></div>
            </div>
            <p class="painel-numeros__rodape" data-plano>Informe o valor para ver as parcelas.</p>
          </div>
          <div class="painel-numeros__bloco">
            <span class="painel-numeros__titulo"><?= icone('calendar', 15) ?> Vencimento</span>
            <div class="painel-numeros__campos">
              <div class="form-field"><label for="vencimento">1º vencimento *</label><input type="date" id="vencimento" name="vencimento" value="<?= $val('vencimento') ?>" required></div>
              <div class="form-field"><label for="competencia">Competência</label><input type="date" id="competencia" name="competencia" value="<?= $val('competencia') ?>"></div>
            </div>
            <p class="painel-numeros__rodape">Competência é o mês em que a conta entra no resultado. As demais parcelas vencem todo mês.</p>
          </div>
        </div>
      </section>

      <!-- 3. Complementares -->
      <section class="grupo" aria-labelledby="g-complementar">
        <header class="grupo__cabeca"><span class="grupo__n">3</span><h2 id="g-complementar">Informações complementares</h2></header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c4"><label for="forma_prevista">Forma prevista</label>
            <select id="forma_prevista" name="forma_prevista"><option value="">A definir</option><?php foreach (Lancamento::FORMAS as $k => $r): ?><option value="<?= $k ?>" <?= $sel('forma_prevista', $k) ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
          <?php if (!$receber): ?>
            <div class="form-field form-ficha__c4"><label for="documento">Documento</label>
              <input id="documento" name="documento" value="<?= $val('documento') ?>" maxlength="60" placeholder="Nº do boleto / nota" class="campo-codigo"></div>
          <?php endif; ?>
          <div class="form-field <?= $receber ? 'form-ficha__c8' : 'form-ficha__c4' ?>"><label for="observacao">Observação</label>
            <input id="observacao" name="observacao" value="<?= $val('observacao') ?>" maxlength="255" placeholder="Opcional"></div>
        </div>
      </section>
    </div>
  </div>

  <aside class="form-ficha__lado" aria-label="Resumo da conta">
    <div class="card form-ficha__resumo">
      <div class="form-ficha__perfil">
        <span class="form-ficha__avatar form-ficha__avatar--quadrado"><?= icone($receber ? 'download' : 'upload', 22) ?></span>
        <div class="form-ficha__perfil-texto">
          <strong data-previa-descricao><?= ($d['descricao'] ?? '') !== '' ? $val('descricao') : $titulo ?></strong>
          <span><?= $receber ? 'A receber' : 'A pagar' ?> · <span data-previa-categoria><?= e($categoria) ?></span></span>
        </div>
      </div>
      <div class="resumo-preco">
        <span>Valor total</span>
        <strong data-previa-total>R$ 0,00</strong>
      </div>
      <dl class="form-ficha__dados form-ficha__dados--lista">
        <div><dt><?= icone('receipt', 14) ?> Parcelamento</dt><dd data-previa-plano>À vista</dd></div>
        <div><dt><?= icone('calendar', 14) ?> 1º vencimento</dt><dd data-previa-venc>—</dd></div>
        <div data-previa-ultimo-linha hidden><dt><?= icone('calendar', 14) ?> Último vencimento</dt><dd data-previa-ultimo>—</dd></div>
      </dl>
      <ol class="conta-parcelas" data-previa-parcelas hidden></ol>
      <div class="form-ficha__acoes">
        <button class="btn btn--primary"><?= icone('check') ?> Lançar conta</button>
        <a class="btn btn--ghost" href="<?= $voltar ?>">Cancelar</a>
      </div>
      <p class="form-ficha__nota">Campos com * são obrigatórios.</p>
    </div>
  </aside>
</form>

<script>
  (function () {
    // Só leitura: o resumo ao lado e a linha de parcelas acompanham os campos (mesma regra de Lancamento::criar).
    var f = document.getElementById('form-conta');
    var q = function (s) { return f.querySelector(s); };
    var moeda = function (v) { return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
    var numero = function (t) {
      t = String(t || '').replace(/\s|R\$/g, '');
      if (t.indexOf(',') >= 0) t = t.replace(/\./g, '').replace(',', '.');
      var n = parseFloat(t); return isNaN(n) ? 0 : Math.round(n * 100) / 100;
    };
    var mes = function (iso, i) { if (!iso) return null; var p = iso.split('-'); return new Date(+p[0], +p[1] - 1 + i, +p[2]); };
    var br = function (d) { return d ? d.toLocaleDateString('pt-BR') : '—'; };
    var titulo = <?= json_encode($titulo) ?>;
    // Pelo nome: o campo de data visível (id) e o valor enviado (name) são elementos diferentes depois do campos.js.
    var venc = function () { var c = f.querySelector('[name="vencimento"]'); return c ? c.value : ''; };
    function atualizar() {
      var valor = numero(f.valor.value), n = parseInt(f.parcelas.value, 10) || 1;
      var base = Math.floor(valor / n * 100) / 100, ultima = Math.round((valor - base * (n - 1)) * 100) / 100;
      var plano = n === 1 ? 'À vista' : n + '× de ' + moeda(base);
      q('[data-previa-descricao]').textContent = f.descricao.value.trim() || titulo;
      q('[data-previa-categoria]').textContent = f.categoria.options[f.categoria.selectedIndex].text;
      q('[data-previa-total]').textContent = moeda(valor);
      q('[data-previa-plano]').textContent = plano;
      q('[data-previa-venc]').textContent = br(mes(venc(), 0));
      q('[data-previa-ultimo-linha]').hidden = n === 1;
      q('[data-previa-ultimo]').textContent = br(mes(venc(), n - 1));
      q('[data-plano]').innerHTML = valor > 0
        ? (n === 1 ? 'Pagamento único de <strong>' + moeda(valor) + '</strong>.' : '<strong>' + plano + '</strong>' + (ultima !== base ? ' · a última fica com <strong>' + moeda(ultima) + '</strong>' : ''))
        : 'Informe o valor para ver as parcelas.';
      var ol = q('[data-previa-parcelas]'); ol.innerHTML = '';
      ol.hidden = !(n > 1 && valor > 0 && venc());
      if (!ol.hidden) for (var i = 0; i < n; i++) {
        var li = document.createElement('li');
        li.innerHTML = '<span>' + (i + 1) + 'ª · ' + br(mes(venc(), i)) + '</span><b>' + moeda(i < n - 1 ? base : ultima) + '</b>';
        ol.appendChild(li);
      }
    }
    ['input', 'change'].forEach(function (ev) { f.addEventListener(ev, atualizar); });
    atualizar();
  })();
</script>
