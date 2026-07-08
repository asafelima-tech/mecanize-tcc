// Drag-and-drop do Kanban de Ordens de Serviço (./kanbanOrdemServico).
// HTML5 drag-and-drop nativo — sem biblioteca externa.
(function () {
    var board = document.querySelector('.kanban-board');
    if (!board) return;

    var cardArrastado = null;

    board.querySelectorAll('.kanban-card').forEach(function (card) {
        card.addEventListener('dragstart', function () {
            cardArrastado = card;
            card.classList.add('is-dragging');
        });

        card.addEventListener('dragend', function () {
            card.classList.remove('is-dragging');
            cardArrastado = null;
        });
    });

    board.querySelectorAll('.kanban-column__body').forEach(function (coluna) {
        coluna.addEventListener('dragover', function (e) {
            e.preventDefault();
            coluna.classList.add('is-dragover');
        });

        coluna.addEventListener('dragleave', function () {
            coluna.classList.remove('is-dragover');
        });

        coluna.addEventListener('drop', function (e) {
            e.preventDefault();
            coluna.classList.remove('is-dragover');

            if (!cardArrastado) return;

            var colunaOrigem = cardArrastado.closest('.kanban-column__body');
            var novoStatus = coluna.dataset.status;
            var statusAntigo = colunaOrigem.dataset.status;
            var id = cardArrastado.dataset.id;

            if (colunaOrigem === coluna) return;

            // Move visualmente na hora (otimista); desfaz se a chamada falhar.
            colunaOrigem.querySelector('.kanban-column__empty')?.remove();
            coluna.appendChild(cardArrastado);
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
                        desfazerMovimento(cardArrastado, colunaOrigem, coluna, statusAntigo);
                    }
                })
                .catch(function () {
                    desfazerMovimento(cardArrastado, colunaOrigem, coluna, statusAntigo);
                });
        });
    });

    function desfazerMovimento(card, colunaOrigem, colunaDestino, statusAntigo) {
        colunaOrigem.appendChild(card);
        garantirVazio(colunaDestino);
        atualizarContadores();
        alert('Não foi possível mover a OS. Tente novamente.');
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
