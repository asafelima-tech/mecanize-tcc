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
