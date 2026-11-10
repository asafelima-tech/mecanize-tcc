<?php

/** Catálogo GLOBAL de marcas e modelos (o mesmo para todas as oficinas, só leitura). */
final class ModeloVeiculo extends Modelo
{
    public function marcas(): array
    {
        return array_column($this->listaGlobal('SELECT DISTINCT marca FROM modelos_veiculos ORDER BY marca'), 'marca');
    }

    public function daMarca(string $marca): array
    {
        return $this->listaGlobal('SELECT id, nome, combustiveis FROM modelos_veiculos WHERE marca = :marca ORDER BY nome', ['marca' => $marca]);
    }

    public function existe(int $id): bool
    {
        return $this->linhaGlobal('SELECT id FROM modelos_veiculos WHERE id = :id', ['id' => $id]) !== null;
    }
}
