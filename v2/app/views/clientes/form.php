<?php
/**
 * Cadastro e edição de cliente. Ficha em seções (identificação, contato, endereço, observações) e,
 * ao lado, um resumo fixo com a prévia do cliente e as ações de salvar.
 * Os campos, nomes, validações e o destino do formulário são os mesmos de sempre.
 * Espera: $c (dados), $id (null = novo), $erro (opcional)
 */
$titulo = $id ? 'Editar cliente' : 'Novo cliente';
$v = fn($k) => e($c[$k] ?? '');
$pj = ($c['tipo_pessoa'] ?? 'PF') === 'PJ';
$nome = trim((string) ($c['nome'] ?? ''));
$partes = $nome !== '' ? preg_split('/\s+/', $nome) : [];
$iniciais = $partes ? mb_strtoupper(mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '')) : '';
$doc = documento_fmt($c['documento'] ?? '');
$voltar = $id ? './cliente?id=' . (int) $id : './clientes';
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./clientes">Clientes</a><?= $id ? ' · ' . $v('nome') : ' · cadastro de pessoa física ou jurídica' ?></p>
  </div>
</div>

<form class="form-ficha" method="post" action="./cliente-salvar<?= $id ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>

  <div class="form-ficha__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <section class="card form-ficha__secao" aria-labelledby="sec-identificacao">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('user', 18) ?></span>
        <div><h2 id="sec-identificacao">Identificação</h2><p>Quem é o cliente e o documento que sai no orçamento e na OS.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c12">
          <span class="form-ficha__rotulo" id="rotulo-tipo">Tipo</span>
          <div class="segmento" role="radiogroup" aria-labelledby="rotulo-tipo">
            <label><input type="radio" name="tipo_pessoa" value="PF" <?= $pj ? '' : 'checked' ?>><span><?= icone('user', 15) ?> Pessoa física</span></label>
            <label><input type="radio" name="tipo_pessoa" value="PJ" <?= $pj ? 'checked' : '' ?>><span><?= icone('folder', 15) ?> Pessoa jurídica</span></label>
          </div>
        </div>
        <div class="form-field form-ficha__c8">
          <label for="nome"><?= $pj ? 'Razão social / nome' : 'Nome completo' ?> *</label>
          <input id="nome" name="nome" value="<?= $v('nome') ?>" required maxlength="150" autofocus>
        </div>
        <div class="form-field form-ficha__c4">
          <label for="documento" id="rotulo-doc"><?= $pj ? 'CNPJ' : 'CPF' ?></label>
          <input id="documento" name="documento" value="<?= e($doc) ?>" data-mascara="documento" inputmode="numeric">
        </div>
      </div>
    </section>

    <section class="card form-ficha__secao" aria-labelledby="sec-contato">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('phone', 18) ?></span>
        <div><h2 id="sec-contato">Contato</h2><p>Como a oficina fala com o cliente sobre orçamentos e entregas.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c5"><label for="telefone">Telefone / WhatsApp</label>
          <span class="campo-icone"><?= icone('phone') ?><input id="telefone" name="telefone" value="<?= e(telefone_fmt($c['telefone'] ?? '')) ?>" data-mascara="telefone" inputmode="tel"></span></div>
        <div class="form-field form-ficha__c7"><label for="email">E-mail</label>
          <span class="campo-icone"><?= icone('mail') ?><input id="email" name="email" type="email" value="<?= $v('email') ?>" maxlength="150"></span></div>
        <label class="opcao form-ficha__c12">
          <input type="checkbox" name="aceita_contato" value="1" <?= !empty($c['aceita_contato']) ? 'checked' : '' ?>>
          <span class="opcao__texto"><strong>Aceita receber contato da oficina</strong><small>O cliente autorizou mensagens por WhatsApp e e-mail.</small></span>
        </label>
      </div>
    </section>

    <section class="card form-ficha__secao" aria-labelledby="sec-endereco">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('location', 18) ?></span>
        <div><h2 id="sec-endereco">Endereço</h2><p>Opcional. Aparece no cadastro e nos documentos impressos.</p></div>
      </header>
      <div class="form-ficha__grade">
        <div class="form-field form-ficha__c3"><label for="cep">CEP</label><input id="cep" name="cep" value="<?= $v('cep') ?>" data-mascara="cep" inputmode="numeric" placeholder="00000-000"></div>
        <div class="form-field form-ficha__c9"><label for="endereco">Endereço</label><input id="endereco" name="endereco" value="<?= $v('endereco') ?>" maxlength="255" placeholder="Rua e número"></div>
        <div class="form-field form-ficha__c5"><label for="bairro">Bairro</label><input id="bairro" name="bairro" value="<?= $v('bairro') ?>" maxlength="100"></div>
        <div class="form-field form-ficha__c5 form-ficha__m4"><label for="cidade">Cidade</label><input id="cidade" name="cidade" value="<?= $v('cidade') ?>" maxlength="100"></div>
        <div class="form-field form-ficha__c2"><label for="uf">UF</label><input id="uf" name="uf" value="<?= $v('uf') ?>" maxlength="2" style="text-transform:uppercase" placeholder="SP"></div>
      </div>
    </section>

    <section class="card form-ficha__secao" aria-labelledby="sec-obs">
      <header class="form-ficha__cabeca">
        <span class="form-ficha__icone"><?= icone('file', 18) ?></span>
        <div><h2 id="sec-obs">Observações</h2><p>Preferências, combinados e o que a equipe precisa saber no atendimento.</p></div>
      </header>
      <div class="form-field"><label for="observacoes" class="sr-only">Observações</label><textarea id="observacoes" name="observacoes" rows="4"><?= $v('observacoes') ?></textarea></div>
    </section>
  </div>

  <aside class="form-ficha__lado" aria-label="Resumo do cliente">
    <div class="card form-ficha__resumo">
      <div class="form-ficha__perfil">
        <span class="form-ficha__avatar" data-previa-iniciais><?= $iniciais !== '' ? e($iniciais) : icone('user', 22) ?></span>
        <div class="form-ficha__perfil-texto">
          <strong data-previa-nome><?= $nome !== '' ? e($nome) : 'Novo cliente' ?></strong>
          <span><span data-previa-tipo><?= $pj ? 'Pessoa jurídica' : 'Pessoa física' ?></span><span data-previa-doc><?= $doc !== '' ? ' · ' . e($doc) : '' ?></span></span>
        </div>
      </div>
      <?php if ($id && !empty($c['criado_em'])): ?>
        <dl class="form-ficha__dados">
          <div><dt>Cliente desde</dt><dd><?= data_br($c['criado_em']) ?></dd></div>
          <div><dt>Situação</dt><dd><?= !isset($c['ativo']) || $c['ativo'] ? 'Ativo' : 'Inativo' ?></dd></div>
        </dl>
      <?php endif; ?>
      <div class="form-ficha__acoes">
        <button class="btn btn--primary"><?= icone('check') ?> Salvar cliente</button>
        <a class="btn btn--ghost" href="<?= $voltar ?>">Cancelar</a>
      </div>
      <p class="form-ficha__nota">Campos com * são obrigatórios.</p>
    </div>
  </aside>
</form>

<script>
  (function () {
    // Tipo de pessoa: troca o rótulo do nome e do documento, e a prévia ao lado acompanha o que é digitado.
    var nome = document.getElementById('nome'), doc = document.getElementById('documento');
    var pNome = document.querySelector('[data-previa-nome]'), pIni = document.querySelector('[data-previa-iniciais]');
    var pTipo = document.querySelector('[data-previa-tipo]'), pDoc = document.querySelector('[data-previa-doc]');
    var iconeVazio = <?= json_encode(icone('user', 22)) ?>;
    function tipo() {
      var pj = (document.querySelector('input[name=tipo_pessoa]:checked') || {}).value === 'PJ';
      document.getElementById('rotulo-doc').textContent = pj ? 'CNPJ' : 'CPF';
      document.querySelector('label[for=nome]').textContent = (pj ? 'Razão social / nome' : 'Nome completo') + ' *';
      pTipo.textContent = pj ? 'Pessoa jurídica' : 'Pessoa física';
    }
    function previa() {
      var n = nome.value.trim(), p = n ? n.split(/\s+/) : [];
      pNome.textContent = n || 'Novo cliente';
      var ini = p.length ? (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase() : '';
      if (ini) pIni.textContent = ini; else if (iconeVazio) pIni.innerHTML = iconeVazio; else pIni.textContent = '';
      pDoc.textContent = doc.value.trim() ? ' · ' + doc.value.trim() : '';
    }
    document.querySelectorAll('input[name=tipo_pessoa]').forEach(function (r) { r.addEventListener('change', tipo); });
    nome.addEventListener('input', previa);
    doc.addEventListener('input', previa);
  })();
</script>
