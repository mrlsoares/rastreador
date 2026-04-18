<?php

namespace App\Services\Protocols;

/**
 * Interface comum para todos os parsers de protocolo de rastreador.
 */
interface ProtocolParserInterface
{
    /**
     * Retorna o nome amigável do protocolo.
     */
    public function getName(): string;

    /**
     * Verifica se o frame de dados brutos pertence a este protocolo.
     */
    public function canParse(string $raw): bool;

    /**
     * Realiza o parse do frame e retorna um array padronizado ou null.
     */
    public function parse(string $raw): ?array;

    /**
     * Gera a resposta (ACK) que deve ser enviada de volta ao rastreador.
     */
    public function getResponse(array $dados, string $raw): ?string;
}
