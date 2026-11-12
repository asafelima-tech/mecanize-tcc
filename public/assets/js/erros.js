/**
 * MECANIZE — comportamento das telas de erro + rede de segurança do cliente.
 *
 * Duas partes, ambas seguras de rodar em qualquer página:
 *
 *   1. Ações das telas de erro (voltar, tentar de novo, copiar ocorrência,
 *      nova tentativa automática).
 *   2. Interceptação global: queda de conexão e falha de JavaScript não
 *      tratada. Sem isso, o usuário via a tela simplesmente parar de
 *      responder, ou um alerta cru do navegador.
 *
 * Sem dependências: é embutido inline nas páginas autônomas, que aparecem
 * justamente quando não dá pra confiar que um arquivo externo será servido.
 */
(function () {
    'use strict';

    // ==========================================================
    // 1. AÇÕES DAS TELAS DE ERRO
    // ==========================================================

    /**
     * "Voltar": usa o histórico quando existe, senão manda pro início.
     * Voltar pelo histórico é o que faz o navegador devolver o formulário
     * com os campos preenchidos — é o que evita redigitar tudo depois de
     * uma sessão expirada.
     */
    document.addEventListener('click', function (e) {
        var botao = e.target.closest('[data-erro-voltar]');
        if (!botao) return;

        e.preventDefault();

        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = './home';
        }
    });

    /** "Tentar novamente": estado de carregando e recarrega via GET. */
    document.addEventListener('click', function (e) {
        var botao = e.target.closest('[data-erro-tentar]');
        if (!botao) return;

        e.preventDefault();
        tentarDeNovo(botao);
    });

    function tentarDeNovo(botao) {
        if (botao) {
            botao.setAttribute('data-carregando', '1');
            var icone = botao.querySelector('[data-erro-icone]');
            if (icone) icone.style.display = 'none';
        }

        // href = href refaz a navegação como GET. `reload()` repetiria o POST
        // original e faria o navegador perguntar sobre reenviar o formulário.
        window.location.href = window.location.href;
    }

    /** Copia o código da ocorrência, com confirmação visual no próprio botão. */
    document.addEventListener('click', function (e) {
        var botao = e.target.closest('[data-erro-copiar]');
        if (!botao) return;

        e.preventDefault();
        var codigo = botao.getAttribute('data-erro-copiar');

        var confirmar = function () {
            var original = botao.innerHTML;
            botao.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"'
                + ' stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
            botao.style.color = 'var(--erro-acento)';
            setTimeout(function () {
                botao.innerHTML = original;
                botao.style.color = '';
            }, 1600);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(codigo).then(confirmar, function () { selecionarCodigo(); });
        } else {
            selecionarCodigo();
        }
    });

    /** Sem API de área de transferência, ao menos deixa o código selecionado. */
    function selecionarCodigo() {
        var alvo = document.getElementById('erro-ocorrencia');
        if (!alvo || !window.getSelection) return;

        var faixa = document.createRange();
        faixa.selectNodeContents(alvo);
        var selecao = window.getSelection();
        selecao.removeAllRanges();
        selecao.addRange(faixa);
    }

    /**
     * Nova tentativa automática. Congela enquanto a aba está em segundo plano
     * (não adianta recarregar uma tela que ninguém está vendo) e, quando o
     * erro é de conexão, dispara na hora em que a rede volta.
     */
    var painelRecontagem = document.querySelector('[data-erro-recontagem]');

    if (painelRecontagem) {
        var restante = parseInt(painelRecontagem.getAttribute('data-erro-recontagem'), 10) || 15;
        var texto    = painelRecontagem.querySelector('[data-erro-recontagem-texto]');

        var tique = setInterval(function () {
            if (document.hidden) return;

            restante--;

            if (restante <= 0) {
                clearInterval(tique);
                if (texto) texto.textContent = 'Tentando novamente...';
                tentarDeNovo(document.querySelector('[data-erro-tentar]'));
                return;
            }

            if (texto) texto.textContent = 'Nova tentativa automática em ' + restante + 's';
        }, 1000);

        window.addEventListener('online', function () {
            clearInterval(tique);
            if (texto) texto.textContent = 'Conexão restabelecida. Recarregando...';
            setTimeout(function () { tentarDeNovo(null); }, 600);
        });
    }

    // ==========================================================
    // 2. REDE DE SEGURANÇA GLOBAL (vale em qualquer tela)
    // ==========================================================

    /**
     * Aviso fixo, discreto e não bloqueante. Não usa alert(): a página
     * continua na tela com tudo que já foi digitado, que é o ponto — a
     * conexão volta e o usuário só clica em enviar de novo.
     */
    function aviso(mensagem, tom, permanente) {
        var id = 'mecanize-aviso-rede';
        var barra = document.getElementById(id);

        if (!barra) {
            barra = document.createElement('div');
            barra.id = id;
            barra.setAttribute('role', 'status');
            barra.style.cssText = [
                'position:fixed', 'left:50%', 'bottom:22px', 'transform:translateX(-50%) translateY(12px)',
                'z-index:99999', 'display:flex', 'align-items:center', 'gap:10px',
                'padding:11px 18px', 'border-radius:999px', 'font:600 13px/1 "Inter",system-ui,sans-serif',
                'box-shadow:0 8px 20px rgba(17,24,38,.14), 0 24px 52px rgba(17,24,38,.12)',
                'opacity:0', 'transition:opacity .22s ease, transform .22s ease',
                'max-width:calc(100vw - 32px)', 'pointer-events:auto'
            ].join(';');
            document.body.appendChild(barra);
        }

        var cores = {
            erro:    { fundo: '#FEE2E2', texto: '#991B1B', ponto: '#EF4444' },
            aviso:   { fundo: '#FEF3C7', texto: '#92400E', ponto: '#F59E0B' },
            sucesso: { fundo: '#DCFCE7', texto: '#166534', ponto: '#16A34A' }
        }[tom] || { fundo: '#FEF3C7', texto: '#92400E', ponto: '#F59E0B' };

        barra.style.background = cores.fundo;
        barra.style.color = cores.texto;
        barra.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:'
            + cores.ponto + ';flex-shrink:0"></span><span>' + mensagem + '</span>';

        requestAnimationFrame(function () {
            barra.style.opacity = '1';
            barra.style.transform = 'translateX(-50%) translateY(0)';
        });

        clearTimeout(barra._sumir);

        if (!permanente) {
            barra._sumir = setTimeout(function () {
                barra.style.opacity = '0';
                barra.style.transform = 'translateX(-50%) translateY(12px)';
            }, 4200);
        }
    }

    // Conexão: avisa ao cair e confirma ao voltar.
    window.addEventListener('offline', function () {
        aviso('Você está sem conexão. Nada foi perdido — reconecte e tente de novo.', 'erro', true);
    });

    window.addEventListener('online', function () {
        aviso('Conexão restabelecida.', 'sucesso', false);
    });

    /**
     * Falha de JavaScript não tratada. Antes, um erro aqui simplesmente
     * travava a tela em silêncio: o botão não respondia e o usuário ficava
     * clicando sem entender. Agora dá um retorno e um caminho de saída.
     */
    var jaAvisou = false;

    function falhaInesperada() {
        if (jaAvisou) return;   // um erro em laço não vira uma parede de avisos
        jaAvisou = true;
        aviso('Algo não funcionou como esperado nesta tela. Atualize a página se o problema continuar.', 'aviso', false);
    }

    window.addEventListener('error', function (e) {
        // Falha ao carregar imagem/script não é erro de execução: ignora.
        if (e && e.target && e.target !== window && e.target.tagName) return;
        falhaInesperada();
    }, true);

    window.addEventListener('unhandledrejection', falhaInesperada);

    // ==========================================================
    // 3. AJUDANTE DE REQUISIÇÃO COM TEMPO LIMITE
    // ==========================================================

    /**
     * fetch com prazo e mensagem tratada. Sem prazo, uma requisição pendurada
     * deixa a tela "carregando" pra sempre, sem nada indicando o que houve.
     *
     *   window.Mecanize.buscar('./listUsuarios?fragmento=1')
     *     .then(...)
     *     .catch(function (e) { e.motivo === 'tempo' | 'rede' | 'http' });
     */
    window.Mecanize = window.Mecanize || {};

    window.Mecanize.buscar = function (url, opcoes) {
        opcoes = opcoes || {};
        var limite = opcoes.limiteMs || 15000;
        var controle = new AbortController();
        var estourou = false;

        var relogio = setTimeout(function () {
            estourou = true;
            controle.abort();
        }, limite);

        opcoes.signal = controle.signal;
        opcoes.credentials = opcoes.credentials || 'same-origin';

        return fetch(url, opcoes).then(function (resposta) {
            clearTimeout(relogio);

            if (!resposta.ok) {
                var erroHttp = new Error('HTTP ' + resposta.status);
                erroHttp.motivo = 'http';
                erroHttp.status = resposta.status;
                throw erroHttp;
            }

            return resposta;
        }).catch(function (e) {
            clearTimeout(relogio);

            if (estourou) {
                var t = new Error('Tempo esgotado');
                t.motivo = 'tempo';
                aviso('A operação demorou demais e foi interrompida. Tente novamente.', 'aviso', false);
                throw t;
            }

            if (e.motivo === 'http') throw e;

            var r = new Error('Falha de rede');
            r.motivo = 'rede';
            aviso(navigator.onLine
                ? 'Não foi possível falar com o servidor. Tente novamente em instantes.'
                : 'Você está sem conexão. Nada foi perdido — reconecte e tente de novo.', 'erro', !navigator.onLine);
            throw r;
        });
    };

    window.Mecanize.aviso = aviso;
})();
