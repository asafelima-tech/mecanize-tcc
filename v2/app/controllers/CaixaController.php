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
            'movimentos' => $caixa ? $m->movimentos((int) $caixa['id']) : [],
            'sugestao' => $m->ultimoFechamento(),
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
            $r = (new Caixa())->fechar($this->post('contado'), $this->post('observacao'));
            $dif = $r['diferenca'];
            $this->avisar('Caixa fechado. ' . (abs($dif) < 0.005 ? 'Conferido sem diferença.' : ($dif > 0 ? 'Sobra de ' : 'Falta de ') . dinheiro(abs($dif)) . ' na gaveta.'),
                abs($dif) < 0.005 ? 'sucesso' : 'aviso');
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
        $this->tela('caixa/ver', ['caixa' => $caixa, 'resumo' => $m->resumo((int) $caixa['id']), 'movimentos' => $m->movimentos((int) $caixa['id'])]);
    }
}
