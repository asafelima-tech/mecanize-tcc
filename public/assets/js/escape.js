// Equivalente em JavaScript ao htmlspecialchars() do PHP. Necessário porque os
// painéis de estatística montam HTML com innerHTML a partir de dados que vieram
// do banco — um cliente cadastrado como <img src=x onerror=...> executa script
// na sessão de quem abrir o painel. O escape do PHP não alcança esse caminho:
// ali o dado chega como JSON e é o JavaScript que constrói a marcação.
(function () {
    var mapa = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };

    /**
     * Devolve o valor pronto para ser colado em innerHTML.
     * null/undefined viram string vazia — é o que as telas esperam ao montar
     * um campo que o cadastro deixou em branco.
     */
    window.escaparHtml = function (valor) {
        if (valor === null || valor === undefined) {
            return '';
        }

        return String(valor).replace(/[&<>"']/g, function (caractere) {
            return mapa[caractere];
        });
    };
})();
