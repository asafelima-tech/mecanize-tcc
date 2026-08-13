<?php

require_once __DIR__ . '/../core/auth.php';

class AgendamentoController extends Controller
{
    /**
     * Agenda por período (dia/semana/mês), com filtros de status e mecânico.
     */
    public function list()
    {
        $periodo = $this->resolverPeriodo();

        $agendamento = $this->model('Agendamento');

        $filtros = [
            'status'      => $_GET['status'] ?? null,
            'mecanico_id' => $_GET['mecanico_id'] ?? null,
        ];

        $dadosForm = $agendamento->getDadosFormulario();

        $this->view('Agendamentos/listAgendamentos', [
            'periodo'      => $periodo,
            'agendamentos' => $agendamento->porPeriodo($periodo['inicio'], $periodo['fim'], $filtros),
            'resumo'       => $agendamento->resumoPeriodo($periodo['inicio'], $periodo['fim']),
            'mecanicos'    => $dadosForm['mecanicos'],
            'filtros'      => $filtros,
            'ok'           => $_GET['ok'] ?? null,
            'erro'         => $_GET['erro'] ?? null,
        ]);
    }

    /**
     * GET  → formulário de novo agendamento.
     * POST → valida e grava.
     */
    public function save()
    {
        $this->requireRole(['ADMIN', 'ATENDENTE']);

        $agendamento = $this->model('Agendamento');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $dados = $agendamento->getDadosFormulario();
            // Permite abrir o formulário já com a data clicada na agenda.
            $dados['old'] = ['data' => $_GET['data'] ?? date('Y-m-d')];
            $this->view('Agendamentos/saveAgendamento', $dados);
            return;
        }

        $erro = $this->validarEntrada($_POST);

        if ($erro === null) {
            $dataHora = $_POST['data'] . ' ' . $_POST['hora'] . ':00';
            $duracao  = (int) $_POST['duracao_min'];

            if ($agendamento->temConflito($dataHora, $duracao, $_POST['mecanico_id'] ?? null)) {
                $erro = !empty($_POST['mecanico_id'])
                    ? 'Esse mecânico já tem outro serviço marcado nesse horário.'
                    : 'Já existe outro agendamento nesse horário.';
            }
        }

        if ($erro !== null) {
            $dados          = $agendamento->getDadosFormulario();
            $dados['error'] = $erro;
            $dados['old']   = $_POST;
            $this->view('Agendamentos/saveAgendamento', $dados);
            return;
        }

        $id = $agendamento->criar([
            'cliente_id'         => $_POST['cliente_id'],
            'veiculo_id'         => $_POST['veiculo_id']  ?? null,
            'mecanico_id'        => $_POST['mecanico_id'] ?? null,
            'data_hora'          => $_POST['data'] . ' ' . $_POST['hora'] . ':00',
            'duracao_min'        => (int) $_POST['duracao_min'],
            'servico'            => $_POST['servico'],
            'observacoes'        => $_POST['observacoes'] ?? null,
            'status'             => 'AGENDADO',
            'usuario_criacao_id' => $_SESSION['user_id_usuario'] ?? null,
        ]);

        if (!$id) {
            $dados          = $agendamento->getDadosFormulario();
            $dados['error'] = 'Não foi possível salvar o agendamento.';
            $dados['old']   = $_POST;
            $this->view('Agendamentos/saveAgendamento', $dados);
            return;
        }

        $this->notificarClienteAgendamento($agendamento, $id);

        header('Location: ./listAgendamentos?data=' . urlencode($_POST['data']) . '&periodo=dia&ok=criado');
        exit;
    }

    /**
     * GET  → formulário preenchido.
     * POST → valida e atualiza.
     */
    public function edit()
    {
        $this->requireRole(['ADMIN', 'ATENDENTE']);

        $agendamento = $this->model('Agendamento');

        $id = (int) ($_POST['id_agendamento'] ?? $_GET['id'] ?? 0);

        if (!$id || !($registro = $agendamento->getById($id))) {
            header('Location: ./listAgendamentos');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $dados                = $agendamento->getDadosFormulario();
            $dados['agendamento'] = $registro;
            $this->view('Agendamentos/editAgendamento', $dados);
            return;
        }

        $erro = $this->validarEntrada($_POST);

        if ($erro === null) {
            $dataHora = $_POST['data'] . ' ' . $_POST['hora'] . ':00';

            // $id ignorado no conflito: um agendamento não colide consigo mesmo.
            if ($agendamento->temConflito($dataHora, (int) $_POST['duracao_min'], $_POST['mecanico_id'] ?? null, $id)) {
                $erro = 'Já existe outro agendamento nesse horário.';
            }
        }

        if ($erro !== null) {
            $dados                = $agendamento->getDadosFormulario();
            $dados['agendamento'] = array_merge($registro, $_POST);
            $dados['error']       = $erro;
            $this->view('Agendamentos/editAgendamento', $dados);
            return;
        }

        $agendamento->atualizar($id, [
            'cliente_id'  => $_POST['cliente_id'],
            'veiculo_id'  => $_POST['veiculo_id']  ?? null,
            'mecanico_id' => $_POST['mecanico_id'] ?? null,
            'data_hora'   => $_POST['data'] . ' ' . $_POST['hora'] . ':00',
            'duracao_min' => (int) $_POST['duracao_min'],
            'servico'     => $_POST['servico'],
            'observacoes' => $_POST['observacoes'] ?? null,
            'status'      => $_POST['status'] ?? 'AGENDADO',
        ]);

        header('Location: ./listAgendamentos?data=' . urlencode($_POST['data']) . '&periodo=dia&ok=editado');
        exit;
    }

    /**
     * Botões rápidos de status (confirmar / cancelar / faltou), via POST.
     */
    public function status()
    {
        $this->requireRole(['ADMIN', 'ATENDENTE']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listAgendamentos');
            exit;
        }

        $id     = (int) ($_POST['id_agendamento'] ?? 0);
        $status = strtoupper(trim($_POST['status'] ?? ''));

        $ok = $id && $this->model('Agendamento')->atualizarStatus($id, $status);

        header('Location: ./listAgendamentos?' . $this->voltarPara() . ($ok ? '&ok=status' : '&erro=status'));
        exit;
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listAgendamentos');
            exit;
        }

        $this->requireRole(['ADMIN']);

        $id = (int) ($_POST['id_agendamento'] ?? 0);
        $ok = $id && $this->model('Agendamento')->excluir($id);

        header('Location: ./listAgendamentos?' . $this->voltarPara() . ($ok ? '&ok=excluido' : '&erro=excluido'));
        exit;
    }

    /**
     * Leva o agendamento para o formulário de nova OS, já preenchido com o
     * cliente/veículo marcados, e guarda o vínculo pra agenda saber que aquele
     * horário virou serviço.
     *
     * A OS em si é criada pelo fluxo normal de OrdemServico — aqui só se faz
     * o encaminhamento, para não duplicar a regra de criação de OS.
     */
    public function gerarOrdemServico()
    {
        $this->requireRole(['ADMIN', 'ATENDENTE']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ./listAgendamentos');
            exit;
        }

        $id = (int) ($_POST['id_agendamento'] ?? 0);

        $agendamento = $this->model('Agendamento');
        $registro    = $id ? $agendamento->getById($id) : null;

        if (!$registro) {
            header('Location: ./listAgendamentos?erro=os');
            exit;
        }

        if (!empty($registro['ordem_servico_id'])) {
            // Já convertido: manda direto pra OS existente em vez de criar outra.
            header('Location: ./viewOrdemServico?id=' . (int) $registro['ordem_servico_id']);
            exit;
        }

        $query = http_build_query([
            'cliente_id'     => $registro['cliente_id'],
            'veiculo_id'     => $registro['veiculo_id'],
            'agendamento_id' => $id,
            'defeito'        => $registro['servico'],
        ]);

        header('Location: ./saveOrdemServico?' . $query);
        exit;
    }

    /**
     * Regras comuns a criar/editar. Devolve a mensagem de erro ou null.
     *
     * O formulário marca os campos como required, mas isso é só o navegador —
     * um POST direto passaria por cima, então a validação real é esta.
     */
    private function validarEntrada(array $post): ?string
    {
        $validator = (new Validator($post))
            ->required('cliente_id', 'Cliente')
            ->required('data', 'Data')
            ->required('hora', 'Hora')
            ->required('servico', 'Serviço');

        if ($validator->fails()) {
            return $validator->firstError();
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['data'])) {
            return 'Data inválida.';
        }

        [$ano, $mes, $dia] = explode('-', $post['data']);
        if (!checkdate((int) $mes, (int) $dia, (int) $ano)) {
            return 'Data inválida.';
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $post['hora'] ?? '')) {
            return 'Hora inválida.';
        }

        if (!in_array((int) ($post['duracao_min'] ?? 0), Agendamento::DURACOES, true)) {
            return 'Duração inválida.';
        }

        if (isset($post['status']) && !in_array($post['status'], Agendamento::STATUS, true)) {
            return 'Status inválido.';
        }

        return null;
    }

    /**
     * Traduz ?periodo=dia|semana|mes (+ ?data=YYYY-MM-DD) num intervalo.
     * A data de referência é sempre reconstruída por date(), nunca repassada
     * crua — entrada inválida cai em hoje.
     */
    private function resolverPeriodo(): array
    {
        $tipo = $_GET['periodo'] ?? 'semana';

        $ref = $_GET['data'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ref) || !strtotime($ref)) {
            $ref = date('Y-m-d');
        }

        $ts = strtotime($ref);

        switch ($tipo) {
            case 'dia':
                $inicio = $fim = date('Y-m-d', $ts);
                $rotulo = $this->diaSemana((int) date('N', $ts)) . ', ' . date('d/m/Y', $ts);
                break;

            case 'mes':
                $inicio = date('Y-m-01', $ts);
                $fim    = date('Y-m-t', $ts);
                $rotulo = $this->nomeMes((int) date('m', $ts)) . ' de ' . date('Y', $ts);
                break;

            case 'semana':
            default:
                $tipo      = 'semana';
                $diaSemana = (int) date('N', $ts);
                $inicio    = date('Y-m-d', strtotime("-" . ($diaSemana - 1) . " days", $ts));
                $fim       = date('Y-m-d', strtotime($inicio . ' +6 days'));
                $rotulo    = 'Semana de ' . date('d/m', strtotime($inicio)) . ' a ' . date('d/m/Y', strtotime($fim));
                break;
        }

        // Passos de navegação (anterior/próximo) coerentes com o período.
        $passo = ['dia' => '1 day', 'semana' => '1 week', 'mes' => '1 month'][$tipo];

        return [
            'tipo'     => $tipo,
            'ref'      => $ref,
            'inicio'   => $inicio,
            'fim'      => $fim,
            'rotulo'   => $rotulo,
            'anterior' => date('Y-m-d', strtotime("-{$passo}", strtotime($inicio))),
            'proximo'  => date('Y-m-d', strtotime("+{$passo}", strtotime($inicio))),
        ];
    }

    /** Preserva período/data ao redirecionar depois de uma ação. */
    private function voltarPara(): string
    {
        $periodo = $_POST['periodo'] ?? 'semana';
        $data    = $_POST['data_ref'] ?? date('Y-m-d');

        if (!in_array($periodo, ['dia', 'semana', 'mes'], true)) {
            $periodo = 'semana';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $data = date('Y-m-d');
        }

        return http_build_query(['periodo' => $periodo, 'data' => $data]);
    }

    /**
     * Avisa o cliente por e-mail que o horário ficou marcado. Uma falha de
     * SMTP não pode derrubar o agendamento, que já está gravado — o erro é
     * registrado e o fluxo segue (mesmo tratamento das notificações de OS
     * concluída e orçamento aprovado).
     */
    private function notificarClienteAgendamento($agendamento, $id): void
    {
        try {
            $registro = $agendamento->getById($id);

            if (!$registro || empty($registro['cliente_email'])) {
                return;
            }

            $quando  = date('d/m/Y \à\s H:i', strtotime($registro['data_hora']));
            $veiculo = trim(($registro['veiculo_marca'] ?? '') . ' ' . ($registro['veiculo_modelo'] ?? ''));

            $corpo = "<p>Olá, " . htmlspecialchars($registro['cliente_nome']) . ".</p>"
                . "<p>Seu atendimento está agendado para <strong>" . $quando . "</strong>.</p>"
                . "<p><strong>Serviço:</strong> " . htmlspecialchars($registro['servico']) . "</p>";

            if ($veiculo !== '' || !empty($registro['veiculo_placa'])) {
                $corpo .= "<p><strong>Veículo:</strong> " . htmlspecialchars(trim($veiculo . ' ' . ($registro['veiculo_placa'] ?? ''))) . "</p>";
            }

            $corpo .= "<p>Se precisar remarcar, é só entrar em contato com a oficina.</p>";

            require_once __DIR__ . '/../core/Mailer.php';
            (new Mailer())->enviar($registro['cliente_email'], 'Agendamento confirmado - Mecanize', $corpo);
        } catch (\Throwable $e) {
            error_log('[Agendamento] Falha ao enviar notificação: ' . $e->getMessage());
        }
    }

    private function diaSemana(int $n): string
    {
        $dias = [1 => 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'];

        return $dias[$n] ?? '';
    }

    private function nomeMes(int $mes): string
    {
        $meses = [
            1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
        ];

        return $meses[$mes] ?? '';
    }
}
