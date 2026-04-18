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
        // TQ protocolos (Topin/ZW) costumam começar com [ ou $ or *
        return str_starts_with($raw, '[') || str_starts_with($raw, '$') || str_starts_with($raw, '*');
    }

    public function parse(string $raw): ?array
    {
        $hex = bin2hex($raw);

        // Tentativa de extrair IMEI de frames conhecidos de TQ
        // Se o frame tiver um tamanho específico ou padrão BCD
        $imei = null;
        
        // Exemplo: $ [IMEI:15] ... ou $$ [IMEI:8_bytes_BCD]
        if (str_starts_with($raw, '$ ')) {
             // Provável formato: $ <tipo> ( <dados> )
             // Precisamos de mais frames para mapear os campos
        }

        return [
            'tipo' => 'desconhecido_tq',
            'imei' => $imei,
            'raw_data' => $hex
        ];
    }

    public function getResponse(array $dados, string $raw): ?string
    {
        return null;
    }
}
