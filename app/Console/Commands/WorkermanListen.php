<?php

namespace App\Console\Commands;

use App\Services\TrackerService;
use App\Services\Protocols\ProtocolManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

/**
 * Listener TCP assíncrono de alta performance usando Workerman.
 * Suporta múltiplos rastreadores com conexões simultâneas sem bloqueio.
 */
class WorkermanListen extends Command
{
    protected $signature = 'workerman:listen
                            {action=start        : Ação: start | stop | restart | status}
                            {--host=0.0.0.0      : Endereço de escuta}
                            {--port=5023         : Porta TCP}
                            {--workers=4         : Número de processos worker}
                            {--daemonize         : Executar em modo daemon (background)}';

    protected $description = 'Listener TCP assíncrono multi-protocolo (Workerman) — preparado para alta concorrência';

    public function handle(ProtocolManager $protocolManager, TrackerService $trackerService): int
    {
        $action   = $this->argument('action');
        $host     = env('SOCKET_HOST', $this->option('host'));
        $port     = (int) env('SOCKET_PORT', $this->option('port'));
        $workers  = (int) env('SOCKET_WORKERS', $this->option('workers'));
        $daemonize = $this->option('daemonize');

        // Configuração do Workerman para rodar dentro do Laravel
        $pidPath = storage_path('framework');
        if (!is_dir($pidPath)) {
            mkdir($pidPath, 0775, true);
        }

        Worker::$pidFile    = $pidPath . '/workerman.pid';
        Worker::$logFile    = storage_path('logs/workerman.log');
        Worker::$statusFile = storage_path('framework/workerman.status');

        // Se estivermos iniciando e o processo não existir de fato, limpa o PID antigo
        if ($action === 'start' && file_exists(Worker::$pidFile)) {
            $pid = file_get_contents(Worker::$pidFile);
            if (!posix_getpgid($pid)) {
                unlink(Worker::$pidFile);
            }
        }

        if ($daemonize) {
            Worker::$daemonize = true;
        }

        // Cria o worker TCP na porta configurada
        $worker = new Worker("tcp://{$host}:{$port}");
        $worker->count = $workers;
        $worker->name  = 'RastreadorSocket';

        // ─── Evento: Nova Conexão ─────────────────────────────────────────────
        $worker->onConnect = function (TcpConnection $connection) {
            $connection->imei   = null; // identificado quando o primeiro pacote chegar
            $connection->buffer = ''; // buffer para pacotes fragmentados

            Log::info('[Workerman] Nova conexão', [
                'ip'            => $connection->getRemoteIp(),
                'connection_id' => $connection->id,
            ]);
        };

        // ─── Evento: Dados Recebidos ──────────────────────────────────────────
        $worker->onMessage = function (TcpConnection $connection, string $data) use ($protocolManager, $trackerService) {
            $ip  = $connection->getRemoteIp();
            $hex = bin2hex($data);
            $tag = $connection->imei ? "[IMEI:{$connection->imei}]" : "[IP:{$ip}]";

            $parser = $protocolManager->getParser($data);

            if (!$parser) {
                Log::warning("[Workerman] {$tag} Protocolo não reconhecido", ['raw_hex' => $hex]);
                return;
            }

            Log::info("[Workerman] {$tag} Pacote recebido [{$parser->getName()}]", [
                'raw_hex' => $hex,
            ]);

            $dadosFormatados = $parser->parse($data);

            if (!$dadosFormatados) {
                return;
            }

            // Salva o IMEI na conexão assim que o protocolo identificar
            if (!$connection->imei && !empty($dadosFormatados['imei'])) {
                $connection->imei = $dadosFormatados['imei'];
                $tag = "[IMEI:{$connection->imei}]";
                Log::info("[Workerman] {$tag} Rastreador identificado", ['ip' => $ip]);
            }

            // Persiste e dispara eventos (Reverb/broadcast)
            try {
                $trackerService->handle($parser, $dadosFormatados, $ip);
            } catch (\Throwable $e) {
                Log::error("[Workerman] {$tag} Erro ao processar dados", [
                    'erro' => $e->getMessage(),
                ]);
            }

            // Envia ACK se o protocolo exigir
            $resposta = $parser->getResponse($dadosFormatados, $data);
            if ($resposta) {
                $connection->send($resposta);
            }
        };

        // ─── Evento: Conexão Encerrada ────────────────────────────────────────
        $worker->onClose = function (TcpConnection $connection) {
            $tag = $connection->imei ? "[IMEI:{$connection->imei}]" : "[IP:{$connection->getRemoteIp()}]";
            Log::info("[Workerman] {$tag} Conexão encerrada");
        };

        // ─── Evento: Erro de Conexão ──────────────────────────────────────────
        $worker->onError = function (TcpConnection $connection, int $code, string $msg) {
            $tag = $connection->imei ? "[IMEI:{$connection->imei}]" : "[IP:{$connection->getRemoteIp()}]";
            Log::error("[Workerman] {$tag} Erro na conexão", [
                'code' => $code,
                'msg'  => $msg,
            ]);
        };

        $this->info("[Workerman] Iniciando listener em {$host}:{$port} com {$workers} workers...");

        // Simula argv para as funções internas do Workerman
        global $argv;
        $argv[0] = 'workerman:listen';
        $argv[1] = $action;

        Worker::runAll();

        return Command::SUCCESS;
    }
}
