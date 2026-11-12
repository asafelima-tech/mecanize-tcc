<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>Mecanize · Painel</title>
    <link rel="stylesheet" href="./assets/vendor/inter.css">
    <link rel="stylesheet" href="./assets/css/global.css">

    <!-- Aplica o tema ANTES de renderizar — evita flash (mesmo padrão do layout principal,
         mas com chave de localStorage própria: o tema do painel é independente do resto
         do sistema — trocar aqui não deve mudar o tema de quem está usando o sistema
         normalmente em outra aba/computador, e vice-versa) -->
    <script>
        (function () {
            var saved = localStorage.getItem('mecanize-theme-painel');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (saved === 'dark' || (!saved && prefersDark)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <script>
        // ── Favicon dinâmico (mesmo padrão da tela de login) ──
        // Segue a preferência de cor do NAVEGADOR/SO (prefers-color-scheme).
        (function atualizarFavicon() {
            const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const favicon = document.createElement('link');
            favicon.rel = 'icon';
            favicon.type = 'image/png';
            favicon.href = isDark ? './assets/img/logo-branco.ico' : './assets/img/logo-escuro.png';
            document.querySelectorAll('link[rel*="icon"]').forEach(el => el.remove());
            document.head.appendChild(favicon);
        })();

        // Atualiza em tempo real se o usuário mudar o tema do SO com a página aberta
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            const favicon = document.querySelector('link[rel*="icon"]');
            const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (favicon) {
                favicon.href = isDark ? './assets/img/logo-branco.ico' : './assets/img/logo-escuro.png';
            }
        });
    </script>

    <style>
        body {
            background: var(--color-bg);
            min-height: 100vh;
            padding: 32px 40px;
            display: flex;
            flex-direction: column;
        }

        .painel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 16px;
        }

        .painel-header__brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 26px;
            font-weight: 800;
            color: var(--color-text);
            letter-spacing: -0.02em;
        }

        .painel-header__brand .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--color-primary-gradient);
        }

        .painel-header__direita {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .painel-header__relogio {
            font-size: 22px;
            font-weight: 700;
            color: var(--color-text-muted);
            font-variant-numeric: tabular-nums;
        }

        /* Botão de tema — desenhado à parte pro painel (o toggle da topbar
           depende do contexto visual da topbar e ficava invisível aqui). */
        .painel-theme-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1.5px solid var(--color-border-strong);
            background: var(--color-surface);
            color: var(--color-text);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }

        .painel-theme-btn:hover {
            transform: translateY(-1px) scale(1.05);
            box-shadow: var(--shadow-md);
            border-color: var(--color-primary);
        }

        .painel-theme-btn svg {
            position: absolute;
            transition: opacity .2s ease, transform .3s ease;
        }

        .painel-theme-btn .icon-sun {
            opacity: 0;
            transform: rotate(-90deg) scale(.5);
            color: #F59E0B;
        }

        .painel-theme-btn .icon-moon {
            opacity: 1;
            transform: rotate(0) scale(1);
            color: #6366F1;
        }

        html[data-theme="dark"] .painel-theme-btn .icon-sun {
            opacity: 1;
            transform: rotate(0) scale(1);
        }

        html[data-theme="dark"] .painel-theme-btn .icon-moon {
            opacity: 0;
            transform: rotate(90deg) scale(.5);
        }

        .painel-colunas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            flex: 1;
        }

        .painel-coluna {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            padding: 24px;
            display: flex;
            flex-direction: column;
        }

        .painel-coluna__titulo {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-text-muted);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .painel-coluna__titulo .dot-status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .painel-coluna--andamento .dot-status {
            background: #F59E0B;
        }

        .painel-coluna--pronto .dot-status {
            background: #22C55E;
        }

        .painel-lista {
            display: flex;
            flex-direction: column;
            gap: 12px;
            overflow-y: auto;
        }

        .painel-card {
            background: var(--color-bg-alt);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .painel-card__placa {
            font-family: monospace;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #fff;
            background: #1D4ED8;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
        }

        .painel-card__nome {
            font-size: 16px;
            font-weight: 600;
            color: var(--color-text);
            flex: 1;
            margin-left: 18px;
        }

        .painel-vazio {
            color: var(--color-text-muted);
            font-size: 14px;
            text-align: center;
            padding: 40px 10px;
        }
    </style>
</head>

<body>

    <div class="painel-header">
        <div class="painel-header__brand">
            <span class="dot"></span> Mecanize
        </div>
        <div class="painel-header__direita">
            <div class="painel-header__relogio" id="relogio">--:--</div>

            <!-- Toggle de tema — desenho próprio do painel (ver comentário no CSS) -->
            <button class="painel-theme-btn" id="temaToggle" aria-label="Alternar tema claro/escuro">
                <svg class="icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5" />
                    <line x1="12" y1="1" x2="12" y2="3" />
                    <line x1="12" y1="21" x2="12" y2="23" />
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                    <line x1="1" y1="12" x2="3" y2="12" />
                    <line x1="21" y1="12" x2="23" y2="12" />
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                </svg>
                <svg class="icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
            </button>
        </div>
    </div>

    <div class="painel-colunas">
        <div class="painel-coluna painel-coluna--andamento">
            <div class="painel-coluna__titulo">
                <span class="dot-status"></span> Em Andamento
            </div>
            <div class="painel-lista">
                <?php if (empty($emAndamento)): ?>
                    <div class="painel-vazio">Nenhum veículo em andamento.</div>
                <?php else: ?>
                    <?php foreach ($emAndamento as $os): ?>
                        <div class="painel-card">
                            <span class="painel-card__placa"><?= htmlspecialchars(strtoupper($os['placa'] ?? '—')) ?></span>
                            <span class="painel-card__nome"><?= htmlspecialchars(explode(' ', $os['cliente'] ?? '—')[0]) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="painel-coluna painel-coluna--pronto">
            <div class="painel-coluna__titulo">
                <span class="dot-status"></span> Pronto para Retirada
            </div>
            <div class="painel-lista">
                <?php if (empty($prontas)): ?>
                    <div class="painel-vazio">Nenhum veículo pronto no momento.</div>
                <?php else: ?>
                    <?php foreach ($prontas as $os): ?>
                        <div class="painel-card">
                            <span class="painel-card__placa"><?= htmlspecialchars(strtoupper($os['placa'] ?? '—')) ?></span>
                            <span class="painel-card__nome"><?= htmlspecialchars(explode(' ', $os['cliente'] ?? '—')[0]) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function atualizarRelogio() {
            document.getElementById('relogio').textContent =
                new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
        atualizarRelogio();
        setInterval(atualizarRelogio, 1000);

        // ── Dark mode toggle (chave de localStorage própria do painel — ver nota no <head>) ──
        (function () {
            var html = document.documentElement;
            var btn = document.getElementById('temaToggle');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var dark = html.getAttribute('data-theme') !== 'dark';
                html.setAttribute('data-theme', dark ? 'dark' : 'light');
                localStorage.setItem('mecanize-theme-painel', dark ? 'dark' : 'light');
            });
        })();
    </script>

</body>

</html>
