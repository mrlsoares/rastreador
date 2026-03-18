<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Models\Posicao;
use App\Models\Rastreador;
use App\Services\TrxParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SocketListen extends Command
{
    protected $signature   = 'socket:listen
                             {--host=0.0.0.0 : Endereço de escuta}
                             {--port=5023    : Porta TCP}';

    protected $description = 'Listener TCP para receber dados do rastreador TRX-16 (Arqia)';

    public function handle(): int
    {
        $host = env('SOCKET_HOST', $this->option('host'));
        $port = (int) env('SOCKET_PORT', $this->option('port'));

        $this->info("[Socket] Iniciando listener em {$host}:{$port}");
        Log::info('[Socket] Listener iniciado', ['host' => $host, 'port' => $port]);

        // Cria socket TCP
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$socket) {
            $erro = socket_strerror(socket_last_error());
            $this->error("[Socket] Falha ao criar socket: {$erro}");
            Log::error('[Socket] Falha ao criar socket', ['erro' => $erro]);
            return Command::FAILURE;
        }

        // Permite reutilizar a porta após restart
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!socket_bind($socket, $host, $port)) {
            $erro = socket_strerror(socket_last_error($socket));
            $this->error("[Socket] Falha ao fazer bind: {$erro}");
            Log::error('[Socket] Falha ao fazer bind', ['host' => $host, 'port' => $port, 'erro' => $erro]);
            return Command::FAILURE;
        }

        socket_listen($socket, 10); // Aceita até 10 conexões na fila
        $this->info('[Socket] Aguardando conexões...');
        Log::info('[Socket] Aguardando conexões', ['host' => $host, 'port' => $port]);

        while (true) {
            // Aceita nova conexão (bloqueante)
            $cliente = @socket_accept($socket);

            if ($cliente === false) {
                $erro = socket_strerror(socket_last_error($socket));
                Log::warning('[Socket] Erro ao aceitar conexão', ['erro' => $erro]);
                sleep(1);
                continue;
            }

            // Obtém IP do cliente
            socket_getpeername($cliente, $ip, $porta_cliente);
            $this->line("[Socket] Nova conexão de: {$ip}:{$porta_cliente}");
            Log::info('[Socket] Nova conexão recebida', [
                'ip'            => $ip,
                'porta_cliente' => $porta_cliente,
            ]);

            // Lê dados enviados pelo dispositivo (buffer de 4096 bytes)
            $dados = socket_read($cliente, 4096, PHP_NORMAL_READ);

            if ($dados === false || trim($dados) === '') {
                $this->warn("[Socket] Conexão vazia de {$ip} — ignorada.");
                Log::warning('[Socket] Dados vazios ou leitura falhou', [
                    'ip'    => $ip,
                    'dados' => $dados,
                ]);
                socket_close($cliente);
                continue;
            }

            $raw = trim($dados);
            $this->line("[Socket] Dados recebidos de {$ip}: {$raw}");
            Log::info('[Socket] Frame recebido', [
                'ip'          => $ip,
                'raw'         => $raw,
                'bytes'       => strlen($raw),
                'recebido_em' => now()->toDateTimeString(),
            ]);

            try {
                $this->processarDados($raw, $ip);
                socket_write($cliente, "OK\r\n");
                Log::info('[Socket] Resposta OK enviada', ['ip' => $ip]);
            } catch (\Throwable $e) {
                Log::error('[Socket] Erro ao processar frame', [
                    'ip'    => $ip,
                    'raw'   => $raw,
                    'erro'  => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                socket_write($cliente, "ERR\r\n");
            } finally {
                socket_close($cliente);
                Log::info('[Socket] Conexão encerrada', ['ip' => $ip]);
            }
        }

        socket_close($socket);
        return Command::SUCCESS;
    }

    /**
     * Parseia e persiste os dados recebidos no banco.
     */
    private function processarDados(string $raw, string $ip = ''): void
    {
        $dados = TrxParser::parse($raw);

        if (!$dados) {
            $this->warn("[Socket] Frame inválido de {$ip}: {$raw}");
            Log::warning('[Socket] Frame ignorado — parse retornou nulo', [
                'ip'  => $ip,
                'raw' => $raw,
            ]);
            return;
        }

        Log::info('[Socket] Frame parseado com sucesso', [
            'ip'            => $ip,
            'imei'          => $dados['imei'],
            'data_hora'     => $dados['data_hora']->toDateTimeString(),
            'latitude'      => $dados['latitude'],
            'longitude'     => $dados['longitude'],
            'velocidade'    => $dados['velocidade'],
            'angulo'        => $dados['angulo'],
            'sinal_gps'     => $dados['sinal_gps'],
            'evento_codigo' => $dados['evento_codigo'],
        ]);

        DB::transaction(function () use ($dados, $ip) {
            // Busca o rastreador ou cria automaticamente pelo IMEI
            $rastreador = Rastreador::firstOrCreate(
                ['imei' => $dados['imei']],
                [
                    'nome'  => 'TRX-16 ' . substr($dados['imei'], -4),
                    'ativo' => true,
                ]
            );

            if ($rastreador->wasRecentlyCreated) {
                Log::info('[Socket] Novo rastreador cadastrado automaticamente', [
                    'imei' => $dados['imei'],
                    'nome' => $rastreador->nome,
                ]);
            }

            // Atualiza o último contato
            $rastreador->update(['ultimo_contato' => now()]);

            // Cria a posição
            $posicao = Posicao::create([
                'rastreador_id' => $rastreador->id,
                'data_hora'     => $dados['data_hora'],
                'latitude'      => $dados['latitude'],
                'longitude'     => $dados['longitude'],
                'velocidade'    => $dados['velocidade'],
                'angulo'        => $dados['angulo'],
                'sinal_gps'     => $dados['sinal_gps'],
                'raw_data'      => $dados['raw_data'],
            ]);

            Log::info('[Socket] Posição salva', [
                'posicao_id'    => $posicao->id,
                'rastreador_id' => $rastreador->id,
                'imei'          => $dados['imei'],
                'latitude'      => $dados['latitude'],
                'longitude'     => $dados['longitude'],
                'velocidade'    => $dados['velocidade'] . ' km/h',
            ]);

            $this->info("[Socket] Posição salva → IMEI: {$dados['imei']} | {$dados['latitude']},{$dados['longitude']} | {$dados['velocidade']}km/h");

            // Processa eventos (flags de bit)
            if ($dados['evento_codigo'] !== '0000') {
                $eventos = TrxParser::decodeEventos($dados['evento_codigo']);

                foreach ($eventos as $evento) {
                    Evento::create([
                        'rastreador_id' => $rastreador->id,
                        'posicao_id'    => $posicao->id,
                        'tipo'          => $evento['tipo'],
                        'descricao'     => $evento['descricao'],
                        'codigo_raw'    => $dados['evento_codigo'],
                    ]);

                    $this->warn("[Socket] Evento: {$evento['tipo']} — {$evento['descricao']}");
                    Log::warning('[Socket] Evento detectado', [
                        'imei'       => $dados['imei'],
                        'posicao_id' => $posicao->id,
                        'tipo'       => $evento['tipo'],
                        'descricao'  => $evento['descricao'],
                        'codigo_raw' => $dados['evento_codigo'],
                    ]);
                }
            }
        });
    }
}
