// Drag-and-drop do Kanban de Ordens de Serviço (./kanbanOrdemServico).
//
// Pointer Events (pointerdown/pointermove/pointerup), não HTML5 Drag-and-
// -Drop nativo — a API nativa (dragstart/dragover/drop) simplesmente não
// dispara em touch (celular/tablet), então quem usasse touch não conseguia
// mover a OS pelo Kanban. Pointer Events cobrem mouse, caneta e touch com o
// mesmo código.
(function () {
    var board = document.querySelector('.kanban-board');
    if (!board) return;

    // Deslocamento mínimo antes de considerar "arrastando" — abaixo disso é
    // só um toque/clique (ex.: no link "Detalhes →" dentro do card).
    var LIMIAR_ARRASTE = 8;

    var estado = null;

    board.querySelectorAll('.kanban-card').forEach(function (card) {
        card.addEventListener('pointerdown', function (e) {
            // Só botão principal do mouse; touch/caneta não têm essa checagem.
            if (e.pointerType === 'mouse' && e.button !== 0) return;

            estado = {
                card: card,
                origem: card.closest('.kanban-column__body'),
                pointerId: e.pointerId,
                inicioX: e.clientX,
                inicioY: e.clientY,
                arrastando: false
            };
        });
    });

    document.addEventListener('pointermove', function (e) {
        if (!estado || e.pointerId !== estado.pointerId) return;

        var dx = e.clientX - estado.inicioX;
        var dy = e.clientY - estado.inicioY;

        if (!estado.arrastando) {
            if (Math.hypot(dx, dy) < LIMIAR_ARRASTE) return;

            estado.arrastando = true;
            estado.card.classList.add('is-dragging');
            // pointer-events:none pra elementFromPoint() enxergar a coluna
            // por baixo do card, não o próprio card (que segue o dedo/cursor).
            estado.card.style.pointerEvents = 'none';
            try {
                estado.card.setPointerCapture(estado.pointerId);
            } catch (err) { /* navegador sem suporte — segue sem capturar */ }
        }

        e.preventDefault();
        estado.card.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(1.03)';

        destacarColunaSobPonteiro(e.clientX, e.clientY);
    }, { passive: false });

    document.addEventListener('pointerup', function (e) {
        if (!estado || e.pointerId !== estado.pointerId) return;
        finalizarArraste(e.clientX, e.clientY);
    });

    document.addEventListener('pointercancel', function (e) {
        if (!estado || e.pointerId !== estado.pointerId) return;
        finalizarArraste(null, null);
    });

    function destacarColunaSobPonteiro(x, y) {
        var alvo = document.elementFromPoint(x, y);
        var coluna = alvo ? alvo.closest('.kanban-column__body') : null;

        board.querySelectorAll('.kanban-column__body.is-dragover').forEach(function (c) {
            if (c !== coluna) c.classList.remove('is-dragover');
        });

        if (coluna) coluna.classList.add('is-dragover');
    }

    function finalizarArraste(x, y) {
        var card = estado.card;
        var origem = estado.origem;
        var arrastando = estado.arrastando;

        card.classList.remove('is-dragging');
        card.style.transform = '';
        card.style.pointerEvents = '';
        try {
            card.releasePointerCapture(estado.pointerId);
        } catch (err) { /* pode já ter sido liberado pelo navegador */ }

        board.querySelectorAll('.kanban-column__body.is-dragover').forEach(function (c) {
            c.classList.remove('is-dragover');
        });

        var coluna = null;
        if (arrastando && x !== null) {
            var alvo = document.elementFromPoint(x, y);
            coluna = alvo ? alvo.closest('.kanban-column__body') : null;
        }

        estado = null;

        if (!arrastando || !coluna || coluna === origem) return;

        moverCard(card, origem, coluna);
    }

    function moverCard(card, colunaOrigem, colunaDestino) {
        var novoStatus = colunaDestino.dataset.status;
        var statusAntigo = colunaOrigem.dataset.status;
        var id = card.dataset.id;

        // Move visualmente na hora (otimista); desfaz se a chamada falhar.
        colunaOrigem.querySelector('.kanban-column__empty')?.remove();
        colunaDestino.appendChild(card);
        atualizarContadores();
        garantirVazio(colunaOrigem);

        fetch('./moverStatusOrdemServico', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_ordem_servico=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(novoStatus)
        })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp.sucesso) {
                    desfazerMovimento(card, colunaOrigem, colunaDestino, statusAntigo, resp.erro);
                }
            })
            .catch(function () {
                desfazerMovimento(card, colunaOrigem, colunaDestino, statusAntigo);
            });
    }

    function desfazerMovimento(card, colunaOrigem, colunaDestino, statusAntigo, motivo) {
        colunaOrigem.appendChild(card);
        garantirVazio(colunaDestino);
        atualizarContadores();
        // O servidor explica o porquê (etapa inválida, só o administrador, falta motivo…).
        alert(motivo || 'Não foi possível mover a OS. Tente novamente.');
    }

    function garantirVazio(coluna) {
        if (!coluna.querySelector('.kanban-card') && !coluna.querySelector('.kanban-column__empty')) {
            var vazio = document.createElement('div');
            vazio.className = 'kanban-column__empty';
            vazio.textContent = 'Nenhuma OS aqui.';
            coluna.appendChild(vazio);
        }
    }

    function atualizarContadores() {
        document.querySelectorAll('.kanban-column').forEach(function (coluna) {
            var body = coluna.querySelector('.kanban-column__body');
            var contador = coluna.querySelector('.kanban-column__count');
            contador.textContent = body.querySelectorAll('.kanban-card').length;
        });
    }
})();
