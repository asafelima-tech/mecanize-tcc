<?php

/**
 * Área do técnico, pensada para o celular: os serviços dele, a execução de cada um (começar,
 * materiais usados, pedidos, fotos e "terminei") e os pedidos de material.
 *
 * Todas as rotas são do nível TECNICO e cada OS passa por ServicoTecnico::exigir(), que confere se o
 * técnico logado está nela — trocar o id na URL ou no formulário não abre a OS de outro técnico.
 */
final class TecnicoController extends Controller
{
    public function painel(): void
    {
        $m = new ServicoTecnico();
        $aba = isset(ServicoTecnico::ABAS[$_GET['aba'] ?? '']) ? $_GET['aba'] : 'fazer';

        $this->tela('tecnico/painel', [
            'aba'        => $aba,
            'servicos'   => $m->servicos($aba),
            'contagens'  => $m->contagens(),
            'pedidosAbertos' => (new Solicitacao())->emAberto((int) Contexto::usuarioId()),
            'titulo'     => 'Meus serviços',
        ]);
    }

    public function ver(): void
    {
        $m  = new ServicoTecnico();
        $id = $this->idDaUrl();
        $os = $m->exigir($id);

        $this->tela('tecnico/servico', [
            'os'        => $os,
            'itens'     => $m->itens($id),
            'materiais' => $m->materiais($id),
            'pedidos'   => array_values(array_filter((new Solicitacao())->daOs($id), fn($s) => (int) $s['tecnico_id'] === (int) Contexto::usuarioId())),
            'catalogo'  => $m->catalogoPecas(),
            'fotos'     => array_values(array_filter((new Anexo())->daOs($id), fn($a) => in_array($a['categoria'], ['FOTO_ANTES', 'FOTO_DURANTE', 'FOTO_DEPOIS', 'PROBLEMA'], true))),
            'andamento' => $m->andamento($id),
            'titulo'    => os_numero($os['numero']),
        ]);
    }

    /** Começar e "terminei" (enviar para validação). */
    public function acao(): void
    {
        $id = $this->idDaUrl();
        (new ServicoTecnico())->exigir($id); // antes de qualquer coisa: a OS é dele?
        $os = new OrdemServico();

        try {
            switch ($_POST['acao'] ?? '') {
                case 'comecar':
                    $faltam = $os->comecarExecucao($id);
                    $faltam
                        ? $this->avisar('Serviço iniciado, mas falta no estoque: ' . implode(', ', $faltam) . '. O administrador já foi avisado.', 'aviso')
                        : $this->avisar('Serviço iniciado. Bom trabalho!');
                    break;
                case 'terminar':
                    $os->enviarParaValidacao($id, $this->post('relato'));
                    $this->avisar('Pronto! O serviço foi enviado para o administrador validar.');
                    break;
                default:
                    throw new ErroHttp('nao-encontrado');
            }
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('servico', ['id' => $id]);
    }

    /** Registrar ou desfazer um material usado. */
    public function material(): void
    {
        $id = $this->idDaUrl();
        $m  = new ServicoTecnico();

        try {
            switch ($_POST['operacao'] ?? '') {
                case 'registrar':
                    $m->registrarMaterial($id, $_POST);
                    $this->avisar('Material registrado. Ele será conferido pelo administrador.');
                    break;
                case 'cancelar':
                    $m->cancelarMaterial($id, (int) ($_POST['material_id'] ?? 0));
                    $this->avisar('Material retirado da lista.');
                    break;
                default:
                    throw new ErroHttp('nao-encontrado');
            }
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('servico', ['id' => $id, 'aba' => 'materiais']);
    }

    /** Pedidos de material do técnico e o formulário de novo pedido. */
    public function pedidos(): void
    {
        $m = new ServicoTecnico();
        $this->tela('tecnico/pedidos', [
            'pedidos'  => (new Solicitacao())->doTecnico(),
            'abertas'  => $m->abertas(),
            'catalogo' => $m->catalogoPecas(),
            'novo'     => ['tipo' => isset(Solicitacao::TIPOS[$_GET['tipo'] ?? '']) ? $_GET['tipo'] : '', 'ordem_servico_id' => (int) ($_GET['os'] ?? 0)],
            'titulo'   => 'Pedidos de material',
        ]);
    }

    public function criarPedido(): void
    {
        $voltarOs = (int) ($_POST['voltar_os'] ?? 0);
        try {
            (new Solicitacao())->criar($_POST);
            $this->avisar('Pedido enviado ao administrador. Você acompanha a resposta em "Pedidos".');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
            if (!$voltarOs) {
                $this->redirecionar('pedidos', array_filter(['tipo' => $this->post('tipo'), 'os' => (int) ($_POST['ordem_servico_id'] ?? 0)]) + ['novo' => 1]);
            }
        }
        $voltarOs ? $this->redirecionar('servico', ['id' => $voltarOs, 'aba' => 'pedidos']) : $this->redirecionar('pedidos');
    }
}
