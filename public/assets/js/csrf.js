// Garante que todo POST (form ou fetch) leve o token CSRF, mesmo em forms
// que não usam <?= csrf_field() ?> explicitamente e em fetch()s manuais
// (ex.: kanban.js, leitura de placa). Roda na fase de CAPTURA do 'submit',
// então executa ANTES de qualquer handler de submit da própria página —
// o campo já existe no form no momento em que o navegador serializa os dados.
(function () {
    function getToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : null;
    }

    document.addEventListener('submit', function (evento) {
        var form = evento.target;
        if (!(form instanceof HTMLFormElement)) return;

        var metodo = (form.getAttribute('method') || 'get').toLowerCase();
        if (metodo !== 'post') return;
        if (form.querySelector('input[name="csrf_token"]')) return;

        var token = getToken();
        if (!token) return;

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = token;
        form.appendChild(input);
    }, true);

    var fetchOriginal = window.fetch;
    if (typeof fetchOriginal === 'function') {
        window.fetch = function (recurso, opcoes) {
            opcoes = opcoes || {};
            var metodo = (opcoes.method || 'GET').toUpperCase();

            if (metodo === 'POST') {
                var token = getToken();
                if (token) {
                    opcoes.headers = new Headers(opcoes.headers || {});
                    if (!opcoes.headers.has('X-CSRF-Token')) {
                        opcoes.headers.set('X-CSRF-Token', token);
                    }
                }
            }

            return fetchOriginal.call(this, recurso, opcoes);
        };
    }
})();
