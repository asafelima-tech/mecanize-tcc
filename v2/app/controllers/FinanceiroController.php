<?php

/** Contas a receber e a pagar, pagamentos, estornos e comissões. Só ADMIN. */
final class FinanceiroController extends Controller
{
    /** Sem ?tipo=: visão geral do período (Design System, tela 16). Com ?tipo=RECEBER|PAGAR: as contas. */
    public function lista(): void
    {
        if (!isset($_GET['tipo'])) {
            $this->resumo();
            return;
        }
        $p = new Paginacao();
        $f = [
            'tipo'     => ($_GET['tipo'] ?? '') === 'PAGAR' ? 'PAGAR' : 'RECEBER',
            'situacao' => isset(Lancamento::SITUACOES[$_GET['situacao'] ?? '']) ? $_GET['situacao'] : 'abertos',
            'busca'    => trim((string) ($_GET['busca'] ?? '')),
            'de'       => trim((string) ($_GET['de'] ?? '')),
            'ate'      => trim((string) ($_GET['ate'] ?? '')),
            'categoria'=> trim((string) ($_GET['categoria'] ?? '')),
        ];
        $m = new Lancamento();
        $r = $m->listar($f, $p);
        $this->tela('financeiro/lista', [
            'contas' => $r['linhas'], 'saldo' => $r['saldo'], 'f' => $f, 'p' => $p,
            'ind' => $m->indicadores($f['tipo']), 'outro' => $m->indicadores($f['tipo'] === 'PAGAR' ? 'RECEBER' : 'PAGAR'),
            'caixa' => (new Caixa())->aberto(),
        ]);
    }

    private function resumo(): void
    {
        $agrupar = in_array($_GET['agrupar'] ?? '', ['dia', 'semana', 'mes', 'ano'], true) ? $_GET['agrupar'] : 'mes';
        $padrao = ['dia' => '-13 days', 'semana' => 'monday this week -11 weeks', 'mes' => 'first day of this month -5 months', 'ano' => 'first day of january this year -2 years'][$agrupar];
        $data = fn (string $k) => ($d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET[$k] ?? ''))) && $d->format('Y-m-d') === $_GET[$k] ? $d : null;
        $ate = $data('ate') ?? new DateTimeImmutable('today');
        $de  = $data('de') ?? (new DateTimeImmutable('today'))->modify($padrao);
        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }
        if ($de->diff($ate)->days > 3660) {
            $de = $ate->modify('-10 years');
        }

        // Período anterior de mesmo tamanho, para as variações dos cartões.
        $dias = $de->diff($ate)->days + 1;
        $antes = new Relatorio($de->modify("-{$dias} days")->format('Y-m-d'), $de->modify('-1 day')->format('Y-m-d'));
        $r = new Relatorio($de->format('Y-m-d'), $ate->format('Y-m-d'));
        $p = new Paginacao();

        $this->tela('financeiro/resumo', [
            'agrupar' => $agrupar, 'de' => $de, 'ate' => $ate, 'busca' => trim((string) ($_GET['busca'] ?? '')),
            'caixa' => $r->caixa(), 'anterior' => $antes->caixa(), 'sessoes' => $r->sessoesCaixa(),
            'fluxo' => $r->fluxoNoTempo($agrupar), 'maiorSaida' => $r->maiorSaida(),
            'movimentos' => $r->movimentos($p, trim((string) ($_GET['busca'] ?? ''))), 'p' => $p,
            'abertas' => $r->contasEmAberto(),
        ]);
    }

    public function ver(): void
    {
        $m = new Lancamento();
        $l = $m->buscar($this->idDaUrl()) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './financeiro']);
        $this->tela('financeiro/ver', [
            'l' => $l, 'parcelas' => $m->parcelas($l), 'movimentos' => $m->movimentos((int) $l['id']),
            'historico' => $this->historico((int) $l['id']), 'caixa' => (new Caixa())->aberto(),
            'anexos' => (new Anexo())->daConta((int) $l['id']),
        ]);
    }

    public function form(): void
    {
        $tipo = ($_GET['tipo'] ?? '') === 'PAGAR' ? 'PAGAR' : 'RECEBER';
        $this->desenharForm(['tipo' => $tipo, 'parcelas' => 1, 'vencimento' => date('Y-m-d'), 'competencia' => date('Y-m-d')]);
    }

    public function salvar(): void
    {
        try {
            $id = (new Lancamento())->criar($_POST);
        } catch (DomainException $e) {
            $this->desenharForm($_POST, $e->getMessage());
            return;
        }
        $this->avisar('Conta lançada.');
        $this->redirecionar('conta', ['id' => $id]);
    }

    public function editar(): void
    {
        $id = $this->idDaUrl();
        $this->executar(fn() => (new Lancamento())->editar($id, $_POST), 'Conta alterada.', $id);
    }

    public function baixar(): void
    {
        $id = $this->idDaUrl();
        $this->executar(fn() => (new Lancamento())->baixar($id, $_POST), 'Pagamento registrado.', $id);
    }

    public function estornar(): void
    {
        $contaId = (int) ($_POST['conta_id'] ?? 0);
        $this->executar(fn() => (new Lancamento())->estornar($this->idDaUrl(), $this->post('motivo')), 'Pagamento estornado.', $contaId);
    }

    public function cancelar(): void
    {
        $id = $this->idDaUrl();
        $this->executar(fn() => (new Lancamento())->cancelar($id, $this->post('motivo')), 'Conta cancelada.', $id);
    }

    /**
     * Comissões: números do topo, a equipe com o que falta lançar, o detalhe de um técnico e o histórico das
     * contas de comissão. O período vale para o histórico e para "lançado" e "pago"; o pendente é sempre o total
     * (o lançamento leva todos os itens pendentes do técnico, como antes).
     */
    public function comissoes(): void
    {
        $m = new Lancamento();
        $p = new Paginacao();
        $data = fn(string $k) => ($d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET[$k] ?? ''))) && $d->format('Y-m-d') === $_GET[$k] ? $d : null;
        $ate = $data('ate') ?? new DateTimeImmutable('today');
        $de = $data('de') ?? (new DateTimeImmutable('first day of this month'))->modify('-2 months');
        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }
        $f = [
            'tecnico'  => (int) ($_GET['tecnico'] ?? 0),
            'de'       => $de->format('Y-m-d'),
            'ate'      => $ate->format('Y-m-d'),
            'situacao' => in_array($_GET['situacao'] ?? '', ['abertos', 'pagos', 'cancelados'], true) ? $_GET['situacao'] : 'todos',
        ];
        $equipe = $m->equipeComissoes();
        $funcionarios = (new Usuario())->tecnicos();
        // Só vale filtrar por alguém que aparece na tela (equipe com comissão ou técnico ativo).
        if ($f['tecnico'] && !in_array($f['tecnico'], array_map('intval', array_merge(array_column($equipe, 'id'), array_column($funcionarios, 'id'))), true)) {
            $f['tecnico'] = 0;
        }
        $this->tela('financeiro/comissoes', [
            'f' => $f, 'p' => $p, 'equipe' => $equipe,
            'resumo' => $m->resumoComissoes($f['de'], $f['ate'], $f['tecnico']),
            'itens' => $f['tecnico'] ? $m->itensComissao($f['tecnico']) : [],
            'historico' => $m->historicoComissoes($f, $p),
            'funcionarios' => $funcionarios,
            'caixa' => (new Caixa())->aberto(),
            // compatibilidade com quem ainda usa estes nomes
            'pendentes' => array_values(array_filter($equipe, fn($e) => (float) $e['pend_total'] > 0)), 'tecnico' => $f['tecnico'],
        ]);
    }

    public function gerarComissao(): void
    {
        try {
            $id = (new Lancamento())->gerarComissao((int) ($_POST['tecnico_id'] ?? 0), $this->post('vencimento'));
            $this->avisar('Conta a pagar da comissão lançada. Registre o pagamento quando pagar o técnico.');
            $this->redirecionar('conta', ['id' => $id]);
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
            $this->redirecionar('comissoes');
        }
    }

    /* ── Apoio ── */

    private function desenharForm(array $d, ?string $erro = null): void
    {
        $this->tela('financeiro/form', [
            'd' => $d, 'erro' => $erro, 'clientes' => (new Cliente())->opcoes(),
            'fornecedores' => (new Fornecedor())->opcoes(), 'funcionarios' => (new Usuario())->tecnicos(),
        ]);
    }

    private function historico(int $id): array
    {
        return (new Historico())->daConta($id);
    }

    private function executar(callable $operacao, string $sucesso, int $id): never
    {
        try {
            $r = $operacao();
            $id = is_int($r) && $r > 0 ? $r : $id;
            $this->avisar($sucesso);
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        // Pagamento feito pela lista de contas: volta para a mesma lista, com os mesmos filtros.
        $voltar = (string) ($_POST['voltar_lista'] ?? '');
        if ($voltar !== '' && preg_match('/^(financeiro|comissoes)(\?[A-Za-z0-9_=&%.\-+]*)?$/', $voltar)) {
            header('Location: ./' . $voltar);
            exit;
        }
        $id > 0 ? $this->redirecionar('conta', ['id' => $id]) : $this->redirecionar('financeiro');
    }
}
