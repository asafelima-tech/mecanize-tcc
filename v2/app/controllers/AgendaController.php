<?php

/** Agenda: a equipe marca, edita, confirma e cancela; excluir é do ADMIN. */
final class AgendaController extends Controller
{
    public function lista(): void
    {
        [$periodo, $ini, $fim, $ref] = $this->periodo();
        $f = ['status' => (string) ($_GET['status'] ?? ''), 'tecnico' => (int) ($_GET['tecnico'] ?? 0)];
        $m = new Agendamento();

        $this->tela('agenda/lista', [
            'periodo' => $periodo, 'ini' => $ini, 'fim' => $fim, 'ref' => $ref, 'f' => $f,
            'agendamentos' => $m->porPeriodo($ini, $fim, $f),
            'resumo' => $m->resumo($ini, $fim),
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

    /** dia | semana, a partir de ?data= (padrão: hoje). Devolve [periodo, início, fim exclusivo, referência]. */
    private function periodo(): array
    {
        $periodo = ($_GET['periodo'] ?? 'semana') === 'dia' ? 'dia' : 'semana';
        $ref = new DateTimeImmutable($this->dataValida($_GET['data'] ?? '') ?? 'today');
        if ($periodo === 'dia') {
            return ['dia', $ref->format('Y-m-d'), $ref->modify('+1 day')->format('Y-m-d'), $ref];
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
