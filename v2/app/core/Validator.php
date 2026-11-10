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
     * Valida telefone brasileiro: DDD real + celular (9 dígitos, começando
     * com 9) OU fixo (8 dígitos) — o próprio input da tela (masks.js) aceita
     * as duas máscaras, então o validador não pode recusar uma das duas.
     * Campo opcional — só valida se algo foi preenchido; para exigir
     * preenchimento, combine com required() antes.
     * Não confirma que o número tem WhatsApp de fato (isso exigiria API
     * paga/não-oficial) — só barra números impossíveis ou claramente falsos
     * (DDD inexistente, quantidade errada de dígitos, todos os dígitos iguais).
     * Um celular (9 dígitos) continua sendo o formato preferido pro link de
     * WhatsApp, mas um fixo é um contato válido e não pode ser bloqueado.
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

        $dddValido    = in_array($ddd, self::DDDS_VALIDOS, true);
        $naoRepetido  = $local !== '' && !preg_match('/^(\d)\1+$/', $local);

        $ehCelular = strlen($digitos) === 11 && ($local[0] ?? '') === '9';
        $ehFixo    = strlen($digitos) === 10;

        $valido = $dddValido && $naoRepetido && ($ehCelular || $ehFixo);

        if (!$valido) {
            $this->erro($campo, ($rotulo ?? $campo) . ' precisa ser um telefone válido, com DDD (ex: (18) 99999-8888 ou (18) 3222-4455).');
        }

        return $this;
    }

    /**
     * Valida CPF (11 dígitos) ou CNPJ (14 dígitos) pelo algoritmo oficial dos
     * dígitos verificadores — detecta automaticamente qual dos dois é pelo
     * tamanho. Campo opcional — só valida se algo foi preenchido; para
     * exigir preenchimento, combine com required() antes.
     */
    public function cpfCnpj(string $campo, ?string $rotulo = null): static
    {
        $valor = trim((string) $this->valor($campo));
        if ($valor === '') {
            return $this;
        }

        $digitos = preg_replace('/\D+/', '', $valor);

        $valido = match (strlen($digitos)) {
            11      => self::validarCpf($digitos),
            14      => self::validarCnpj($digitos),
            default => false,
        };

        if (!$valido) {
            $this->erro($campo, ($rotulo ?? $campo) . ' inválido.');
        }

        return $this;
    }

    /** Algoritmo oficial dos dígitos verificadores do CPF (módulo 11). */
    private static function validarCpf(string $cpf): bool
    {
        // Todos os dígitos iguais (ex.: 111.111.111-11) passariam no cálculo
        // do módulo 11, mas nunca são CPFs válidos de verdade.
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += (int) $cpf[$i] * (10 - $i);
        }
        $resto = $soma % 11;
        $d1    = $resto < 2 ? 0 : 11 - $resto;

        if ($d1 !== (int) $cpf[9]) {
            return false;
        }

        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += (int) $cpf[$i] * (11 - $i);
        }
        $resto = $soma % 11;
        $d2    = $resto < 2 ? 0 : 11 - $resto;

        return $d2 === (int) $cpf[10];
    }

    /** Algoritmo oficial dos dígitos verificadores do CNPJ (módulo 11). */
    private static function validarCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma   = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma += (int) $cnpj[$i] * $pesos1[$i];
        }
        $resto = $soma % 11;
        $d1    = $resto < 2 ? 0 : 11 - $resto;

        if ($d1 !== (int) $cnpj[12]) {
            return false;
        }

        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma   = 0;
        for ($i = 0; $i < 13; $i++) {
            $soma += (int) $cnpj[$i] * $pesos2[$i];
        }
        $resto = $soma % 11;
        $d2    = $resto < 2 ? 0 : 11 - $resto;

        return $d2 === (int) $cnpj[13];
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
