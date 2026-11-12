/*
 * Reduz a foto NO APARELHO antes de enviar (celular).
 *
 * Uma foto de câmera tem vários MB e estourava o limite do servidor / demorava no 4G. Aqui o lado maior
 * vira no máximo 1280 px, em JPEG, respeitando a rotação gravada pela câmera (foto em pé não sai deitada).
 * Foto que já é pequena segue como está (sem recompressão desnecessária). Se não for possível reduzir e o
 * original couber no limite, ele é enviado; senão, a mensagem diz o que fazer.
 *
 * Usado pela leitura de placa (placa-ocr.js) e pelas evidências da OS.
 *
 *   FotoUpload.reduzir(arquivo [, { maxLado, qualidade, limiteBytes }]) → Promise<Blob|File>
 *   FotoUpload.prepararInput(input [, aoPronto, aoErro])  → troca o arquivo do <input type=file> pelo reduzido
 */
(function (global) {
    'use strict';

    var PADRAO = { maxLado: 1280, qualidade: 0.82, limiteBytes: 4 * 1024 * 1024, pequenoBytes: 1.5 * 1024 * 1024 };

    function reduzir(arquivo, opcoes) {
        var o = Object.assign({}, PADRAO, opcoes || {});

        return new Promise(function (resolve, reject) {
            if (!arquivo || String(arquivo.type).indexOf('image/') !== 0) {
                reject(new Error('Escolha uma foto (JPG, PNG ou WEBP).'));
                return;
            }

            function original() {
                if (arquivo.size <= o.limiteBytes) resolve(arquivo);
                else reject(new Error('A foto é grande demais e não foi possível reduzi-la neste aparelho. Tire de novo, mais perto.'));
            }

            function desenhar(fonte, largura, altura) {
                var escala = Math.min(1, o.maxLado / Math.max(largura, altura));

                if (escala === 1 && arquivo.size <= o.pequenoBytes) { resolve(arquivo); return; }

                var canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(largura * escala));
                canvas.height = Math.max(1, Math.round(altura * escala));
                canvas.getContext('2d').drawImage(fonte, 0, 0, canvas.width, canvas.height);

                canvas.toBlob(function (blob) { blob ? resolve(blob) : original(); }, 'image/jpeg', o.qualidade);
            }

            function carregarImagem() {
                var url = URL.createObjectURL(arquivo);
                var img = new Image();
                img.onload = function () { URL.revokeObjectURL(url); desenhar(img, img.naturalWidth, img.naturalHeight); };
                img.onerror = function () { URL.revokeObjectURL(url); original(); };
                img.src = url;
            }

            if (typeof createImageBitmap === 'function') {
                createImageBitmap(arquivo, { imageOrientation: 'from-image' })
                    .then(function (bmp) { desenhar(bmp, bmp.width, bmp.height); })
                    .catch(carregarImagem);
            } else {
                carregarImagem();
            }
        });
    }

    /**
     * Para formulários comuns (POST multipart): ao escolher a foto, troca o arquivo do input pela versão
     * reduzida — o envio normal do formulário já leva a foto leve. Sem suporte a DataTransfer, mantém o original.
     */
    function prepararInput(input, aoPronto, aoErro) {
        input.addEventListener('change', function () {
            var arquivo = input.files && input.files[0];
            if (!arquivo) return;

            reduzir(arquivo).then(function (resultado) {
                if (resultado !== arquivo && typeof DataTransfer === 'function') {
                    try {
                        var nome = (arquivo.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
                        var dt = new DataTransfer();
                        dt.items.add(new File([resultado], nome, { type: 'image/jpeg' }));
                        input.files = dt.files;
                    } catch (e) { /* mantém o original */ }
                }

                if (aoPronto) aoPronto(input.files[0], arquivo);
            }).catch(function (e) { if (aoErro) aoErro(e); });
        });
    }

    global.FotoUpload = { reduzir: reduzir, prepararInput: prepararInput };
})(window);
