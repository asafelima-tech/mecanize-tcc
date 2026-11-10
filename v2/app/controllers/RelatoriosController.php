<?php

/** Relatórios do ADMIN por período. */
final class RelatoriosController extends Controller
{
    public const ATALHOS = ['mes' => 'Este mês', 'mes-passado' => 'Mês passado', '30' => 'Últimos 30 dias', 'ano' => 'Este ano'];

    public function painel(): void
    {
        [$ini, $fim, $atalho] = $this->periodo();
        $r = new Relatorio($ini, $fim);

        $this->tela('relatorios/painel', [
            'ini' => $ini, 'fim' => $fim, 'atalho' => $atalho,
            'faturamento' => $r->faturamento(),
            'tempo'       => $r->faturamentoNoTempo(),
            'ordens'      => $r->ordens(),
            'caixa'       => $r->caixa(),
            'contas'      => $r->contasEmAberto(),
            'itens'       => $r->itensMaisVendidos(),
            'tecnicos'    => $r->tecnicos(),
            'clientes'    => $r->clientes(),
            'estoque'     => $r->estoque(),
            'agenda'      => $r->agenda(),
        ]);
    }

    /** [início, fim, atalho]. Datas inclusivas; período de no máximo 3 anos. */
    private function periodo(): array
    {
        $hoje = new DateTimeImmutable('today');
        $atalho = (string) ($_GET['p'] ?? '');
        $data = function (string $campo): ?DateTimeImmutable {
            $v = (string) ($_GET[$campo] ?? '');
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);

            return $d && $d->format('Y-m-d') === $v ? $d : null;
        };

        if ($atalho === '' && ($i = $data('ini')) && ($f = $data('fim'))) {
            if ($f < $i) {
                [$i, $f] = [$f, $i];
            }
            if ($i->diff($f)->days > 366 * 3) {
                $i = $f->modify('-3 years');
            }

            return [$i->format('Y-m-d'), $f->format('Y-m-d'), ''];
        }

        switch ($atalho) {
            case 'mes-passado':
                $i = $hoje->modify('first day of last month');
                return [$i->format('Y-m-d'), $i->modify('last day of this month')->format('Y-m-d'), $atalho];
            case '30':
                return [$hoje->modify('-29 days')->format('Y-m-d'), $hoje->format('Y-m-d'), $atalho];
            case 'ano':
                return [$hoje->format('Y') . '-01-01', $hoje->format('Y-m-d'), $atalho];
            default:
                return [$hoje->modify('first day of this month')->format('Y-m-d'), $hoje->format('Y-m-d'), 'mes'];
        }
    }
}
