<?php

namespace App\Services\Protocols;

/**
 * Gerenciador de protocolos — padrão Strategy com injeção de dependência.
 *
 * Conforme OCP (SOLID): adicionar um novo protocolo requer apenas registrar
 * a nova implementação de ProtocolParserInterface no AppServiceProvider,
 * sem modificar esta classe.
 *
 * @see App\Providers\AppServiceProvider::register()
 */
class ProtocolManager
{
    /** @param ProtocolParserInterface[] $parsers */
    public function __construct(
        protected readonly array $parsers = []
    ) {}

    /**
     * Encontra o parser adequado para os dados recebidos.
     * Retorna null se nenhum protocolo reconhecer o pacote.
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

    /**
     * Retorna os nomes de todos os protocolos registrados (útil para diagnóstico).
     *
     * @return string[]
     */
    public function getRegisteredProtocols(): array
    {
        return array_map(fn($p) => $p->getName(), $this->parsers);
    }
}
