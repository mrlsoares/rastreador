<?php

namespace App\Services\Protocols;

/**
 * Stub para o protocolo JT808 (Padrão Automotivo Chinês).
 */
class Jt808Parser implements ProtocolParserInterface
{
    public function getName(): string
    {
        return 'JT808';
    }

    public function canParse(string $raw): bool
    {
        // JT808 frames começam e terminam com 0x7E (~)
        return str_starts_with($raw, '~') && str_ends_with($raw, '~');
    }

    public function parse(string $raw): ?array
    {
        // TODO: Implementar lógica de desescape e parsing de mensagens JT808
        return [
            'tipo' => 'desconhecido_jt808',
            'raw_data' => bin2hex($raw)
        ];
    }

    public function getResponse(array $dados, string $raw): ?string
    {
        // TODO: Implementar resposta padrão JT808 (PlatResp)
        return null;
    }
}
