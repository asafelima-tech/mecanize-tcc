// Componente de assinatura em canvas — desenho com mouse/touch, botão de
// limpar, e exportação pra data URL (PNG base64) antes do submit do form.
// Sem biblioteca externa. Usado em: aprovar orçamento, concluir OS.
function initAssinatura(canvasId) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    var ctx = canvas.getContext('2d');
    var desenhando = false;
    var temTraco = false;
    // Atualizado em redimensionar(); usado pra manter a espessura do traço
    // (em "pixels de tela") igual em qualquer resolução de canvas.
    var dprAtual = 1;

    function pos(e) {
        var rect = canvas.getBoundingClientRect();
        var scaleX = canvas.width / rect.width;
        var scaleY = canvas.height / rect.height;
        var clientX = e.touches ? e.touches[0].clientX : e.clientX;
        var clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
    }

    function iniciar(e) {
        desenhando = true;
        temTraco = true;
        var p = pos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        e.preventDefault();
    }

    function desenhar(e) {
        if (!desenhando) return;
        var p = pos(e);
        ctx.lineTo(p.x, p.y);
        ctx.strokeStyle = '#111826';
        // pos() já converte a posição do dedo/mouse pra pixel FÍSICO do canvas
        // (canvas.width/rect.width) — a espessura precisa da mesma conversão,
        // senão o traço sai fino demais num canvas redimensionado (celular).
        ctx.lineWidth = 2.5 * dprAtual;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
        e.preventDefault();
    }

    function parar() {
        desenhando = false;
    }

    canvas.addEventListener('mousedown', iniciar);
    canvas.addEventListener('mousemove', desenhar);
    window.addEventListener('mouseup', parar);
    canvas.addEventListener('touchstart', iniciar, { passive: false });
    canvas.addEventListener('touchmove', desenhar, { passive: false });
    canvas.addEventListener('touchend', parar);

    return {
        limpar: function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            temTraco = false;
        },
        vazio: function () {
            return !temTraco;
        },
        dataUrl: function () {
            return temTraco ? canvas.toDataURL('image/png') : '';
        },
        // Ajusta a RESOLUÇÃO do canvas (canvas.width/height, o "papel" onde se
        // desenha) para bater com o tamanho que o CSS realmente reserva pra ele
        // na tela (width:100%). Sem isso, o canvas nascia com os atributos HTML
        // fixos (380×130) e o navegador só esticava/comprimia o desenho pra
        // caber — num modal mais estreito que 380px (qualquer celular), a
        // assinatura saía capturada distorcida. × devicePixelRatio deixa o
        // traço nítido em tela retina/celular.
        //
        // Sem ctx.scale(dpr,dpr) de propósito: pos() já converte cada
        // coordenada pra pixel físico via canvas.width/rect.width — aplicar
        // escala nos dois lugares dobraria o fator e desenharia fora do
        // lugar. dprAtual só ajusta a espessura do traço (ver desenhar()).
        //
        // Chamar só quando o canvas já estiver visível (getBoundingClientRect()
        // de um elemento display:none volta 0×0) — normalmente logo depois de
        // abrir o modal que o contém.
        redimensionar: function () {
            var rect = canvas.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) return;

            dprAtual = window.devicePixelRatio || 1;
            canvas.width = rect.width * dprAtual;
            canvas.height = rect.height * dprAtual;

            ctx.clearRect(0, 0, canvas.width, canvas.height);
            temTraco = false;
        }
    };
}
