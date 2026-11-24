/* ═══════════════════════════════════════════════════════════════════════════
   Mecanize · Janelas (modais) de criar e editar
   Um link para uma das telas de ROTAS abre a tela numa janela sobre a página atual, sem sair dela.
   O servidor devolve só o conteúdo (cabeçalho X-Mecanize-Modal: 1, ver Controller::tela). O envio
   também acontece na janela: erro de validação volta para a própria janela; salvo, o servidor
   responde {"ir": "./destino"} e a página segue para o destino. Sem JavaScript, ou abrindo o
   endereço direto, a mesma tela aparece como página, com o mesmo desenho.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  // Telas que abrem em janela: todas as de criar e editar, o perfil e as configurações da oficina.
  var ROTAS = ['cliente-form', 'veiculo-form', 'fornecedor-form', 'produto-form', 'usuario-form', 'conta-form', 'agenda-form',
               'estoque-entrada', 'estoque-ajuste', 'os-nova', 'plataforma-nova', 'perfil', 'configuracoes'];
  var CABECALHO = { 'X-Mecanize-Modal': '1' };
  var reduzir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var dlg = null, origem = null, comHistorico = false, rotaJanela = null;

  function rotaDe(href) {
    var u;
    try { u = new URL(href, location.href); } catch (e) { return null; }
    if (u.origin !== location.origin) return null;
    return u.pathname.replace(/\/+$/, '').split('/').pop();
  }

  /* ── Janela ──────────────────────────────────────────────────────────── */
  function criar() {
    dlg = document.createElement('dialog');
    dlg.className = 'mf-dlg';
    dlg.setAttribute('aria-modal', 'true');
    dlg.addEventListener('cancel', function (e) { e.preventDefault(); pedirFechar(); });
    // Clique no fundo escurecido (fora do quadro) fecha, como no Esc.
    dlg.addEventListener('mousedown', function (e) { dlg._fundo = e.target === dlg; });
    dlg.addEventListener('click', function (e) { if (e.target === dlg && dlg._fundo) pedirFechar(); });
    // Fechada por fora do fluxo normal (o navegador fecha no segundo Esc seguido): arruma a página igual.
    dlg.addEventListener('close', function () {
      if (dlg._ok) { dlg._ok = false; return; }
      dlg.classList.remove('is-saindo');
      dlg.innerHTML = '';
      document.documentElement.classList.remove('mf-aberta');
      if (comHistorico && history.state && history.state.mf) { comHistorico = false; history.back(); }
    });
    document.body.appendChild(dlg);
  }

  function abrir(url, registrar) {
    if (!dlg) criar();
    if (!dlg.open) origem = document.activeElement;
    rotaJanela = rotaDe(url);
    dlg.innerHTML = '<div class="mf-carregando" role="status" aria-label="Carregando"><span></span></div>';
    if (!dlg.open) {
      dlg.classList.remove('is-saindo');
      dlg.showModal();
      document.documentElement.classList.add('mf-aberta');
    }
    if (registrar !== false) {
      try { history.pushState({ mf: url }, '', url); comHistorico = true; } catch (e) { comHistorico = false; }
    }
    fetch(url, { headers: CABECALHO, credentials: 'same-origin' })
      .then(responder)
      .catch(function () { location.href = url; });
  }

  // Fecha a janela. Se ela entrou no histórico, volta uma posição (o "voltar" do navegador faz o mesmo).
  function fechar() {
    if (!dlg || !dlg.open) return;
    if (comHistorico && history.state && history.state.mf) { history.back(); return; }
    esconder();
  }
  function esconder() {
    if (!dlg || !dlg.open || dlg.classList.contains('is-saindo')) return;
    comHistorico = false;
    var fim = function () {
      dlg.classList.remove('is-saindo');
      dlg._ok = true;
      dlg.close();
      dlg.innerHTML = '';
      document.documentElement.classList.remove('mf-aberta');
      if (origem && origem.focus && document.contains(origem)) origem.focus({ preventScroll: true });
    };
    if (reduzir) { fim(); return; }
    dlg.classList.add('is-saindo');
    setTimeout(fim, 180);
  }
  // Fechar com alterações não salvas pergunta antes (Esc, fundo, X e Cancelar).
  function pedirFechar() {
    var f = dlg && dlg.querySelector('form[data-alterado]');
    if (f && f.dataset.alterado && window.Mecanize && window.Mecanize.confirmar) {
      window.Mecanize.confirmar('As alterações feitas nesta janela ainda não foram salvas.', {
        titulo: 'Descartar alterações?', sim: 'Descartar', nao: 'Continuar editando', perigo: true
      }).then(function (ok) { if (ok) fechar(); });
      return;
    }
    fechar();
  }
  // Voltar do navegador fecha a janela; avançar de novo reabre.
  window.addEventListener('popstate', function (e) {
    if (dlg && dlg.open) { esconder(); return; }
    if (e.state && e.state.mf) { comHistorico = true; abrir(e.state.mf, false); }
  });

  /* ── Resposta do servidor ────────────────────────────────────────────── */
  function responder(res) {
    if (res.redirected) { location.replace(res.url); return; }
    var tipo = res.headers.get('Content-Type') || '';
    if (tipo.indexOf('json') !== -1) {
      return res.json().then(function (d) {
        if (d && d.ir) { seguir(d.ir); return; }
        mostrarErro((d && d.mensagem) || 'Não foi possível concluir. Tente de novo.');
      });
    }
    var aviso = null;
    try { aviso = JSON.parse(decodeURIComponent(res.headers.get('X-Mecanize-Aviso') || '')); } catch (e) { aviso = null; }
    return res.text().then(function (html) { montar(html); if (aviso && aviso.mensagem) avisar(aviso.mensagem, aviso.tipo); });
  }
  // Salvo: a página segue para o destino no lugar da entrada da janela no histórico. Se o destino é a própria
  // janela (perfil, configurações), ela recarrega no lugar e mostra o aviso do servidor no topo.
  function seguir(url) {
    if (dlg && dlg.open && rotaDe(url) === rotaJanela) {
      dlg.querySelectorAll('form[data-alterado]').forEach(function (f) { delete f.dataset.alterado; });
      fetch(url, { headers: CABECALHO, credentials: 'same-origin' }).then(responder).catch(function () { location.href = url; });
      return;
    }
    if (comHistorico) location.replace(url); else location.href = url;
  }
  function avisar(msg, tipo) {
    var mf = dlg && dlg.querySelector('.mf');
    var area = mf && (mf.querySelector('.mf__conteudo') || mf);
    if (!area) return;
    tipo = /^(erro|aviso|sucesso|info)$/.test(tipo || '') ? tipo : 'sucesso';
    var a = document.createElement('div');
    a.className = 'alerta alerta--' + tipo;
    a.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');
    a.setAttribute('data-mf-erro', '');
    a.textContent = msg;
    area.insertBefore(a, area.firstChild);
    area.scrollTop = 0;
  }

  function montar(html) {
    var alterado = dlg.querySelector('form[data-alterado]');
    dlg.innerHTML = html;
    var mf = dlg.querySelector('.mf');
    if (!mf) { dlg.innerHTML = ''; esconder(); return; }
    if (window.aplicarMascaras) window.aplicarMascaras(dlg);
    // Scripts que vêm no conteúdo não rodam sozinhos quando entram por innerHTML: recria cada um, na ordem.
    dlg.querySelectorAll('script').forEach(function (velho) {
      var novo = document.createElement('script');
      [].forEach.call(velho.attributes, function (at) { novo.setAttribute(at.name, at.value); });
      if (novo.src) novo.async = false;
      novo.textContent = velho.textContent;
      velho.parentNode.replaceChild(novo, velho);
    });
    preparar(mf, true);
    var principal = mf.matches('form') ? mf : mf.querySelector('form');
    if (alterado && principal) principal.dataset.alterado = '1';
    var erro = mf.querySelector('.alerta--erro');
    var foco = erro ? null : mf.querySelector('[autofocus]');
    if (!foco) foco = mf.querySelector('.mf__conteudo input:not([type=hidden]):not([type=radio]):not([type=checkbox]), .mf__conteudo select, .mf__conteudo textarea');
    if (foco && !erro) foco.focus({ preventScroll: true });
    else if (erro) { var t = mf.querySelector('.mf__titulo'); if (t) { t.setAttribute('tabindex', '-1'); t.focus({ preventScroll: true }); } }
  }

  function mostrarErro(msg) {
    var mf = dlg && dlg.querySelector('.mf');
    if (!mf) {
      dlg.innerHTML = '<div class="mf mf--erro"><header class="mf__topo"><h2 class="mf__titulo">Não foi possível abrir</h2>'
        + '<button type="button" class="mf__fechar" data-mf-fechar aria-label="Fechar">×</button></header>'
        + '<div class="mf-aviso"><p></p></div></div>';
      dlg.querySelector('.mf-aviso p').textContent = msg;
      dlg.querySelector('[data-mf-fechar]').addEventListener('click', fechar);
      return;
    }
    liberar(mf);
    mf.querySelectorAll('form').forEach(liberar);
    var area = mf.querySelector('.mf__conteudo') || mf;
    var velho = area.querySelector('[data-mf-erro]');
    if (velho) velho.remove();
    var a = document.createElement('div');
    a.className = 'alerta alerta--erro';
    a.setAttribute('role', 'alert');
    a.setAttribute('data-mf-erro', '');
    a.textContent = msg;
    area.insertBefore(a, area.firstChild);
    area.scrollTo({ top: 0, behavior: reduzir ? 'auto' : 'smooth' });
  }

  function liberar(form) {
    delete form.dataset.enviando;
    form.querySelectorAll('.is-enviando').forEach(function (b) { b.classList.remove('is-enviando'); b.removeAttribute('aria-busy'); });
  }

  /* ── Conteúdo: seções, alterações, envio e fechar ────────────────────── */
  function preparar(mf, naJanela) {
    var rolagem = mf.querySelector('.mf__conteudo');
    var links = [].slice.call(mf.querySelectorAll('.mf__nav a[href^="#"]'));
    var secoes = links.map(function (a) { return mf.querySelector(a.getAttribute('href')); });
    var travado = null, pausa = 0;

    function marcar(i) {
      links.forEach(function (a, k) {
        a.classList.toggle('is-ativa', k === i);
        if (k === i) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
      });
      // No celular o menu de seções é uma faixa que desliza: mantém a seção ativa à vista.
      var ativo = links[i];
      if (ativo && ativo.parentNode.scrollWidth > ativo.parentNode.clientWidth) {
        ativo.parentNode.scrollTo({ left: ativo.offsetLeft - 16, behavior: reduzir ? 'auto' : 'smooth' });
      }
    }
    links.forEach(function (a, i) {
      a.addEventListener('click', function (e) {
        var s = secoes[i];
        if (!s || !rolagem) return;
        e.preventDefault();
        travado = i;
        marcar(i);
        rolagem.scrollTo({ top: Math.max(0, s.offsetTop - 8), behavior: reduzir ? 'auto' : 'smooth' });
      });
    });
    if (rolagem && links.length) {
      rolagem.addEventListener('scroll', function () {
        clearTimeout(pausa);
        pausa = setTimeout(function () { travado = null; }, 140);
        if (travado !== null) return;
        var y = rolagem.scrollTop + 32, ativo = 0;
        secoes.forEach(function (s, k) { if (s && s.offsetTop <= y) ativo = k; });
        if (rolagem.scrollTop + rolagem.clientHeight >= rolagem.scrollHeight - 4) ativo = secoes.length - 1;
        marcar(ativo);
      }, { passive: true });
    }

    // Todos os formulários do conteúdo (a ficha e, às vezes, ações à parte como excluir ou enviar logotipo).
    var forms = mf.matches('form') ? [mf].concat([].slice.call(mf.querySelectorAll('form'))) : [].slice.call(mf.querySelectorAll('form'));
    forms.forEach(function (form) {
      if (form.hasAttribute('data-mf-ignorar-alteracao')) return;
      var mexeu = function () { form.dataset.alterado = '1'; };
      form.addEventListener('input', mexeu);
      form.addEventListener('change', mexeu);
    });

    if (!naJanela) return;

    mf.querySelectorAll('[data-mf-fechar]').forEach(function (b) {
      b.addEventListener('click', function (e) { e.preventDefault(); pedirFechar(); });
    });

    // Envio pela janela (só POST; um formulário com data-mf-pagina ou target envia do jeito normal, saindo da janela).
    // Registrado no próprio formulário, roda antes do envio padrão de js/campos.js (que então ignora, por já estar
    // com defaultPrevented). A confirmação (data-confirmar) vem antes de tudo.
    forms.forEach(function (form) {
      if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post' || form.target || form.hasAttribute('data-mf-pagina')) return;
      form.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        e.preventDefault();
        if (form.dataset.enviando) return;
        form.dataset.enviando = '1';
        var bt = e.submitter || form.querySelector('button[type=submit], button:not([type])');
        if (bt) { bt.classList.add('is-enviando'); bt.setAttribute('aria-busy', 'true'); }
        var dados = new FormData(form);
        if (e.submitter && e.submitter.name) dados.append(e.submitter.name, e.submitter.value);
        fetch(form.action, { method: 'POST', body: dados, headers: CABECALHO, credentials: 'same-origin' })
          .then(responder)
          .catch(function () { liberar(form); mostrarErro('Sem conexão com o servidor. Confira a internet e tente de novo.'); });
      });
    });
  }

  /* ── Início ──────────────────────────────────────────────────────────── */
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || a.target || a.hasAttribute('download') || a.closest('.mf-dlg')) return;
    if (ROTAS.indexOf(rotaDe(a.href)) === -1 || !window.fetch || typeof HTMLDialogElement === 'undefined') return;
    e.preventDefault();
    // Fecha menus abertos (Criar, "⋯") antes de abrir a janela.
    document.querySelectorAll('details.menu-mais[open]').forEach(function (d) { d.removeAttribute('open'); });
    var criar = document.getElementById('painel-criar');
    if (criar && !criar.hidden) { criar.hidden = true; var bc = document.querySelector('[data-abrir-criar]'); if (bc) bc.setAttribute('aria-expanded', 'false'); }
    abrir(a.href);
  });

  // Tela aberta direto pelo endereço: mesmo desenho, como página (seções e alterações funcionam igual).
  document.querySelectorAll('.content .mf').forEach(function (mf) { preparar(mf, false); });

  window.Mecanize = window.Mecanize || {};
  window.Mecanize.abrirJanela = function (url) { abrir(url); };
  // Para outros scripts (ex.: linha de tabela clicável): abre na janela se o endereço for de uma tela de janela.
  window.Mecanize.abrirSeJanela = function (url) {
    if (ROTAS.indexOf(rotaDe(url)) === -1 || !window.fetch || typeof HTMLDialogElement === 'undefined') return false;
    abrir(new URL(url, location.href).href);
    return true;
  };
})();
