<?php

require_once __DIR__ . '/../core/Dinheiro.php';

/**
 * Cartões corporativos da oficina e o calendário das suas faturas.
 *
 * O sistema NÃO guarda número, validade nem código do cartão — só o apelido, o emissor, a
 * bandeira, os 4 últimos dígitos (opcionais, para a equipe reconhecer o cartão) e o que importa
 * para o controle: limite, dia de fechamento e dia de vencimento. Também não há conexão
 * bancária: compras e pagamentos são registrados à mão.
 *
 * Regra do calendário (documentada, porque cada emissor tem a sua):
 *   - a compra feita ATÉ o dia de fechamento entra na fatura que fecha naquele mês; depois dele,
 *     na do mês seguinte;
 *   - a fatura vence no dia de vencimento: no mesmo mês do fechamento se o dia de vencimento é
 *     maior que o de fechamento, senão no mês seguinte;
 *   - a parcela k de uma compra parcelada cai na fatura k−1 meses depois da primeira.
 * Dias de fechamento/vencimento vão de 1 a 28, para existirem em todo mês.
 *
 * Limite comprometido = soma das parcelas ainda NÃO pagas das compras no cartão (inclusive as
 * futuras): é o que continua ocupando o limite até a fatura ser paga.
 */
class CartaoCorporativo
{
    public const BANDEIRAS = ['Visa', 'Mastercard', 'Elo', 'American Express', 'Hipercard', 'Outra'];

    private PDO $conn;
    private string $ultimoErro = '';

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? new Database();
    }

    public function getLastError(): string
    {
        return $this->ultimoErro;
    }

    /* ── Cadastro ────────────────────────────────────────────────────────── */

    /** @return int|false */
    public function create(array $d)
    {
        $campos = $this->validar($d, null);

        if ($campos === null) {
            return false;
        }

        $this->conn->prepare("
            INSERT INTO cartoes_corporativos (apelido, instituicao, bandeira, final_cartao, limite_total, dia_fechamento, dia_vencimento)
            VALUES (:apelido, :instituicao, :bandeira, :final, :limite, :fecha, :vence)
        ")->execute($campos);

        return (int) $this->conn->lastInsertId();
    }

    public function update(int $id, array $d): bool
    {
        $campos = $this->validar($d, $id);

        if ($campos === null) {
            return false;
        }

        $campos[':id'] = $id;

        $this->conn->prepare("
            UPDATE cartoes_corporativos
            SET apelido = :apelido, instituicao = :instituicao, bandeira = :bandeira, final_cartao = :final,
                limite_total = :limite, dia_fechamento = :fecha, dia_vencimento = :vence
            WHERE id_cartao = :id
        ")->execute($campos);

        return true;
    }

    public function definirAtivo(int $id, bool $ativo): bool
    {
        return $this->conn->prepare('UPDATE cartoes_corporativos SET ativo = ? WHERE id_cartao = ?')->execute([$ativo ? 1 : 0, $id]);
    }

    private function validar(array $d, ?int $idAtual): ?array
    {
        $this->ultimoErro = '';

        $apelido = trim((string) ($d['apelido'] ?? ''));
        $inst    = trim((string) ($d['instituicao'] ?? ''));

        if (mb_strlen($apelido) < 2) {
            $this->ultimoErro = 'Dê um apelido ao cartão (ex.: "Cartão da oficina").';
            return null;
        }

        if (mb_strlen($inst) < 2) {
            $this->ultimoErro = 'Informe o banco/instituição emissora.';
            return null;
        }

        $fecha = (int) ($d['dia_fechamento'] ?? 0);
        $vence = (int) ($d['dia_vencimento'] ?? 0);

        if ($fecha < 1 || $fecha > 28 || $vence < 1 || $vence > 28) {
            $this->ultimoErro = 'Os dias de fechamento e vencimento precisam estar entre 1 e 28.';
            return null;
        }

        $final = preg_replace('/\D+/', '', (string) ($d['final_cartao'] ?? ''));
        if ($final !== '' && strlen($final) !== 4) {
            $this->ultimoErro = 'Informe somente os 4 últimos dígitos do cartão (ou deixe em branco).';
            return null;
        }

        $limiteTxt = trim((string) ($d['limite_total'] ?? ''));
        $limite = $limiteTxt === '' ? null : Dinheiro::parse($limiteTxt);

        if ($limite !== null && $limite < 0) {
            $this->ultimoErro = 'O limite não pode ser negativo.';
            return null;
        }

        $dup = $this->conn->prepare('SELECT 1 FROM cartoes_corporativos WHERE apelido = ? AND id_cartao <> ?');
        $dup->execute([$apelido, $idAtual ?? 0]);

        if ($dup->fetchColumn()) {
            $this->ultimoErro = 'Já existe um cartão com este apelido.';
            return null;
        }

        $bandeira = trim((string) ($d['bandeira'] ?? ''));

        return [
            ':apelido'     => mb_substr($apelido, 0, 80),
            ':instituicao' => mb_substr($inst, 0, 80),
            ':bandeira'    => in_array($bandeira, self::BANDEIRAS, true) ? $bandeira : null,
            ':final'       => $final !== '' ? $final : null,
            ':limite'      => $limite,
            ':fecha'       => $fecha,
            ':vence'       => $vence,
        ];
    }

    /* ── Consulta ────────────────────────────────────────────────────────── */

    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare($this->selectBase() . ' WHERE c.id_cartao = ?');
        $stmt->execute([$id]);

        return $this->completar($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    public function listar(bool $incluirInativos = false): array
    {
        $rows = $this->conn->query($this->selectBase() . ($incluirInativos ? '' : ' WHERE c.ativo = 1') . ' ORDER BY c.ativo DESC, c.apelido')->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($r) => $this->completar($r), $rows);
    }

    private function selectBase(): string
    {
        return "
            SELECT c.*,
                   COALESCE((SELECT SUM(p.valor) FROM contas_pagar_parcelas p JOIN despesas d ON d.id_despesa = p.despesa_id
                              WHERE d.cartao_id = c.id_cartao AND d.status = 'ATIVA' AND p.status = 'PENDENTE'), 0) AS comprometido
            FROM cartoes_corporativos c
        ";
    }

    private function completar(?array $c): ?array
    {
        if ($c === null) {
            return null;
        }

        $c['comprometido'] = round((float) $c['comprometido'], 2);
        $c['limite_disponivel'] = $c['limite_total'] !== null ? round((float) $c['limite_total'] - $c['comprometido'], 2) : null;
        $c['limite_excedido'] = $c['limite_disponivel'] !== null && $c['limite_disponivel'] < 0;

        return $c;
    }

    /* ── Calendário das faturas ──────────────────────────────────────────── */

    /**
     * Datas da fatura em que cai uma compra (ou a parcela $deslocamento meses depois dela).
     *
     * @return array{referencia:string, fecha_em:string, vence_em:string}
     */
    public static function datasDaFatura(int $diaFechamento, int $diaVencimento, string $dataCompra, int $deslocamentoMeses = 0): array
    {
        $compra = new DateTimeImmutable($dataCompra);

        $mesesAFrente = ((int) $compra->format('j') > $diaFechamento ? 1 : 0) + $deslocamentoMeses;

        $primeiroDoMes = $compra->modify('first day of this month')->modify("+{$mesesAFrente} months");
        $fecha = $primeiroDoMes->setDate((int) $primeiroDoMes->format('Y'), (int) $primeiroDoMes->format('n'), $diaFechamento);

        $mesVenc = $diaVencimento > $diaFechamento ? $primeiroDoMes : $primeiroDoMes->modify('+1 month');
        $vence = $mesVenc->setDate((int) $mesVenc->format('Y'), (int) $mesVenc->format('n'), $diaVencimento);

        return [
            'referencia' => $primeiroDoMes->format('Y-m-d'),
            'fecha_em'   => $fecha->format('Y-m-d'),
            'vence_em'   => $vence->format('Y-m-d'),
        ];
    }

    /**
     * Fatura do cartão que recebe a parcela. Cria a fatura do mês se ainda não existe e, se a
     * fatura-alvo já foi paga, empurra para a primeira seguinte em aberto (uma compra nunca entra
     * numa fatura já quitada). Roda dentro da transação de quem chama.
     */
    public static function faturaParaParcela(PDO $pdo, array $cartao, string $dataCompra, int $deslocamentoMeses): array
    {
        for ($tentativa = 0; $tentativa < 24; $tentativa++) {
            $datas = self::datasDaFatura((int) $cartao['dia_fechamento'], (int) $cartao['dia_vencimento'], $dataCompra, $deslocamentoMeses + $tentativa);

            $pdo->prepare('INSERT IGNORE INTO faturas_cartao (cartao_id, referencia, fecha_em, vence_em) VALUES (?, ?, ?, ?)')
                ->execute([(int) $cartao['id_cartao'], $datas['referencia'], $datas['fecha_em'], $datas['vence_em']]);

            $stmt = $pdo->prepare('SELECT * FROM faturas_cartao WHERE cartao_id = ? AND referencia = ? FOR UPDATE');
            $stmt->execute([(int) $cartao['id_cartao'], $datas['referencia']]);
            $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fatura && $fatura['paga_em'] === null) {
                return $fatura;
            }
        }

        throw new DomainException('Não foi possível encontrar uma fatura em aberto para esta compra.');
    }
}
