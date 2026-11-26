<?php

final class VeiculosController extends Cadastro
{
    public function lista(): void
    {
        $p = new Paginacao();
        $f = $this->filtros(['marca', 'combustivel', 'novos']);
        $m = new Veiculo();
        $this->tela('veiculos/lista', ['veiculos' => $m->listar($f, $p), 'f' => $f, 'p' => $p, 'resumo' => $m->resumo(), 'marcas' => $m->marcas()]);
    }

    public function ver(): void
    {
        $m = new Veiculo();
        $id = $this->idDaUrl();
        $veiculo = $m->buscar($id) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './veiculos']);

        $this->tela('veiculos/ver', ['v' => $veiculo, 'historico' => $m->historico($id)]);
    }

    public function form(): void
    {
        $id = $this->idOpcional();
        $veiculo = $id
            ? ((new Veiculo())->buscar($id) ?? throw new ErroHttp('registro-inexistente'))
            : ['cliente_id' => (int) ($_GET['cliente'] ?? 0)];
        $this->desenharForm($veiculo, $id);
    }

    public function salvar(): void
    {
        $id = $this->idOpcional();
        $modelos = new ModeloVeiculo();
        $ano = (int) ($_POST['ano'] ?? 0);

        $v = (new Validator($_POST))
            ->required('cliente_id', 'Cliente')
            ->required('placa', 'Placa')
            ->custom('placa', fn($p) => $p === null || $p === '' || Veiculo::normalizarPlaca((string) $p) !== null, 'Placa inválida. Use ABC-1234 ou ABC1D23.')
            ->custom('ano', fn() => $ano === 0 || ($ano >= 1900 && $ano <= (int) date('Y') + 1), 'Ano inválido.')
            ->custom('modelo_id', fn($m) => !empty($m) ? $modelos->existe((int) $m) : trim((string) ($_POST['modelo_texto'] ?? '')) !== '', 'Escolha o modelo na lista ou escreva o modelo.');

        if ($v->fails()) {
            $this->desenharForm($_POST, $id, $v->firstError());
            return;
        }

        try {
            $id = (new Veiculo())->salvar($_POST, $id);
        } catch (DomainException $e) {
            $this->desenharForm($_POST, $id, $e->getMessage());
            return;
        }

        $this->avisar('Veículo salvo.');
        $this->redirecionar('veiculo', ['id' => $id]);
    }

    public function ativo(): void
    {
        $this->alternarAtivo(new Veiculo(), 'veiculo', 'Veículo');
    }

    public function excluir(): void
    {
        $this->removerRegistro(new Veiculo(), 'veiculo', 'veiculos', 'Veículo');
    }

    /** JSON: modelos de uma marca, para o campo "Modelo" do formulário. */
    public function modelos(): void
    {
        $this->json((new ModeloVeiculo())->daMarca((string) ($_GET['marca'] ?? '')));
    }

    /**
     * Lê a placa de uma foto (JSON, POST com o arquivo em "imagem"). A resposta é uma SUGESTÃO: o cadastro mostra
     * a leitura e só preenche o campo quando a pessoa escolhe. Nada é gravado. Limite de leituras por sessão,
     * porque cada uma tem custo no plano do serviço.
     */
    public function lerPlaca(): void
    {
        $arquivo = $_FILES['imagem'] ?? null;
        $vazio = ['ok' => false, 'placa' => null, 'formato' => null, 'candidatos' => [], 'baixa_confianca' => true, 'requer_conferencia' => true];

        // O tipo real sai do CONTEÚDO (em LeituraPlaca), nunca de $_FILES['type'], que vem do navegador.
        if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
            $grande = in_array($arquivo['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            $this->json(['codigo' => $grande ? 'TAMANHO' : 'IMAGEM_INVALIDA',
                'erro' => $grande ? 'A foto é grande demais. Tire de novo, mais perto da placa.' : 'Nenhuma imagem foi recebida. Tire a foto novamente.'] + $vazio);
        }

        $leitor = new LeituraPlaca();
        if (!$leitor->configurada()) {
            $this->json(['codigo' => 'NAO_CONFIGURADO', 'erro' => 'A leitura automática de placa não está configurada neste sistema. Digite a placa.'] + $vazio);
        }

        $historico = (array) ($_SESSION['placa_leituras'] ?? []);
        $permitido = LeituraPlaca::dentroDoLimite($historico, time());
        $_SESSION['placa_leituras'] = $historico;
        if (!$permitido) {
            $this->json(['codigo' => 'LIMITE_SESSAO', 'erro' => 'Muitas leituras seguidas. Aguarde alguns minutos ou digite a placa.'] + $vazio, 429);
        }

        header('Cache-Control: no-store');
        $this->json($leitor->ler($arquivo['tmp_name']));
    }

    private function desenharForm(array $veiculo, ?int $id, ?string $erro = null): void
    {
        $this->tela('veiculos/form', [
            'v' => $veiculo, 'id' => $id, 'erro' => $erro,
            'clientes' => (new Cliente())->opcoes(),
            'marcas' => (new ModeloVeiculo())->marcas(),
        ]);
    }
}
