<?php

namespace App\Services\Protocols;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Implementação do protocolo binário JT808.
 */
class Jt808Parser implements ProtocolParserInterface
{
    public const START_END_FLAG = 0x7E;
    public const ESCAPE_CHAR    = 0x7D;

    public function getName(): string
    {
        return 'JT808';
    }

    public function canParse(string $raw): bool
    {
        // JT808 sempre começa e termina com 0x7E
        return str_starts_with($raw, "\x7E") && str_ends_with($raw, "\x7E");
    }

    public function parse(string $raw): ?array
    {
        $data = $this->unescape($raw);
        if (strlen($data) < 15) return null;

        // Verifica Checksum (XOR de todos os bytes exceto flags)
        if (!$this->verifyChecksum($data)) {
            Log::warning("[Jt808Parser] Checksum inválido", ['raw' => bin2hex($raw)]);
            return null;
        }

        $msgId = unpack('n', substr($data, 1, 2))[1];
        $attr  = unpack('n', substr($data, 3, 2))[1];
        $terminalId = bin2hex(substr($data, 5, 6)); // Terminal ID é BCD
        $seq   = unpack('n', substr($data, 11, 2))[1];
        
        $bodyLength = $attr & 0x03FF;
        $body = substr($data, 13, $bodyLength);

        Log::info("[Jt808Parser] Mensagem recebida", [
            'id' => dechex($msgId),
            'imei' => $terminalId,
            'seq' => $seq,
            'len' => $bodyLength
        ]);

        switch ($msgId) {
            case 0x0100: // Registro de Terminal
                return $this->handleRegistration($terminalId, $seq, $body, $raw);
            case 0x0102: // Autenticação de Terminal
                return $this->handleAuthentication($terminalId, $seq, $raw);
            case 0x0002: // Heartbeat
                return $this->handleHeartbeat($terminalId, $seq, $raw);
            case 0x0200: // Reporte de Localização
                return $this->handleLocation($terminalId, $seq, $body, $raw);
            default:
                return [
                    'tipo' => 'desconhecido',
                    'imei' => $terminalId,
                    'msg_id' => dechex($msgId),
                    'raw_data' => bin2hex($raw),
                    'response' => $this->buildAck($msgId, $terminalId, $seq)
                ];
        }
    }

    public function getResponse(array $dados, string $raw): ?string
    {
        return $dados['response'] ?? null;
    }

    private function handleRegistration(string $imei, int $seq, string $body, string $raw): array
    {
        // Resposta de Registro (0x8100)
        // Body: 2 bytes Seq + 1 byte Result (0=Sucesso) + String AuthCode
        $authCode = "888888";
        $respBody = pack('nC', $seq, 0) . $authCode;

        return [
            'tipo' => 'login',
            'imei' => $imei,
            'raw_data' => bin2hex($raw),
            'response' => $this->buildPacket(0x8100, $imei, $respBody)
        ];
    }

    private function handleAuthentication(string $imei, int $seq, string $raw): array
    {
        return [
            'tipo' => 'login',
            'imei' => $imei,
            'raw_data' => bin2hex($raw),
            'response' => $this->buildAck(0x0102, $imei, $seq)
        ];
    }

    private function handleHeartbeat(string $imei, int $seq, string $raw): array
    {
        return [
            'tipo' => 'heartbeat',
            'imei' => $imei,
            'raw_data' => bin2hex($raw),
            'response' => $this->buildAck(0x0002, $imei, $seq)
        ];
    }

    private function handleLocation(string $imei, int $seq, string $body, string $raw): ?array
    {
        if (strlen($body) < 28) return null;

        $alarm  = unpack('N', substr($body, 0, 4))[1];
        $status = unpack('N', substr($body, 4, 4))[1];
        
        $latRaw = unpack('N', substr($body, 8, 4))[1];
        $lonRaw = unpack('N', substr($body, 12, 4))[1];
        
        $latitude  = $latRaw / 1000000;
        $longitude = $lonRaw / 1000000;

        Log::info("[Jt808Parser] Coordenadas Brutas", [
            'status_hex' => dechex($status),
            'lat_bruta'  => $latitude,
            'lon_bruta'  => $longitude,
            'bit_S'      => ($status & 0x04) ? 'Sim' : 'Não',
            'bit_W'      => ($status & 0x08) ? 'Sim' : 'Não'
        ]);

        // Hemisférios em JT808 (Status field)
        // Bit 2: 0=N, 1=S
        // Bit 3: 0=E, 1=W
        if ($status & 0x04) $latitude = -$latitude;
        if ($status & 0x08) $longitude = -$longitude;
        
        $alt    = unpack('n', substr($body, 16, 2))[1];
        $vel    = unpack('n', substr($body, 18, 2))[1] / 10;
        $course = unpack('n', substr($body, 20, 2))[1];
        
        $dtRaw  = bin2hex(substr($body, 22, 6)); // BCD YYMMDDHHMMSS
        
        try {
            $dataHora = Carbon::createFromFormat('ymdHis', $dtRaw);
        } catch (\Exception $e) {
            $dataHora = now();
        }

        $evento = null;
        $descricao = null;
        
        // SOS bit no Alarm field (Bit 0 em JT808)
        if ($alarm & 0x01) {
            $evento = 'SOS';
            $descricao = 'Botão de pânico JT808';
        }

        // ACC bit no Status field (Bit 0 em JT808)
        $ignicao = ($status & 0x01) ? '0001' : '0002';

        return [
            'tipo'              => $evento ? 'alerta' : 'localizacao',
            'evento_tipo'       => $evento,
            'evento_descricao'  => $descricao,
            'imei'              => $imei,
            'data_hora'         => $dataHora,
            'latitude'          => round($latitude, 6),
            'longitude'         => round($longitude, 6),
            'velocidade'        => $vel,
            'angulo'            => $course,
            'sinal_gps'         => 5,
            'evento_codigo'     => $ignicao,
            'raw_data'          => bin2hex($raw),
            'response'          => $this->buildAck(0x0200, $imei, $seq)
        ];
    }

    private function unescape(string $raw): string
    {
        $res = '';
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $b = ord($raw[$i]);
            if ($b === self::ESCAPE_CHAR) {
                $next = ord($raw[++$i]);
                if ($next === 0x02) $res .= chr(0x7E);
                elseif ($next === 0x01) $res .= chr(0x7D);
            } else {
                $res .= chr($b);
            }
        }
        return $res;
    }

    private function verifyChecksum(string $data): bool
    {
        $len = strlen($data);
        $expected = ord($data[$len - 2]);
        $calculated = 0;
        for ($i = 1; $i < $len - 2; $i++) {
            $calculated ^= ord($data[$i]);
        }
        return $calculated === $expected;
    }

    private function buildAck(int $msgId, string $imei, int $seq): string
    {
        // Resposta Comum (0x8001)
        // Body: 2 bytes Seq + 2 bytes MsgID + 1 byte Result (0=Sucesso)
        $body = pack('nnC', $seq, $msgId, 0);
        return $this->buildPacket(0x8001, $imei, $body);
    }

    private function buildPacket(int $msgId, string $imei, string $body): string
    {
        $attr = strlen($body);
        $header = pack('nn', $msgId, $attr) . hex2bin(str_pad($imei, 12, '0', STR_PAD_LEFT)) . pack('n', 0);
        
        $data = $header . $body;
        $checksum = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $checksum ^= ord($data[$i]);
        }
        
        $packet = $data . chr($checksum);
        
        // Escape
        $escaped = '';
        for ($i = 0; $i < strlen($packet); $i++) {
            $b = ord($packet[$i]);
            if ($b === 0x7E) {
                $escaped .= chr(0x7D) . chr(0x02);
            } elseif ($b === 0x7D) {
                $escaped .= chr(0x7D) . chr(0x01);
            } else {
                $escaped .= chr($b);
            }
        }
        
        return chr(0x7E) . $escaped . chr(0x7E);
    }
}
