<?php

final class OrdensController extends Controller
{
    public function lista(): void
    {
        $p = new Paginacao();
        $f = [
            'busca'    => trim((string) ($_GET['busca'] ?? '')),
            'situacao' => isset(OrdemServico::FILTROS[$_GET['situacao'] ?? '']) ? $_GET['situacao'] : 'abertas',
            'tecnico'  => (int) ($_GET['tecnico'] ?? 0),
        ];
        $this->tela('ordens/lista', ['ordens' => (new OrdemServico())->listar($f, $p), 'f' => $f, 'p' => $p, 'tecnicos' => (new Usuario())->tecnicos()]);
    }

    public function nova(): void
    {
        $os = ['cliente_id' => (int) ($_GET['cliente'] ?? 0), 'veiculo_id' => (int) ($_GET['veiculo'] ?? 0),
               'validade_orcamento' => date('Y-m-d', strtotime('+10 days'))];

        // Vindo da agenda ("Gerar OS"): cliente, veículo, técnico e serviço já preenchidos.
        if (($_GET['agendamento'] ?? '') !== '') {
            $ag = (new Agendamento())->buscar($this->idDaUrl('agendamento')) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './agenda']);
            if ($ag['ordem_servico_id']) {
                $this->redirecionar('os', ['id' => (int) $ag['ordem_servico_id']]);
            }
            $os = ['cliente_id' => (int) $ag['cliente_id'], 'veiculo_id' => (int) $ag['veiculo_id'], 'tecnico_id' => (int) $ag['tecnico_id'],
                   'defeito_relatado' => trim($ag['servico'] . ($ag['observacoes'] ? "\n" . $ag['observacoes'] : '')),
                   'agendamento_id' => (int) $ag['id']] + $os;
        }

        $this->tela('ordens/nova', ['os' => $os, 'clientes' => (new Cliente())->opcoes(), 'tecnicos' => (new Usuario())->tecnicos()]);
    }

    public function criar(): void
    {
        $v = (new Validator($_POST))->required('cliente_id', 'Cliente')->required('veiculo_id', 'Veículo');
        $agendamento = (int) ($_POST['agendamento_id'] ?? 0);
        try {
            if ($v->fails()) {
                throw new DomainException($v->firstError());
            }
            // OS e vínculo com o agendamento na mesma transação: ou os dois, ou nenhum.
            $id = Transacao::executar(Banco::conexao(), function () use ($agendamento) {
                $id = (new OrdemServico())->criar($_POST);
                if ($agendamento && !(new Agendamento())->vincularOs($agendamento, $id, (int) $_POST['cliente_id'])) {
                    throw new DomainException('Este agendamento já virou OS, foi cancelado ou é de outro cliente.');
                }

                return $id;
            });
        } catch (DomainException $e) {
            $this->tela('ordens/nova', ['os' => $_POST, 'erro' => $e->getMessage(), 'clientes' => (new Cliente())->opcoes(), 'tecnicos' => (new Usuario())->tecnicos()]);
            return;
        }

        $this->avisar('Orçamento aberto. Inclua os serviços e peças.');
        $this->redirecionar('os', ['id' => $id]);
    }

    public function ver(): void
    {
        $m  = new OrdemServico();
        $id = $this->idDaUrl();
        $os = $m->buscar($id) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './ordens']);

        $this->tela('ordens/ver', [
            'os'        => $os,
            'itens'     => $m->itens($id),
            'historico' => $m->historico($id),
            'tecnicos'  => (new Usuario())->tecnicos(),
            'catalogo'  => $this->catalogo(),
            'contas'    => (new Lancamento())->daOs($id),
            'faltaLancar' => (new Lancamento())->faltaLancar($os),
            'caixa'     => (new Caixa())->aberto(),
            'anexos'    => (new Anexo())->daOs($id),
            'linkAcompanhamento' => $this->urlAbsoluta('acompanhar?t=' . $os['token_rastreio']),
        ]);
    }

    public function dados(): void
    {
        $id = $this->idDaUrl();
        $this->executar(fn() => (new OrdemServico())->atualizarDados($id, $_POST), 'Dados da OS salvos.', $id);
    }

    public function item(): void
    {
        $id = $this->idDaUrl();
        $m  = new OrdemServico();
        $itemId = (int) ($_POST['item_id'] ?? 0);

        switch ($_POST['operacao'] ?? '') {
            case 'incluir':
                $this->executar(fn() => $m->adicionarItem($id, $_POST), 'Item incluído.', $id);
            case 'alterar':
                $this->executar(fn() => $m->alterarItem($id, $itemId, $_POST), 'Item alterado.', $id);
            case 'remover':
                $this->executar(fn() => $m->removerItem($id, $itemId, $this->post('motivo')), 'Item removido.', $id);
            default:
                throw new ErroHttp('nao-encontrado');
        }
    }

    public function acao(): void
    {
        $id = $this->idDaUrl();
        $m  = new OrdemServico();

        switch ($_POST['acao'] ?? '') {
            case 'aprovar':
                $assinatura = (string) ($_POST['assinatura'] ?? '');
                // Só PNG gerado pelo canvas de assinatura; o resto é descartado.
                $assinatura = str_starts_with($assinatura, 'data:image/png;base64,') && strlen($assinatura) < 400000 ? $assinatura : null;
                $this->executar(fn() => $m->aprovar($id, $this->post('aprovado_por'), $assinatura), 'Orçamento aprovado.', $id);
            case 'recusar':
                $this->executar(fn() => $m->recusar($id, $this->post('motivo')), 'Orçamento recusado.', $id);
            case 'reabrir':
                $this->executar(fn() => $m->reabrir($id), 'Orçamento reaberto.', $id);
            case 'iniciar':
                $this->executar(function () use ($m, $id) {
                    $faltam = $m->iniciar($id);
                    if ($faltam) {
                        $this->avisar('Execução iniciada, mas falta estoque de: ' . implode(', ', $faltam) . '. A OS ficou aguardando peça.', 'aviso');
                    }
                }, 'Execução iniciada. Peças baixadas do estoque.', $id);
            case 'baixar':
                $this->executar(function () use ($m, $id) {
                    $faltam = $m->baixarPendentes($id);
                    if ($faltam) {
                        $this->avisar('Ainda sem estoque: ' . implode(', ', $faltam) . '.', 'aviso');
                    }
                }, 'Peças baixadas. A OS voltou para execução.', $id);
            case 'concluir':
                $this->executar(fn() => $m->concluir($id), 'Serviço concluído. O veículo está pronto para entrega.', $id);
            case 'retomar':
                $this->executar(fn() => $m->retomar($id, $this->post('motivo')), 'A OS voltou para execução.', $id);
            case 'entregar':
                $this->executar(fn() => $m->entregar($id, $this->post('checklist_entrega')), 'Veículo entregue.', $id);
            case 'cobrar':
                $this->executar(fn() => (new Lancamento())->cobrarOs($id, $_POST), 'Cobrança lançada.', $id);
            case 'receber':
                $this->executar(fn() => (new Lancamento())->receberDaOs($id, (int) ($_POST['conta_id'] ?? 0), $_POST), 'Recebimento registrado.', $id);
            case 'cancelar':
                $this->executar(fn() => $m->cancelar($id, $this->post('motivo')), 'OS cancelada. Peças baixadas voltaram ao estoque.', $id);
            default:
                throw new ErroHttp('nao-encontrado');
        }
    }

    public function imprimir(): void
    {
        $m  = new OrdemServico();
        $id = $this->idDaUrl();
        $os = $m->buscar($id) ?? throw new ErroHttp('registro-inexistente');

        $this->tela('ordens/imprimir', ['os' => $os, 'itens' => $m->itens($id), 'oficina' => (new Oficina())->atual()], null);
    }

    /** JSON: veículos ativos de um cliente (campo "Veículo" da nova OS). */
    public function veiculosDoCliente(): void
    {
        $lista = array_values(array_filter((new Cliente())->veiculos((int) ($_GET['cliente'] ?? 0)), fn($v) => (int) $v['ativo'] === 1));
        $this->json(array_map(fn($v) => ['id' => (int) $v['id'], 'rotulo' => $v['placa'] . ' — ' . $v['modelo']], $lista));
    }

    /** Página pública de acompanhamento (o link que a oficina manda ao cliente). */
    public function acompanhar(): void
    {
        $os = (new OrdemServico())->porToken((string) ($_GET['t'] ?? '')) ?? throw new ErroHttp('rastreio-invalido');
        $this->tela('ordens/acompanhar', ['os' => $os], null);
    }

    /* ── Apoio ── */

    /** Roda a operação; erro de regra volta para a OS com a mensagem. */
    private function executar(callable $operacao, string $sucesso, int $id): never
    {
        try {
            $operacao();
            if (!isset($_SESSION['aviso'])) {
                $this->avisar($sucesso);
            }
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('os', ['id' => $id]);
    }

    private function catalogo(): array
    {
        // Catálogo ativo inteiro para o campo de escolha (oficinas pequenas/médias); a busca é no navegador.
        return (new Produto())->listarParaEscolha();
    }

    private function urlAbsoluta(string $caminho): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/' . $caminho;
    }
}
