<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Models\Posicao;
use App\Models\Rastreador;
use App\Services\TrxParser;
use App\Services\Gt06Parser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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

            // Tempo limite de leitura (para não travar o servidor em uma única conexão morta)
            socket_set_option($cliente, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 30, 'usec' => 0]);

            $isHeartbeat = false;

            try {
                // Loop para manter a conexão aberta e processar múltiplos pacotes do mesmo rastreador
                while (true) {
                    $dados = @socket_read($cliente, 4096, PHP_BINARY_READ);

                    if ($dados === false || $dados === '') {
                        break;
                    }

                    $isGt06 = str_starts_with($dados, Gt06Parser::START_BIT);
                    $raw = $isGt06 ? $dados : trim($dados);
                    
                    $isHeartbeat = false;
                    if (!$isGt06) {
                        $isHeartbeat = strtolower($raw) === 'xx';
                    }
                    
                    if (!$isHeartbeat) {
                        $this->line("[Socket] Dados de {$ip}:" . ($isGt06 ? bin2hex($raw) : $raw));
                        Log::info('[Socket] Nova mensagem', [
                            'ip'        => $ip,
                            'protocolo' => $isGt06 ? 'GT06' : 'TRX-16',
                            'raw'       => $isGt06 ? bin2hex($raw) : $raw,
                        ]);
                    }

                    $resposta = $this->processarDados($raw, $ip, $isGt06);
                    
                    if ($resposta) {
                        socket_write($cliente, $resposta);
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

    /**
     * Parseia e persiste os dados recebidos no banco.
     * Retorna a string de resposta para o rastreador.
     */
    private function processarDados(string $raw, string $ip = '', bool $isGt06 = false): ?string
    {
        $dados = $isGt06 ? Gt06Parser::parse($raw) : TrxParser::parse($raw);

        if (!$dados) {
            $displayRaw = $isGt06 ? bin2hex($raw) : $raw;
            $this->warn("[Socket] Frame inválido de {$ip}: {$displayRaw}");
            Log::warning('[Socket] Frame ignorado — parse retornou nulo', [
                'ip'  => $ip,
                'raw' => $displayRaw,
            ]);
            return $isGt06 ? null : "ERR\r\n";
        }

        // Log detalhado de todos os campos recebidos
        Log::info('[Socket] Frame parseado', array_merge([
            'ip'        => $ip,
            'protocolo' => $isGt06 ? 'GT06' : 'TRX-16',
        ], $dados));

        // Resposta padrão
        $resposta = $isGt06 ? ($dados['response'] ?? null) : "OK\r\n";

        // Verifica se é tipo Heartbeat
        if (isset($dados['tipo']) && $dados['tipo'] === 'heartbeat') {
            return $resposta;
        }

        // No GT06, pacotes de login servem apenas para parear IMEI com IP/Conexão
        if (isset($dados['tipo']) && $dados['tipo'] === 'login') {
            Cache::put("tracker_imei_{$ip}", $dados['imei'], 3600); // Salva por 1 hora
            $this->info("[Socket] Login GT06 recebido → IMEI: {$dados['imei']}");
            return $resposta;
        }

        // Se for localização mas não tiver IMEI (comum no GT06)
        if (!isset($dados['imei']) && $isGt06) {
            $dados['imei'] = Cache::get("tracker_imei_{$ip}");
            
            if (!$dados['imei']) {
                $this->warn("[Socket] Localização GT06 sem IMEI identificável de {$ip}");
                return $resposta;
            }
        }

        Log::info('[Socket] Frame processado com sucesso', [
            'ip'            => $ip,
            'imei'          => $dados['imei'],
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

            // Processa eventos (flags de bit para TRX ou campos diretos para GT06)
            $eventosParaSalvar = [];

            if ($isGt06) {
                // Eventos específicos de alarme (SOS, etc)
                if (isset($dados['evento_tipo'])) {
                    $eventosParaSalvar[] = [
                        'tipo' => $dados['evento_tipo'],
                        'descricao' => $dados['evento_descricao'],
                    ];
                }
                
                // Evento de ignição embutido na localização
                if (isset($dados['evento_codigo']) && $dados['evento_codigo'] !== '0000') {
                    $eventosParaSalvar = array_merge($eventosParaSalvar, TrxParser::decodeEventos($dados['evento_codigo']));
                }
            } else {
                // TRX-16 usa o campo evento_codigo padrão
                if ($dados['evento_codigo'] !== '0000') {
                    $eventosParaSalvar = TrxParser::decodeEventos($dados['evento_codigo']);
                }
            }

            foreach ($eventosParaSalvar as $evento) {
                Evento::create([
                    'rastreador_id' => $rastreador->id,
                    'posicao_id'    => $posicao->id,
                    'tipo'          => $evento['tipo'],
                    'descricao'     => $evento['descricao'],
                    'codigo_raw'    => $dados['evento_codigo'] ?? '0000',
                ]);

                $this->warn("[Socket] Evento: {$evento['tipo']} — {$evento['descricao']}");
                Log::warning('[Socket] Evento detectado', [
                    'imei'       => $dados['imei'],
                    'posicao_id' => $posicao->id,
                    'tipo'       => $evento['tipo'],
                    'descricao'  => $evento['descricao'],
                    'codigo_raw' => $dados['evento_codigo'] ?? '0000',
                ]);
            }
        });

        return $resposta;
    }
}
