<?php

namespace App\Console\Commands;

use App\Services\TrackerService;
use App\Services\Protocols\ProtocolManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Listener TCP poliglota seguindo princípios SOLID e Clean Code.
 */
class SocketListen extends Command
{
    protected $signature   = 'socket:listen
                             {--host=0.0.0.0 : Endereço de escuta}
                             {--port=5023    : Porta TCP}';

    protected $description = 'Listener TCP multi-protocolo (TRX-16, GT06, JT808, TQ)';

    public function handle(ProtocolManager $protocolManager, TrackerService $trackerService): int
    {
        $host = env('SOCKET_HOST', $this->option('host'));
        $port = (int) env('SOCKET_PORT', $this->option('port'));

        $this->info("[Socket] Iniciando listener multi-protocolo em {$host}:{$port}");
        Log::info('[Socket] Listener iniciado', ['host' => $host, 'port' => $port]);

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$socket) {
            $erro = socket_strerror(socket_last_error());
            $this->error("[Socket] Falha ao criar socket: {$erro}");
            return Command::FAILURE;
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!socket_bind($socket, $host, $port)) {
            $erro = socket_strerror(socket_last_error($socket));
            $this->error("[Socket] Falha ao fazer bind: {$erro}");
            return Command::FAILURE;
        }

        socket_listen($socket, 10);
        $this->info('[Socket] Aguardando conexões...');

        while (true) {
            $cliente = @socket_accept($socket);

            if ($cliente === false) {
                continue;
            }

            socket_getpeername($cliente, $ip, $porta_cliente);
            socket_set_option($cliente, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 30, 'usec' => 0]);

            try {
                while (true) {
                    $dados = @socket_read($cliente, 4096, PHP_BINARY_READ);

                    if ($dados === false || $dados === '') {
                        break;
                    }

                    // Identifica o protocolo adequado pelo frame
                    $parser = $protocolManager->getParser($dados);

                    if (!$parser) {
                        Log::warning('[Socket] Protocolo não reconhecido', ['ip' => $ip, 'raw' => bin2hex($dados)]);
                        continue;
                    }

                    // Parseia os dados de acordo com o protocolo
                    $dadosFormatados = $parser->parse($dados);

                    if ($dadosFormatados) {
                        // Persiste os dados via Serviço (SRP)
                        $trackerService->handle($parser, $dadosFormatados, $ip);

                        // Envia resposta (ACK) se o protocolo exigir
                        $resposta = $parser->getResponse($dadosFormatados, $dados);
                        if ($resposta) {
                            socket_write($cliente, $resposta);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('[Socket] Erro na conexão', [
                    'ip'    => $ip,
                    'erro'  => $e->getMessage(),
                ]);
            } finally {
                @socket_close($cliente);
                Log::info('[Socket] Conexão encerrada', ['ip' => $ip]);
            }
        }

        socket_close($socket);
        return Command::SUCCESS;
    }
}
