/*
 * Campos no padrão do Mecanize — nada de janelinhas do navegador.
 *
 *  - <select>: a lista de opções abre num painel do sistema (o select continua o mesmo: valor, validação,
 *    eventos "change" e o que as telas fazem com ele não mudam).
 *  - Datas e horários (type=date, datetime-local, time): campo de texto dd/mm/aaaa [hh:mm] com calendário
 *    e horários do sistema. O valor enviado e o .value lido pelas telas continuam em ISO (aaaa-mm-dd).
 *  - <datalist>: sugestões no mesmo painel.
 *  - Mensagens de validação embaixo do campo, no lugar do balão do navegador.
 *  - Confirmações (data-confirmar e Mecanize.confirmar) numa janela do sistema, no lugar do confirm().
 *
 * Um campo com data-nativo fica como o navegador desenha.
 */
(function () {
  'use strict';
  if (window.Mecanize && window.Mecanize.campos) return;
  var M = window.Mecanize = window.Mecanize || {};
  M.campos = true;

  var SVG = {
    check: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
    esq: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>',
    dir: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>',
    cal: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM4 10h16M8 3v4M16 3v4"/></svg>',
    hora: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 12a8.5 8.5 0 1 0 17 0a8.5 8.5 0 1 0-17 0M12 7.5V12l3 2"/></svg>',
    busca: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 11a7 7 0 1 0 14 0a7 7 0 1 0-14 0M20 20l-4-4"/></svg>'
  };
  var semAcento = function (s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
  var disparar = function (el, tipo) { el.dispatchEvent(new Event(tipo, { bubbles: true })); };
  var pad = function (n) { return String(n).padStart(2, '0'); };

  /* ── Painel flutuante (um aberto por vez) ─────────────────────────────── */
  var aberto = null; // { painel, ancora, fechar }
  function abrirPainel(ancora, painel, aoFechar, larguraMin) {
    fecharPainel();
    var casa = ancora.closest('dialog[open]') || document.body;
    painel.classList.add('mec-pop');
    casa.appendChild(painel);
    aberto = { painel: painel, ancora: ancora, fechar: aoFechar, larguraMin: larguraMin };
    posicionar();
    requestAnimationFrame(function () { painel.classList.add('is-aberto'); });
    return painel;
  }
  function posicionar() {
    if (!aberto) return;
    var r = aberto.ancora.getBoundingClientRect(), p = aberto.painel, m = 8;
    if (aberto.larguraMin !== false) p.style.minWidth = Math.max(r.width, aberto.larguraMin || 0) + 'px';
    var h = p.offsetHeight, w = p.offsetWidth;
    var embaixo = window.innerHeight - r.bottom - m, emcima = r.top - m;
    var acima = h + 6 > embaixo && emcima > embaixo;
    p.style.top = (acima ? Math.max(m, r.top - h - 6) : Math.min(r.bottom + 6, window.innerHeight - h - m)) + 'px';
    p.style.left = Math.max(m, Math.min(r.left, window.innerWidth - w - m)) + 'px';
    p.classList.toggle('is-acima', acima);
  }
  function fecharPainel() {
    if (!aberto) return;
    var a = aberto; aberto = null;
    a.painel.remove();
    if (a.fechar) a.fechar();
  }
  document.addEventListener('pointerdown', function (e) {
    if (aberto && !aberto.painel.contains(e.target) && !aberto.ancora.contains(e.target)) fecharPainel();
  }, true);
  window.addEventListener('resize', posicionar);
  window.addEventListener('scroll', function (e) { if (aberto && !aberto.painel.contains(e.target)) posicionar(); }, true);

  /* ── Lista de opções (select e sugestões) ─────────────────────────────── */
  // itens: [{ grupo } | { valor, texto, desabilitado, selecionado }]
  function painelLista(itens, opts) {
    var p = document.createElement('div');
    p.className = 'mec-lista';
    p.setAttribute('role', 'listbox');
    var busca = null, corpo = document.createElement('div');
    corpo.className = 'mec-lista__corpo';
    if (opts.busca) {
      var caixa = document.createElement('label');
      caixa.className = 'mec-lista__busca';
      caixa.innerHTML = SVG.busca + '<input type="text" placeholder="Buscar" autocomplete="off" spellcheck="false" aria-label="Buscar opção">';
      busca = caixa.querySelector('input');
      p.appendChild(caixa);
    }
    p.appendChild(corpo);
    var ativo = -1, visiveis = [];

    function desenhar(filtro) {
      corpo.innerHTML = ''; visiveis = [];
      var f = semAcento(filtro), grupoPendente = null;
      itens.forEach(function (it) {
        if (it.grupo !== undefined) { grupoPendente = it.grupo; return; }
        if (f && semAcento(it.texto).indexOf(f) < 0) return;
        if (grupoPendente !== null) {
          var g = document.createElement('div'); g.className = 'mec-lista__grupo'; g.textContent = grupoPendente; corpo.appendChild(g); grupoPendente = null;
        }
        var o = document.createElement('div');
        o.className = 'mec-lista__opcao' + (it.selecionado ? ' is-selecionada' : '') + (it.desabilitado ? ' is-desabilitada' : '') + (it.vazio ? ' is-vazia' : '');
        o.setAttribute('role', 'option');
        o.setAttribute('aria-selected', it.selecionado ? 'true' : 'false');
        o.innerHTML = '<span class="mec-lista__texto"></span>' + (it.selecionado ? SVG.check : '');
        o.querySelector('.mec-lista__texto').textContent = it.texto;
        o._item = it;
        if (!it.desabilitado) {
          o.addEventListener('pointerdown', function (e) { e.preventDefault(); });
          o.addEventListener('click', function () { opts.escolher(it); });
          o.addEventListener('pointermove', function () { marcar(visiveis.indexOf(o), false); });
          visiveis.push(o);
        }
        corpo.appendChild(o);
      });
      if (!visiveis.length) {
        var v = document.createElement('div'); v.className = 'mec-lista__nada'; v.textContent = 'Nada encontrado'; corpo.appendChild(v);
      }
      var sel = visiveis.findIndex(function (o) { return o._item.selecionado; });
      marcar(f ? 0 : sel, true);
    }
    function marcar(i, rolar) {
      if (visiveis[ativo]) visiveis[ativo].classList.remove('is-ativa');
      ativo = Math.max(-1, Math.min(i, visiveis.length - 1));
      var o = visiveis[ativo];
      if (o) { o.classList.add('is-ativa'); if (rolar) o.scrollIntoView({ block: 'nearest' }); }
    }
    p.teclado = function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); marcar(ativo + 1, true); return true; }
      if (e.key === 'ArrowUp') { e.preventDefault(); marcar(Math.max(0, ativo - 1), true); return true; }
      if (e.key === 'Home' && !busca) { e.preventDefault(); marcar(0, true); return true; }
      if (e.key === 'End' && !busca) { e.preventDefault(); marcar(visiveis.length - 1, true); return true; }
      if (e.key === 'Enter') { if (visiveis[ativo]) { e.preventDefault(); opts.escolher(visiveis[ativo]._item); return true; } }
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); fecharPainel(); return true; }
      if (e.key === 'Tab') { fecharPainel(); return false; }
      return false;
    };
    p.filtrar = desenhar;
    if (busca) {
      busca.addEventListener('input', function () { desenhar(busca.value); posicionar(); });
      busca.addEventListener('keydown', function (e) { p.teclado(e); });
    }
    desenhar('');
    p.busca = busca;
    return p;
  }

  /* ── <select> ─────────────────────────────────────────────────────────── */
  function itensDoSelect(sel) {
    var itens = [];
    Array.prototype.forEach.call(sel.children, function (n) {
      if (n.tagName === 'OPTGROUP') {
        itens.push({ grupo: n.label });
        Array.prototype.forEach.call(n.children, function (o) { itens.push(itemOpcao(o, n.disabled)); });
      } else if (n.tagName === 'OPTION') {
        itens.push(itemOpcao(n, false));
      }
    });
    return itens;
  }
  function itemOpcao(o, grupoDesabilitado) {
    return { valor: o.value, texto: o.textContent.trim().replace(/\s+/g, ' '), desabilitado: o.disabled || grupoDesabilitado || o.hidden,
             selecionado: o.selected, opcao: o, vazio: o.value === '' };
  }
  function abrirSelect(sel, porToque) {
    if (sel.disabled || (aberto && aberto.ancora === sel)) return;
    var itens = itensDoSelect(sel);
    var qtd = itens.filter(function (i) { return i.valor !== undefined; }).length;
    var lista = painelLista(itens, {
      busca: qtd > 10,
      escolher: function (it) {
        fecharPainel();
        if (it.opcao.selected) { sel.focus(); return; }
        it.opcao.selected = true;
        disparar(sel, 'input'); disparar(sel, 'change');
        sel.focus();
      }
    });
    sel.classList.add('is-aberto');
    sel.setAttribute('aria-expanded', 'true');
    abrirPainel(sel, lista, function () { sel.classList.remove('is-aberto'); sel.setAttribute('aria-expanded', 'false'); });
    if (lista.busca && !porToque) setTimeout(function () { lista.busca.focus(); }, 0);
  }
  function prepararSelect(sel) {
    if (sel._mec || sel.multiple || sel.size > 1 || sel.hasAttribute('data-nativo')) return;
    sel._mec = true;
    sel.setAttribute('aria-haspopup', 'listbox');
    sel.setAttribute('aria-expanded', 'false');
    // O navegador abre a própria lista no pressionar do mouse/toque: abrimos a nossa no lugar.
    var abrirPeloPonteiro = function (e) {
      if (e.button !== undefined && e.button !== 0) return;
      e.preventDefault();
      if (aberto && aberto.ancora === sel) { fecharPainel(); return; }
      sel.focus();
      abrirSelect(sel, e.type === 'touchstart' || e.pointerType === 'touch');
    };
    sel.addEventListener('mousedown', abrirPeloPonteiro);
    sel.addEventListener('touchstart', function (e) { e.preventDefault(); abrirPeloPonteiro(e); }, { passive: false });
    sel.addEventListener('keydown', function (e) {
      if (aberto && aberto.ancora === sel) { aberto.painel.teclado(e); return; }
      if ([' ', 'Enter', 'ArrowDown', 'ArrowUp', 'F4'].indexOf(e.key) >= 0 || (e.altKey && e.key === 'ArrowDown')) {
        e.preventDefault(); abrirSelect(sel);
      }
    });
    sel.addEventListener('blur', function () {
      setTimeout(function () {
        if (aberto && aberto.ancora === sel && !aberto.painel.contains(document.activeElement)) fecharPainel();
      }, 0);
    });
  }

  /* ── Datas e horários ─────────────────────────────────────────────────── */
  var MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  var protoValor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
  var FORMATOS = {
    date: { modelo: 'dd/mm/aaaa', tam: 10 },
    'datetime-local': { modelo: 'dd/mm/aaaa hh:mm', tam: 16 },
    time: { modelo: 'hh:mm', tam: 5 }
  };
  function isoParaTexto(tipo, iso) {
    if (!iso) return '';
    if (tipo === 'time') return iso.slice(0, 5);
    var d = iso.slice(0, 10).split('-');
    if (d.length < 3) return '';
    var t = d[2] + '/' + d[1] + '/' + d[0];
    return tipo === 'datetime-local' ? t + ' ' + (iso.slice(11, 16) || '00:00') : t;
  }
  function textoParaIso(tipo, txt) {
    txt = txt.trim();
    if (!txt) return '';
    var m;
    if (tipo === 'time') {
      m = txt.match(/^(\d{2}):(\d{2})$/);
      return m && +m[1] < 24 && +m[2] < 60 ? txt : null;
    }
    m = txt.match(/^(\d{2})\/(\d{2})\/(\d{4})(?: (\d{2}):(\d{2}))?$/);
    if (!m) return null;
    var dt = new Date(+m[3], +m[2] - 1, +m[1]);
    if (dt.getFullYear() !== +m[3] || dt.getMonth() !== +m[2] - 1 || dt.getDate() !== +m[1]) return null;
    var iso = m[3] + '-' + m[2] + '-' + m[1];
    if (tipo === 'datetime-local') {
      if (m[4] === undefined || +m[4] > 23 || +m[5] > 59) return null;
      return iso + 'T' + m[4] + ':' + m[5];
    }
    return m[4] === undefined ? iso : null;
  }
  function mascarar(tipo, txt) {
    var d = txt.replace(/\D/g, ''), r = '';
    if (tipo === 'time') {
      d = d.slice(0, 4);
      return d.length > 2 ? d.slice(0, 2) + ':' + d.slice(2) : d;
    }
    d = d.slice(0, tipo === 'datetime-local' ? 12 : 8);
    for (var i = 0; i < d.length; i++) {
      if (i === 2 || i === 4) r += '/';
      if (i === 8) r += ' ';
      if (i === 10) r += ':';
      r += d[i];
    }
    return r;
  }
  function isoHoje() { var h = new Date(); return h.getFullYear() + '-' + pad(h.getMonth() + 1) + '-' + pad(h.getDate()); }

  function prepararData(inp) {
    var tipo = inp.type;
    if (inp._mec || !FORMATOS[tipo] || inp.hasAttribute('data-nativo')) return;
    inp._mec = true;
    var fmt = FORMATOS[tipo];
    var oculto = document.createElement('input');
    oculto.type = 'hidden';
    var iso = inp.value;
    if (inp.name) { oculto.name = inp.name; inp.removeAttribute('name'); }
    if (inp.getAttribute('form')) oculto.setAttribute('form', inp.getAttribute('form'));
    oculto.value = iso;
    inp.insertAdjacentElement('afterend', oculto);
    var min = inp.getAttribute('min') || '', max = inp.getAttribute('max') || '';
    inp.type = 'text';
    inp.classList.add('campo-data');
    inp.dataset.tipoData = tipo;
    inp.setAttribute('inputmode', 'numeric');
    inp.setAttribute('autocomplete', 'off');
    inp.setAttribute('maxlength', fmt.tam);
    if (!inp.placeholder) inp.placeholder = fmt.modelo;
    inp.setAttribute('aria-haspopup', 'dialog');
    protoValor.set.call(inp, isoParaTexto(tipo, iso));

    // .value continua em ISO para as telas (ler e escrever).
    Object.defineProperty(inp, 'value', {
      configurable: true,
      get: function () { return oculto.value; },
      set: function (v) { oculto.value = v || ''; protoValor.set.call(inp, isoParaTexto(tipo, v || '')); inp.setCustomValidity(''); limparAviso(inp); }
    });
    inp._texto = function () { return protoValor.get.call(inp); };

    // Ícone: calendário ou relógio, que também abre o painel (menos dentro de campos que já têm ícone).
    if (!inp.closest('.campo-periodo')) {
      var caixa = document.createElement('span');
      caixa.className = 'campo-data-caixa';
      inp.parentNode.insertBefore(caixa, inp);
      caixa.appendChild(inp);
      var bt = document.createElement('button');
      bt.type = 'button'; bt.className = 'campo-data-botao'; bt.tabIndex = -1;
      bt.setAttribute('aria-label', tipo === 'time' ? 'Escolher horário' : 'Abrir calendário');
      bt.innerHTML = tipo === 'time' ? SVG.hora : SVG.cal;
      bt.addEventListener('pointerdown', function (e) { e.preventDefault(); });
      bt.addEventListener('click', function () { if (aberto && aberto.ancora === inp) fecharPainel(); else { inp.focus(); abrirData(inp, tipo, min, max); } });
      caixa.appendChild(bt);
    }

    function validar(final) {
      var txt = protoValor.get.call(inp), v = textoParaIso(tipo, txt);
      if (v === null) {
        oculto.value = '';
        inp.setCustomValidity(final && txt.trim() ? 'Data incompleta ou inválida: use ' + fmt.modelo + '.' : '');
        return;
      }
      var dia = v.slice(0, 10);
      if (tipo !== 'time' && min && dia < min) { inp.setCustomValidity('A data precisa ser a partir de ' + isoParaTexto('date', min) + '.'); oculto.value = ''; return; }
      if (tipo !== 'time' && max && dia > max.slice(0, 10)) { inp.setCustomValidity('A data precisa ser até ' + isoParaTexto('date', max) + '.'); oculto.value = ''; return; }
      inp.setCustomValidity('');
      oculto.value = v;
    }
    // Captura: roda antes dos ouvintes das telas, que leem .value já atualizado.
    inp.addEventListener('input', function () {
      var antes = protoValor.get.call(inp), depois = mascarar(tipo, antes);
      if (depois !== antes) protoValor.set.call(inp, depois);
      validar(false);
      if (aberto && aberto.ancora === inp && aberto.painel.atualizar) aberto.painel.atualizar();
    }, true);
    inp.addEventListener('change', function () { validar(true); }, true);
    inp.addEventListener('blur', function () { validar(true); });
    inp.addEventListener('click', function () { if (!(aberto && aberto.ancora === inp)) abrirData(inp, tipo, min, max); });
    inp.addEventListener('keydown', function (e) {
      if (aberto && aberto.ancora === inp) {
        if (e.key === 'Escape') { e.preventDefault(); fecharPainel(); }
        else if (e.key === 'ArrowDown' && aberto.painel.focar) { e.preventDefault(); aberto.painel.focar(); }
        else if (e.key === 'Tab') fecharPainel();
        return;
      }
      if ((e.key === 'ArrowDown' && e.altKey) || e.key === 'F4') { e.preventDefault(); abrirData(inp, tipo, min, max); }
    });
    var form = inp.form;
    if (form) form.addEventListener('reset', function () { setTimeout(function () { inp.value = inp.defaultValue ? textoParaIso(tipo, inp.defaultValue) || '' : ''; }, 0); });
  }

  function abrirData(inp, tipo, min, max) {
    if (inp.disabled || inp.readOnly) return;
    var p = document.createElement('div');
    p.className = 'mec-data' + (tipo === 'datetime-local' ? ' mec-data--hora' : '') + (tipo === 'time' ? ' mec-data--so-hora' : '');
    p.setAttribute('role', 'dialog');
    p.setAttribute('aria-label', tipo === 'time' ? 'Escolher horário' : 'Escolher data');
    var atual = inp.value, base = atual ? atual.slice(0, 10) : '';
    var hoje = isoHoje();
    var ref = (base || (min && hoje < min ? min : hoje)).split('-');
    var ano = +ref[0], mes = +ref[1] - 1, foco = base || hoje;
    var horaAtual = tipo === 'time' ? (atual || '') : (atual.slice(11, 16) || '');

    function escolher(dia, hora) {
      var v;
      if (tipo === 'date') v = dia;
      else if (tipo === 'time') v = hora;
      else v = (dia || inp.value.slice(0, 10) || hoje) + 'T' + (hora || horaAtual || '08:00');
      inp.value = v;
      disparar(inp, 'input'); disparar(inp, 'change');
    }
    function desenhar() {
      var h = '';
      if (tipo !== 'time') {
        h += '<div class="mec-data__cal"><div class="mec-data__topo"><button type="button" class="mec-data__nav" data-mes="-1" aria-label="Mês anterior">' + SVG.esq + '</button>'
          + '<strong>' + MESES[mes] + ' ' + ano + '</strong>'
          + '<button type="button" class="mec-data__nav" data-mes="1" aria-label="Próximo mês">' + SVG.dir + '</button></div>'
          + '<div class="mec-data__semana"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div><div class="mec-data__dias" role="grid">';
        var primeiro = new Date(ano, mes, 1), inicio = new Date(ano, mes, 1 - primeiro.getDay());
        var sel = inp.value ? inp.value.slice(0, 10) : '';
        for (var i = 0; i < 42; i++) {
          var d = new Date(inicio.getFullYear(), inicio.getMonth(), inicio.getDate() + i);
          var iso = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
          var fora = d.getMonth() !== mes, bloq = (min && iso < min) || (max && iso > max.slice(0, 10));
          h += '<button type="button" class="mec-data__dia' + (fora ? ' is-fora' : '') + (iso === hoje ? ' is-hoje' : '') + (iso === sel ? ' is-selecionado' : '')
            + '" data-dia="' + iso + '"' + (bloq ? ' disabled' : '') + ' tabindex="' + (iso === foco ? '0' : '-1') + '" aria-label="' + d.getDate() + ' de ' + MESES[d.getMonth()] + '">' + d.getDate() + '</button>';
        }
        h += '</div><div class="mec-data__rodape"><button type="button" class="mec-data__acao" data-hoje>Hoje</button>'
          + (inp.required ? '' : '<button type="button" class="mec-data__acao" data-limpar>Limpar</button>') + '</div></div>';
      }
      if (tipo !== 'date') {
        h += '<div class="mec-data__horas" role="listbox" aria-label="Horários">';
        for (var m = 0; m < 24 * 60; m += tipo === 'time' ? 15 : 30) {
          var hh = pad(Math.floor(m / 60)) + ':' + pad(m % 60);
          h += '<button type="button" class="mec-data__hora' + (hh === horaAtual ? ' is-selecionado' : '') + '" data-hora="' + hh + '">' + hh + '</button>';
        }
        h += '</div>';
      }
      p.innerHTML = h;
      rolarHoras();
    }
    function rolarHoras() {
      var hs = p.querySelector('.mec-data__hora.is-selecionado') || p.querySelector('[data-hora="08:00"]');
      var lista = p.querySelector('.mec-data__horas');
      if (hs && lista) lista.scrollTop = hs.offsetTop - lista.offsetTop - lista.clientHeight / 2 + hs.offsetHeight / 2;
    }
    p.addEventListener('pointerdown', function (e) { if (e.target.closest('button')) e.preventDefault(); });
    p.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b || b.disabled) return;
      if (b.dataset.mes) { mes += +b.dataset.mes; if (mes < 0) { mes = 11; ano--; } if (mes > 11) { mes = 0; ano++; } desenhar(); posicionar(); return; }
      if (b.dataset.dia) { escolher(b.dataset.dia, null); if (tipo === 'date') { fecharPainel(); inp.focus(); } else { var r = inp.value.slice(0, 10).split('-'); ano = +r[0]; mes = +r[1] - 1; desenhar(); } return; }
      if (b.dataset.hora) { horaAtual = b.dataset.hora; escolher(null, b.dataset.hora); fecharPainel(); inp.focus(); return; }
      if (b.hasAttribute('data-hoje')) { escolher(hoje, null); if (tipo === 'date') { fecharPainel(); inp.focus(); } else { var t = hoje.split('-'); ano = +t[0]; mes = +t[1] - 1; desenhar(); } return; }
      if (b.hasAttribute('data-limpar')) { inp.value = ''; disparar(inp, 'input'); disparar(inp, 'change'); fecharPainel(); inp.focus(); }
    });
    p.addEventListener('keydown', function (e) {
      var b = e.target.closest('.mec-data__dia');
      if (e.key === 'Escape') { e.preventDefault(); fecharPainel(); inp.focus(); return; }
      if (!b) return;
      var passo = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
      if (passo || e.key === 'PageUp' || e.key === 'PageDown') {
        e.preventDefault();
        var d = new Date(b.dataset.dia + 'T00:00:00');
        if (passo) d.setDate(d.getDate() + passo); else d.setMonth(d.getMonth() + (e.key === 'PageUp' ? -1 : 1));
        foco = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        if (d.getMonth() !== mes || d.getFullYear() !== ano) { ano = d.getFullYear(); mes = d.getMonth(); desenhar(); }
        var alvo = p.querySelector('[data-dia="' + foco + '"]'); if (alvo) alvo.focus();
      }
    });
    p.atualizar = function () {
      var v = inp.value;
      if (v && tipo !== 'time') { var r = v.slice(0, 10).split('-'); ano = +r[0]; mes = +r[1] - 1; foco = v.slice(0, 10); }
      if (tipo !== 'date') horaAtual = tipo === 'time' ? v : v.slice(11, 16);
      desenhar(); posicionar();
    };
    p.focar = function () { var a = p.querySelector('.mec-data__dia[tabindex="0"]') || p.querySelector('.mec-data__hora.is-selecionado') || p.querySelector('button'); if (a) a.focus(); };
    desenhar();
    inp.classList.add('is-aberto');
    abrirPainel(inp.closest('.campo-data-caixa') || inp, p, function () { inp.classList.remove('is-aberto'); }, false);
    rolarHoras();
  }

  /* ── <datalist> (sugestões) ───────────────────────────────────────────── */
  function prepararSugestoes(inp) {
    if (inp._mec || inp.hasAttribute('data-nativo')) return;
    var dl = document.getElementById(inp.getAttribute('list'));
    if (!dl) return;
    inp._mec = true;
    inp.removeAttribute('list');
    inp.setAttribute('autocomplete', 'off');
    var valores = Array.prototype.map.call(dl.options, function (o) { return o.value; }).filter(Boolean);
    function mostrar() {
      var f = semAcento(inp.value);
      var lista = valores.filter(function (v) { return !f || semAcento(v).indexOf(f) >= 0; }).slice(0, 50);
      if (!lista.length || (lista.length === 1 && lista[0] === inp.value)) { if (aberto && aberto.ancora === inp) fecharPainel(); return; }
      var painel = painelLista(lista.map(function (v) { return { valor: v, texto: v, selecionado: v === inp.value }; }), {
        escolher: function (it) { fecharPainel(); protoValor.set.call(inp, it.valor); disparar(inp, 'input'); disparar(inp, 'change'); inp.focus(); }
      });
      abrirPainel(inp, painel);
    }
    inp.addEventListener('focus', mostrar);
    inp.addEventListener('input', mostrar);
    inp.addEventListener('keydown', function (e) {
      if (aberto && aberto.ancora === inp) aberto.painel.teclado(e);
      else if (e.key === 'ArrowDown') { e.preventDefault(); mostrar(); }
    });
    inp.addEventListener('blur', function () { setTimeout(function () { if (aberto && aberto.ancora === inp) fecharPainel(); }, 0); });
  }

  /* ── Validação: mensagem embaixo do campo ─────────────────────────────── */
  var primeiroInvalido = null;
  function lugarDoAviso(el) {
    return el.closest('.form-field') || el.closest('.auth-campo') || el.closest('label') || el.parentNode;
  }
  function limparAviso(el) {
    el.removeAttribute('aria-invalid');
    var c = lugarDoAviso(el), a = c && c.querySelector(':scope > .campo-aviso');
    if (a) a.remove();
    if (c) c.classList.remove('is-invalido');
  }
  document.addEventListener('invalid', function (e) {
    var el = e.target;
    e.preventDefault();
    el.setAttribute('aria-invalid', 'true');
    var c = lugarDoAviso(el);
    if (c) {
      c.classList.add('is-invalido');
      var a = c.querySelector(':scope > .campo-aviso');
      if (!a) { a = document.createElement('span'); a.className = 'campo-aviso'; a.setAttribute('role', 'alert'); c.appendChild(a); }
      var msg = el.validationMessage;
      if (el.validity.valueMissing) msg = el.tagName === 'SELECT' || el.type === 'radio' || el.type === 'checkbox' ? 'Escolha uma opção.' : 'Preencha este campo.';
      else if (el.validity.typeMismatch && el.type === 'email') msg = 'Informe um e-mail válido.';
      else if (el.validity.tooShort) msg = 'Use pelo menos ' + el.minLength + ' caracteres.';
      a.textContent = msg;
    }
    if (!primeiroInvalido) {
      primeiroInvalido = el;
      setTimeout(function () {
        var alvo = primeiroInvalido; primeiroInvalido = null;
        var dlg = alvo.closest('dialog');
        if (dlg && !dlg.open) return;
        alvo.scrollIntoView({ block: 'center', behavior: 'smooth' });
        alvo.focus({ preventScroll: true });
      }, 0);
    }
  }, true);
  ['input', 'change'].forEach(function (t) {
    document.addEventListener(t, function (e) {
      var el = e.target;
      if (el && el.getAttribute && el.getAttribute('aria-invalid') === 'true' && el.validity && el.validity.valid) limparAviso(el);
    });
  });

  /* ── Confirmação no padrão do sistema ─────────────────────────────────── */
  M.confirmar = function (mensagem, opcoes) {
    opcoes = opcoes || {};
    document.querySelectorAll('details.menu-mais[open]').forEach(function (m) { m.removeAttribute('open'); });
    return new Promise(function (resolver) {
      var d = document.createElement('dialog');
      d.className = 'dlg dlg--confirmar';
      var perigo = opcoes.perigo !== undefined ? opcoes.perigo : /^(excluir|desativar|remover|cancelar|estornar)/i.test(String(mensagem).trim());
      d.innerHTML = '<form method="dialog"><h3></h3><p class="dlg--confirmar__texto"></p><div class="form-acoes">'
        + '<button value="nao" class="btn btn--ghost">' + (opcoes.nao || 'Voltar') + '</button>'
        + '<button value="sim" class="btn ' + (perigo ? 'btn--danger' : 'btn--primary') + '">' + (opcoes.sim || 'Confirmar') + '</button></div></form>';
      d.querySelector('h3').textContent = opcoes.titulo || (perigo ? 'Tem certeza?' : 'Confirmar');
      d.querySelector('.dlg--confirmar__texto').textContent = mensagem;
      document.body.appendChild(d);
      d.addEventListener('close', function () { resolver(d.returnValue === 'sim'); d.remove(); });
      d.addEventListener('click', function (e) { if (e.target === d) d.close('nao'); });
      d.showModal();
      d.querySelector('button[value=sim]').focus();
    });
  };
  // <form data-confirmar="texto">: pergunta antes de enviar.
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches || !f.matches('form[data-confirmar]')) return;
    if (f._confirmado) { f._confirmado = false; return; }
    e.preventDefault();
    e.stopImmediatePropagation();
    var quem = e.submitter;
    M.confirmar(f.dataset.confirmar).then(function (ok) {
      if (!ok) return;
      f._confirmado = true;
      if (f.requestSubmit) f.requestSubmit(quem && quem.form === f ? quem : undefined); else f.submit();
    });
  }, true);

  /* ── Início: campos de agora e os que aparecerem depois ───────────────── */
  function preparar(raiz) {
    if (!raiz.querySelectorAll) return;
    if (raiz.matches && raiz.matches('select')) prepararSelect(raiz);
    raiz.querySelectorAll('select').forEach(prepararSelect);
    raiz.querySelectorAll('input[type=date], input[type=datetime-local], input[type=time]').forEach(prepararData);
    raiz.querySelectorAll('input[list]').forEach(prepararSugestoes);
  }
  preparar(document);
  new MutationObserver(function (lista) {
    lista.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1 && !n.classList.contains('mec-pop')) preparar(n); }); });
  }).observe(document.body, { childList: true, subtree: true });
})();
