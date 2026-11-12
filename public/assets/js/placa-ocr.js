/*
 * Leitura de placa pela foto — componente das telas de veículo e de chegada.
 *
 * Regra de ouro: a leitura é uma SUGESTÃO. O campo da placa só é preenchido depois que a pessoa
 * confirma ("Usar esta placa") — uma placa duvidosa nunca é gravada sozinha. A tela mostra o que foi lido,
 * a confiança do serviço, o formato (Mercosul / antiga) e as alternativas; leitura incerta ou com formato
 * impossível aparece em alerta e pede conferência caractere a caractere.
 *
 * Celular: a foto é REDUZIDA no próprio aparelho antes de subir (lado maior 1280 px, JPEG) — uma foto de
 * câmera tem vários MB e estourava o limite do servidor; reduzida vira algumas centenas de KB e sobe rápido
 * até no 4G. Tem tempo limite, botão de cancelar, nova tentativa e a saída sempre disponível: digitar.
 *
 * Requer foto-upload.js (carregue antes). Uso:
 *   PlacaOcr.iniciar({
 *     inputFoto: '#fotoPlaca', painel: '#painelLeituraPlaca', rota: './lerPlaca',
 *     aoConfirmar: function (placaNormalizada, origem) { ... },   // origem: 'FOTO' | 'CORRIGIDA'
 *     aoDigitar: function () { ... }                              // "Digitar a placa"
 *   });
 */
(function (global) {
    'use strict';

    var TIMEOUT_MS = 25000;

    function normalizar(t) { return String(t == null ? '' : t).toUpperCase().replace(/[^A-Z0-9]/g, ''); }

    function formato(p) {
        if (/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/.test(p)) return 'MERCOSUL';
        if (/^[A-Z]{3}[0-9]{4}$/.test(p)) return 'ANTIGO';
        return null;
    }

    /** Como a placa aparece no campo: Mercosul sem hífen, modelo antigo com hífen. */
    function formatar(p) {
        return formato(p) === 'ANTIGO' ? p.slice(0, 3) + '-' + p.slice(3) : p;
    }

    function el(tag, atributos, filhos) {
        var e = document.createElement(tag);
        Object.keys(atributos || {}).forEach(function (k) {
            if (k === 'texto') e.textContent = atributos[k];
            else if (k === 'estilo') e.setAttribute('style', atributos[k]);
            else e.setAttribute(k, atributos[k]);
        });
        (filhos || []).forEach(function (f) { if (f) e.appendChild(f); });
        return e;
    }

    /** A redução da foto no aparelho mora em foto-upload.js (compartilhada com as evidências da OS). */
    function reduzir(arquivo) {
        return global.FotoUpload.reduzir(arquivo);
    }

    /** Envia e devolve SEMPRE um objeto de resultado (nunca lança): falha de rede e tempo esgotado viram códigos. */
    function enviar(blob, rota, nomeArquivo) {
        var controle = typeof AbortController === 'function' ? new AbortController() : null;
        var temporizador = setTimeout(function () { if (controle) controle.abort(); }, TIMEOUT_MS);
        var dados = new FormData();
        dados.append('imagem', blob, nomeArquivo || 'placa.jpg');

        var promessa = fetch(rota, { method: 'POST', body: dados, signal: controle ? controle.signal : undefined, credentials: 'same-origin' })
            .then(function (resp) {
                return resp.text().then(function (texto) {
                    var j;
                    try { j = JSON.parse(texto); } catch (e) { return { ok: false, codigo: 'INDISPONIVEL', erro: 'O servidor devolveu uma resposta inesperada. Digite a placa.' }; }
                    j.ok = j.ok === undefined ? !!j.sucesso : j.ok;
                    return j;
                });
            })
            .catch(function (e) {
                if (e && e.name === 'AbortError') return { ok: false, codigo: 'TEMPO_ESGOTADO', erro: 'A leitura demorou demais. Tente de novo ou digite a placa.', cancelado: !!(promessa && promessa._cancelado) };
                return { ok: false, codigo: 'REDE', erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo, ou digite a placa.' };
            })
            .then(function (r) { clearTimeout(temporizador); return r; });

        promessa.cancelar = function () { promessa._cancelado = true; clearTimeout(temporizador); if (controle) controle.abort(); };

        return promessa;
    }

    function iniciar(cfg) {
        var input = typeof cfg.inputFoto === 'string' ? document.querySelector(cfg.inputFoto) : cfg.inputFoto;
        var painel = typeof cfg.painel === 'string' ? document.querySelector(cfg.painel) : cfg.painel;
        if (!input || !painel) return null;

        painel.setAttribute('aria-live', 'polite');
        var emAndamento = null;
        var sequencia = 0; // só a última foto escolhida vale (escolher outra no meio cancela a anterior)

        function limpar() { while (painel.firstChild) painel.removeChild(painel.firstChild); }

        function mensagem(texto, tom) {
            limpar();
            painel.appendChild(el('div', { texto: texto, estilo: 'font-size:12px;line-height:1.4;' + (tom === 'erro' ? 'color:var(--color-danger);font-weight:600;' : 'color:var(--color-text-muted);') }));
        }

        function botao(texto, classe, aoClicar) {
            var b = el('button', { type: 'button', class: 'btn ' + classe, texto: texto, estilo: 'min-height:40px;' });
            b.addEventListener('click', aoClicar);
            return b;
        }

        function confirmar(placa, origem) {
            limpar();
            painel.appendChild(el('div', { texto: 'Placa ' + (origem === 'CORRIGIDA' ? 'corrigida e ' : '') + 'confirmada por você: ' + formatar(placa), estilo: 'font-size:12px;font-weight:600;color:var(--color-success);' }));
            if (cfg.aoConfirmar) cfg.aoConfirmar(placa, origem);
        }

        function mostrarResultado(r) {
            limpar();
            var placa = normalizar(r.placa);
            var incerta = !!r.baixa_confianca;
            var caixa = el('div', { estilo: 'border:1.5px solid ' + (incerta ? 'var(--color-warning)' : 'var(--color-primary)') + ';background:' + (incerta ? 'var(--color-warning-soft)' : 'var(--color-primary-soft)') + ';border-radius:var(--radius-md);padding:10px 12px;' });

            caixa.appendChild(el('div', { texto: 'Placa lida na foto — confira antes de usar', estilo: 'font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.04em;' }));
            caixa.appendChild(el('div', { texto: formatar(placa), estilo: 'font-family:monospace;font-size:26px;font-weight:800;letter-spacing:2px;margin:4px 0;color:var(--color-text);' }));

            var detalhe = [];
            var f = formato(placa);
            detalhe.push(f === 'MERCOSUL' ? 'Placa Mercosul' : (f === 'ANTIGO' ? 'Placa modelo antigo' : 'Formato fora do padrão brasileiro'));
            detalhe.push(r.confianca != null ? 'confiança ' + Math.round(r.confianca * 100) + '%' : 'confiança não informada');
            caixa.appendChild(el('div', { texto: detalhe.join(' · '), estilo: 'font-size:12px;color:var(--color-text-secondary);' }));

            if (incerta || r.erro) {
                caixa.appendChild(el('div', { texto: r.erro || 'Leitura incerta: compare letra por letra com a foto. Em caso de dúvida, corrija ou digite.', estilo: 'font-size:12px;font-weight:600;margin-top:6px;color:var(--color-text);' }));
            }

            var linha = el('div', { estilo: 'display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;' });
            if (f) linha.appendChild(botao('Usar esta placa', 'btn--primary', function () { confirmar(placa, 'FOTO'); }));
            linha.appendChild(botao('Corrigir', 'btn--ghost', function () {
                limpar();
                var campo = el('input', { type: 'text', maxlength: '8', value: formatar(placa), 'aria-label': 'Placa corrigida', estilo: 'text-transform:uppercase;font-family:monospace;font-size:18px;letter-spacing:1px;' });
                // dentro de .form-field o campo ganha o estilo padrão do sistema (borda, foco, tema escuro)
                var envoltorio = el('div', { class: 'form-field', estilo: 'flex:0 1 150px;min-width:120px;' }, [campo]);
                var aviso = el('div', { estilo: 'font-size:12px;color:var(--color-danger);min-height:16px;margin-top:4px;' });
                var ok = botao('Confirmar', 'btn--primary', function () {
                    var p = normalizar(campo.value);
                    if (!formato(p)) { aviso.textContent = 'Placa inválida. Use ABC1D23 (Mercosul) ou ABC-1234 (antiga).'; return; }
                    confirmar(p, 'CORRIGIDA');
                });
                painel.appendChild(el('div', { estilo: 'display:flex;gap:8px;align-items:center;flex-wrap:wrap;' }, [envoltorio, ok]));
                painel.appendChild(aviso);
                campo.focus();
            }));
            linha.appendChild(botao('Descartar', 'btn--ghost', function () { mensagem('Leitura descartada. Tire outra foto ou digite a placa.'); }));
            caixa.appendChild(linha);

            (r.candidatos || []).forEach(function (c, i) {
                if (i === 0) caixa.appendChild(el('div', { texto: 'Outras leituras possíveis:', estilo: 'font-size:11px;color:var(--color-text-muted);margin-top:8px;' }));
                var b = botao(formatar(normalizar(c.placa)) + (c.confianca != null ? ' (' + Math.round(c.confianca * 100) + '%)' : ''), 'btn--ghost btn--sm', function () { confirmar(normalizar(c.placa), 'FOTO'); });
                b.style.marginRight = '6px'; b.style.marginTop = '4px';
                caixa.appendChild(b);
            });

            painel.appendChild(caixa);
        }

        function falha(r, repetir) {
            limpar();
            painel.appendChild(el('div', { texto: r.erro || 'Não foi possível ler a placa.', estilo: 'font-size:12.5px;font-weight:600;color:var(--color-danger);line-height:1.4;' }));
            var linha = el('div', { estilo: 'display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;' });
            if (repetir && r.codigo !== 'NAO_CONFIGURADO' && r.codigo !== 'LIMITE' && r.codigo !== 'LIMITE_SESSAO') linha.appendChild(botao('Tentar de novo', 'btn--ghost btn--sm', repetir));
            linha.appendChild(botao('Digitar a placa', 'btn--primary btn--sm', function () { limpar(); if (cfg.aoDigitar) cfg.aoDigitar(); }));
            painel.appendChild(linha);
        }

        function processar(arquivo) {
            if (emAndamento) emAndamento.cancelar();
            var minhaVez = ++sequencia;
            mensagem('Preparando a foto…');

            reduzir(arquivo).then(function (blob) {
                if (minhaVez !== sequencia) return;
                var rotulo = el('div', { texto: 'Lendo a placa… (pode levar alguns segundos)', estilo: 'font-size:12px;color:var(--color-text-muted);' });
                limpar(); painel.appendChild(rotulo);
                painel.appendChild(botao('Cancelar', 'btn--ghost btn--sm', function () { if (emAndamento) emAndamento.cancelar(); }));

                var minha = emAndamento = enviar(blob, cfg.rota || './lerPlaca');
                minha.then(function (r) {
                    if (emAndamento !== minha) return;
                    emAndamento = null;
                    if (r.cancelado) { mensagem('Leitura cancelada. Tire outra foto ou digite a placa.'); return; }
                    if (r.ok && r.placa) mostrarResultado(r); else falha(r, function () { processar(arquivo); });
                });
            }).catch(function (e) { if (minhaVez === sequencia) falha({ erro: e.message, codigo: 'IMAGEM_INVALIDA' }, null); });
        }

        // Por padrão a leitura começa assim que a foto é escolhida; a tela pode assumir esse gatilho (automatico:false).
        if (cfg.automatico !== false) {
            input.addEventListener('change', function () {
                if (input.files && input.files[0]) processar(input.files[0]);
            });
        }

        return { processar: processar, limpar: limpar };
    }

    global.PlacaOcr = { iniciar: iniciar, normalizar: normalizar, formato: formato, formatar: formatar, reduzir: reduzir };
})(window);
