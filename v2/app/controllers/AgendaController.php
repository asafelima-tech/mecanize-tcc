<?php

/** Agenda: a equipe marca, edita, confirma e cancela; excluir é do ADMIN. */
final class AgendaController extends Controller
{
    public function lista(): void
    {
        [$periodo, $ini, $fim, $ref] = $this->periodo();
        $f = ['status' => (string) ($_GET['status'] ?? ''), 'tecnico' => (int) ($_GET['tecnico'] ?? 0)];
        $m = new Agendamento();

        // Mês do calendário lateral: o da data escolhida, ou outro com ?mes=AAAA-MM (setas do calendário).
        $mes = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($_GET['mes'] ?? '')) ? new DateTimeImmutable($_GET['mes'] . '-01') : $ref->modify('first day of this month');

        $this->tela('agenda/lista', [
            'periodo' => $periodo, 'ini' => $ini, 'fim' => $fim, 'ref' => $ref, 'f' => $f, 'mes' => $mes,
            'agendamentos' => $m->porPeriodo($ini, $fim, $f),
            'resumo' => $m->resumo($ini, $fim),
            'diasMarcados' => $m->diasComAgendamento($mes->format('Y-m-d'), $mes->modify('+1 month')->format('Y-m-d')),
            'tecnicos' => (new Usuario())->tecnicos(),
        ]);
    }

    public function form(): void
    {
        $id = ($_GET['id'] ?? '') === '' ? null : $this->idDaUrl();
        $ag = $id ? ((new Agendamento())->buscar($id) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './agenda'])) : [];
        if ($ag) {
            $ag['data'] = substr($ag['data_hora'], 0, 10);
            $ag['hora'] = substr($ag['data_hora'], 11, 5);
        } else {
            $ag = ['data' => $this->dataValida($_GET['data'] ?? '') ?? date('Y-m-d'), 'hora' => '08:00', 'duracao_min' => 60,
                   'cliente_id' => (int) ($_GET['cliente'] ?? 0)];
        }
        $this->formulario($ag, $id);
    }

    public function salvar(): void
    {
        $id = ($_GET['id'] ?? '') === '' ? null : $this->idDaUrl();
        try {
            $novo = (new Agendamento())->salvar($_POST, $id);
        } catch (DomainException $e) {
            $this->formulario($_POST, $id, $e->getMessage());
            return;
        }
        $this->avisar($id ? 'Agendamento atualizado.' : 'Horário agendado.');
        $this->redirecionar('agenda', ['periodo' => 'dia', 'data' => (string) $_POST['data'], 'destaque' => $novo]);
    }

    public function marcar(): void
    {
        $id = $this->idDaUrl();
        try {
            (new Agendamento())->marcar($id, (string) ($_POST['status'] ?? ''));
            $this->avisar('Situação do agendamento atualizada.');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->voltar();
    }

    public function excluir(): void
    {
        $id = $this->idDaUrl();
        try {
            if (!(new Agendamento())->remover($id)) {
                throw new ErroHttp('registro-inexistente', ['voltar' => './agenda']);
            }
            $this->avisar('Agendamento excluído.');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->voltar();
    }

    /** "Gerar OS": abre o novo orçamento já preenchido; se já virou OS, abre a OS. */
    public function gerarOs(): void
    {
        $ag = (new Agendamento())->buscar($this->idDaUrl()) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './agenda']);
        if ($ag['ordem_servico_id']) {
            $this->redirecionar('os', ['id' => (int) $ag['ordem_servico_id']]);
        }
        if (!in_array($ag['status'], Agendamento::ATIVOS, true)) {
            $this->avisar('Só um agendamento agendado ou confirmado pode virar OS.', 'erro');
            $this->redirecionar('agenda', ['periodo' => 'dia', 'data' => substr($ag['data_hora'], 0, 10)]);
        }
        $this->redirecionar('os-nova', ['agendamento' => (int) $ag['id']]);
    }

    private function formulario(array $ag, ?int $id, ?string $erro = null): void
    {
        $this->tela('agenda/form', ['ag' => $ag, 'id' => $id, 'erro' => $erro,
            'clientes' => (new Cliente())->opcoes(), 'tecnicos' => (new Usuario())->tecnicos()]);
    }

    /** dia | semana | mes, a partir de ?data= (padrão: hoje). Devolve [periodo, início, fim exclusivo, referência]. */
    private function periodo(): array
    {
        $periodo = in_array($_GET['periodo'] ?? '', ['dia', 'mes'], true) ? $_GET['periodo'] : 'semana';
        $ref = new DateTimeImmutable($this->dataValida($_GET['data'] ?? '') ?? 'today');
        if ($periodo === 'dia') {
            return ['dia', $ref->format('Y-m-d'), $ref->modify('+1 day')->format('Y-m-d'), $ref];
        }
        if ($periodo === 'mes') {
            $primeiro = $ref->modify('first day of this month');

            return ['mes', $primeiro->format('Y-m-d'), $primeiro->modify('+1 month')->format('Y-m-d'), $ref];
        }
        $seg = $ref->modify('-' . ((int) $ref->format('N') - 1) . ' days');

        return ['semana', $seg->format('Y-m-d'), $seg->modify('+7 days')->format('Y-m-d'), $ref];
    }

    private function dataValida(string $d): ?string
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);

        return $dt && $dt->format('Y-m-d') === $d ? $d : null;
    }

    private function voltar(): never
    {
        $q = array_intersect_key($_POST, array_flip(['periodo', 'data', 'status', 'tecnico']));
        $this->redirecionar('agenda', array_filter($q, fn($v) => is_string($v) && $v !== ''));
    }
}
