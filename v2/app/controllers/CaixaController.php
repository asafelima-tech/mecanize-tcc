<?php

/** Caixa: a equipe abre, movimenta e fecha; o histórico das sessões é do ADMIN. */
final class CaixaController extends Controller
{
    public function atual(): void
    {
        $m = new Caixa();
        $caixa = $m->aberto();
        $this->tela('caixa/atual', [
            'caixa' => $caixa,
            'resumo' => $caixa ? $m->resumo((int) $caixa['id']) : null,
            'fechamento' => $caixa ? $m->fechamento((int) $caixa['id']) : null,
            'movimentos' => $caixa ? $m->movimentos((int) $caixa['id']) : [],
            'anterior' => $caixa ? null : $m->ultimoFechado(),
            // Últimas sessões no pé da tela (o histórico completo continua só para o ADMIN).
            'sessoes' => Contexto::perfil() === 'ADMIN' ? array_slice($m->historico(new Paginacao(1)), 0, 5) : [],
        ]);
    }

    public function abrir(): void
    {
        try {
            (new Caixa())->abrir($this->post('valor'), $this->post('observacao'));
            $this->avisar('Caixa aberto.');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('caixa');
    }

    public function movimentar(): void
    {
        try {
            (new Caixa())->movimentar((string) ($_POST['natureza'] ?? ''), $this->post('valor'), $this->post('descricao'), $this->post('chave'));
            $this->avisar(($_POST['natureza'] ?? '') === 'SANGRIA' ? 'Sangria registrada.' : 'Suprimento registrado.');
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('caixa');
    }

    public function fechar(): void
    {
        try {
            $r = (new Caixa())->fechar($this->post('observacao'));
            $this->avisar('Caixa fechado com saldo final de ' . dinheiro($r['saldo']) . '. Ficam ' . dinheiro($r['dinheiro']) . ' em dinheiro na gaveta para o próximo caixa.');
            if (Contexto::perfil() === 'ADMIN') {
                $this->redirecionar('caixa-ver', ['id' => $r['id']]);
            }
        } catch (DomainException $e) {
            $this->avisar($e->getMessage(), 'erro');
        }
        $this->redirecionar('caixa');
    }

    public function historico(): void
    {
        $p = new Paginacao();
        $this->tela('caixa/historico', ['caixas' => (new Caixa())->historico($p), 'p' => $p]);
    }

    public function ver(): void
    {
        $m = new Caixa();
        $caixa = $m->buscar($this->idDaUrl()) ?? throw new ErroHttp('registro-inexistente', ['voltar' => './caixas']);
        $this->tela('caixa/ver', ['caixa' => $caixa, 'fechamento' => $m->fechamento((int) $caixa['id']), 'movimentos' => $m->movimentos((int) $caixa['id'])]);
    }
}
