<?php

/**
 * Validação genérica de arrays (tipicamente $_POST), reaproveitável por
 * qualquer controller. Métodos encadeáveis; cada um só adiciona um erro se
 * a regra falhar (sem sobrescrever erros já registrados para o campo).
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    private function valor(string $campo)
    {
        return $this->data[$campo] ?? null;
    }

    private function erro(string $campo, string $mensagem): void
    {
        if (!isset($this->errors[$campo])) {
            $this->errors[$campo] = $mensagem;
        }
    }

    public function required(string $campo, ?string $rotulo = null): static
    {
        $valor = $this->valor($campo);

        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            $this->erro($campo, ($rotulo ?? $campo) . ' é obrigatório.');
        }

        return $this;
    }

    public function email(string $campo, ?string $rotulo = null): static
    {
        $valor = trim((string) $this->valor($campo));

        if ($valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $this->erro($campo, ($rotulo ?? $campo) . ' deve ser um e-mail válido.');
        }

        return $this;
    }

    public function numeric(string $campo, ?string $rotulo = null): static
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && !is_numeric($valor)) {
            $this->erro($campo, ($rotulo ?? $campo) . ' deve ser um número.');
        }

        return $this;
    }

    public function min(string $campo, int $tamanho, ?string $rotulo = null): static
    {
        $valor = (string) ($this->valor($campo) ?? '');

        if ($valor !== '' && mb_strlen($valor) < $tamanho) {
            $this->erro($campo, ($rotulo ?? $campo) . " deve ter pelo menos {$tamanho} caracteres.");
        }

        return $this;
    }

    public function max(string $campo, int $tamanho, ?string $rotulo = null): static
    {
        $valor = (string) ($this->valor($campo) ?? '');

        if ($valor !== '' && mb_strlen($valor) > $tamanho) {
            $this->erro($campo, ($rotulo ?? $campo) . " deve ter no máximo {$tamanho} caracteres.");
        }

        return $this;
    }

    public function in(string $campo, array $opcoes, ?string $rotulo = null): static
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && !in_array($valor, $opcoes, true)) {
            $this->erro($campo, ($rotulo ?? $campo) . ' contém um valor inválido.');
        }

        return $this;
    }

    public function matches(string $campo, string $outroCampo, ?string $rotulo = null): static
    {
        if ($this->valor($campo) !== $this->valor($outroCampo)) {
            $this->erro($campo, ($rotulo ?? $campo) . ' não coincide.');
        }

        return $this;
    }

    /** DDDs realmente atribuídos pela Anatel — usado por celularBr() abaixo. */
    private const DDDS_VALIDOS = [
        11, 12, 13, 14, 15, 16, 17, 18, 19,
        21, 22, 24, 27, 28,
        31, 32, 33, 34, 35, 37, 38,
        41, 42, 43, 44, 45, 46, 47, 48, 49,
        51, 53, 54, 55,
        61, 62, 63, 64, 65, 66, 67, 68, 69,
        71, 73, 74, 75, 77, 79,
        81, 82, 83, 84, 85, 86, 87, 88, 89,
        91, 92, 93, 94, 95, 96, 97, 98, 99,
    ];

    /**
     * Valida celular brasileiro (formato exigido pro link de WhatsApp
     * funcionar de verdade: DDD real + 9 dígitos, começando com 9).
     * Campo opcional — só valida se algo foi preenchido; para exigir
     * preenchimento, combine com required() antes.
     * Não confirma que o número tem WhatsApp de fato (isso exigiria API
     * paga/não-oficial) — só barra números impossíveis ou claramente falsos
     * (DDD inexistente, quantidade errada de dígitos, todos os dígitos iguais).
     */
    public function celularBr(string $campo, ?string $rotulo = null): static
    {
        $valor = trim((string) $this->valor($campo));
        if ($valor === '') {
            return $this;
        }

        $digitos = preg_replace('/\D+/', '', $valor);
        $ddd     = (int) substr($digitos, 0, 2);
        $local   = substr($digitos, 2);

        $valido = strlen($digitos) === 11
            && in_array($ddd, self::DDDS_VALIDOS, true)
            && ($local[0] ?? '') === '9'
            && !preg_match('/^(\d)\1+$/', $local);

        if (!$valido) {
            $this->erro($campo, ($rotulo ?? $campo) . ' precisa ser um celular válido, com DDD (ex: (18) 99999-8888).');
        }

        return $this;
    }

    /**
     * Regra livre — usada, por exemplo, para checar duplicidade reaproveitando
     * métodos que já existem nos models (User::emailExiste, Cliente::emailExisteAtivo).
     * $fn recebe o valor do campo e deve retornar true quando o valor é VÁLIDO.
     */
    public function custom(string $campo, callable $fn, string $mensagem): static
    {
        if (!$fn($this->valor($campo))) {
            $this->erro($campo, $mensagem);
        }

        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : reset($this->errors);
    }
}
