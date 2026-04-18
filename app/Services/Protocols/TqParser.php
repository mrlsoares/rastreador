<?php

namespace App\Services\Protocols;

/**
 * Stub para o protocolo TQ (Topin/Watch).
 */
class TqParser implements ProtocolParserInterface
{
    public function getName(): string
    {
        return 'TQ';
    }

    public function canParse(string $raw): bool
    {
        // TQ protocolos costumam começar com [ ou de formas específicas ASCII/Binárias
        return str_starts_with($raw, '[') && str_ends_with($raw, ']');
    }

    public function parse(string $raw): ?array
    {
        // TODO: Implementar parsing do protocolo TQ
        return [
            'tipo' => 'desconhecido_tq',
            'raw_data' => $raw
        ];
    }

    public function getResponse(array $dados, string $raw): ?string
    {
        return null;
    }
}
