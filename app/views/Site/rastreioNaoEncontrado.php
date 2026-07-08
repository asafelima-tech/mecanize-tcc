<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mecanize · Rastreamento</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/global.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script>
        (function () {
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <style>
        body {
            background: var(--color-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card-erro {
            max-width: 380px;
            width: 100%;
            text-align: center;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            padding: 36px 28px;
        }
    </style>
</head>

<body>
    <div class="card-erro">
        <div style="width:64px;height:64px;border-radius:50%;background:var(--color-danger-soft);color:var(--color-danger);
                    display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:26px;">
            <i class="bi bi-search"></i>
        </div>
        <div style="font-size:16px;font-weight:700;color:var(--color-text);margin-bottom:8px;">Link inválido ou expirado</div>
        <p style="font-size:13px;color:var(--color-text-muted);line-height:1.6;">
            Não encontramos nenhuma OS ou orçamento com este link. Confira o endereço ou entre em contato com a oficina.
        </p>
    </div>
</body>

</html>
