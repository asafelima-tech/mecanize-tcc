// Componente de assinatura em canvas — desenho com mouse/touch, botão de
// limpar, e exportação pra data URL (PNG base64) antes do submit do form.
// Sem biblioteca externa. Usado em: aprovar orçamento, concluir OS.
function initAssinatura(canvasId) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    var ctx = canvas.getContext('2d');
    var desenhando = false;
    var temTraco = false;

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
        ctx.lineWidth = 2.5;
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
        }
    };
}
