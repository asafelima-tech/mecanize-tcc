<!-- 500 — página autônoma, sem depender de header/footer/CSS externo, porque
     é exibida justamente quando algo na infraestrutura (banco, config) falhou. -->
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mecanize — erro inesperado</title>
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #F8FAFC;
      font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      color: #0F172A;
    }

    .card {
      max-width: 460px;
      width: 100%;
      text-align: center;
      padding: 48px 36px;
      margin: 20px;
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
    }

    .icone {
      width: 84px;
      height: 84px;
      border-radius: 18px;
      background: #FEF2F2;
      color: #DC2626;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 28px;
      font-size: 34px;
    }

    .numero {
      font-size: 88px;
      font-weight: 800;
      line-height: 1;
      letter-spacing: -0.04em;
      margin-bottom: 14px;
    }

    .numero span {
      color: #DC2626;
    }

    h1 {
      font-size: 19px;
      font-weight: 700;
      letter-spacing: -0.01em;
      margin: 0 0 8px;
    }

    p {
      font-size: 13px;
      color: #64748B;
      line-height: 1.7;
      margin-bottom: 30px;
    }

    a.btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 10px;
      background: #003BDD;
      color: #fff;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
    }
  </style>
</head>

<body>
  <div class="card">
    <div class="icone">!</div>
    <div class="numero">5<span>0</span>0</div>
    <h1>Algo deu errado</h1>
    <p>
      Ocorreu um erro inesperado ao processar sua solicitação.<br>
      Já foi registrado — tente novamente em instantes.
    </p>
    <a class="btn" href="./home">Ir para o início</a>
  </div>
</body>

</html>
