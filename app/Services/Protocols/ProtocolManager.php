<?php

namespace App\Services\Protocols;

use Illuminate\Support\Facades\Log;

/**
 * Gerenciador de protocolos para selecionar o parser correto.
 */
class ProtocolManager
{
    protected array $parsers = [];

    public function __construct()
    {
        // Registra os protocolos suportados
        $this->parsers[] = new Gt06Parser();
        $this->parsers[] = new TrxParser();
        $this->parsers[] = new Jt808Parser();
        $this->parsers[] = new TqParser();
    }

    /**
     * Encontra o parser adequado para os dados recebidos.
     */
    public function getParser(string $raw): ?ProtocolParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->canParse($raw)) {
                return $parser;
            }
        }

        return null;
    }
}
