<?php

namespace App\Services\Protocols;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Implementação do protocolo binário GT06 (Concox/Accurate).
 */
class Gt06Parser implements ProtocolParserInterface
{
    public const START_BIT = "\x78\x78";
    
    public const PROTO_LOGIN      = 0x01;
    public const PROTO_LOCATION   = 0x12;
    public const PROTO_STATUS     = 0x13;
    public const PROTO_ALARM      = 0x16;

    public function getName(): string
    {
        return 'GT06';
    }

    public function canParse(string $raw): bool
    {
        return str_starts_with($raw, self::START_BIT);
    }

    public function parse(string $raw): ?array
    {
        if (strlen($raw) < 10) return null;

        $length = ord($raw[2]);
        $protocolId = ord($raw[3]);
        
        $content = substr($raw, 4, $length - 5);
        
        switch ($protocolId) {
            case self::PROTO_LOGIN:
                return $this->parseLogin($content, $raw);
            case self::PROTO_LOCATION:
                return $this->parseLocation($content, $raw);
            case self::PROTO_ALARM:
                return $this->parseAlarm($content, $raw);
            case self::PROTO_STATUS:
                return $this->parseStatus($content, $raw);
            default:
                return [
                    'tipo' => 'desconhecido',
                    'protocol_id' => $protocolId,
                    'raw_data' => bin2hex($raw)
                ];
        }
    }

    public function getResponse(array $dados, string $raw): ?string
    {
        if (isset($dados['response'])) {
            return $dados['response'];
        }
        return null;
    }

    private function parseLogin(string $content, string $raw): array
    {
        $imeiRaw = substr($content, 0, 8);
        $fullImei = self::bcdToText($imeiRaw);
        
        // Unifica o IMEI: Mantém sufixo de 10 e aplica prefixo 86802
        $suffix = substr($fullImei, -10);
        $imei = '86802' . $suffix;

        return [
            'tipo' => 'login',
            'imei' => $imei,
            'raw_data' => bin2hex($raw),
            'response' => $this->buildResponse(self::PROTO_LOGIN, $raw)
        ];
    }

    private function parseLocation(string $content, string $raw): ?array
    {
        if (strlen($content) < 18) return null;

        $dt = unpack('C6', substr($content, 0, 6));
        $dataHora = Carbon::create(2000 + $dt[1], $dt[2], $dt[3], $dt[4], $dt[5], $dt[6], 'UTC')
                          ->setTimezone('America/Sao_Paulo');

        $gpsInfo = unpack('Nlat/Nlon/Cvel/ncourse', substr($content, 7, 11));
        
        $latitude = $gpsInfo['lat'] / 1800000;
        $longitude = $gpsInfo['lon'] / 1800000;
        
        $courseStatus = $gpsInfo['course'];
        
        // Hemisférios em GT06 (Bits 10 e 11 da palavra Course/Status)
        // Bit 10 (0x0400): 0 = Sul, 1 = Norte (negate if 0)
        // Bit 11 (0x0800): 0 = Leste, 1 = Oeste (negate if 1)
        if (!($courseStatus & 0x0400)) $latitude = -$latitude;
        if ($courseStatus & 0x0800) $longitude = -$longitude;

        // ACC/Ignição: Bit 1 (0x02) do byte 17 (byte baixo do course)
        $statusByte = ord($content[17]);
        $ignicao = ($statusByte & 0x02) ? '0001' : '0002';

        $data = [
            'tipo' => 'localizacao',
            'data_hora' => $dataHora,
            'latitude' => round($latitude, 6),
            'longitude' => round($longitude, 6),
            'velocidade' => $gpsInfo['vel'],
            'angulo' => $gpsInfo['course'] & 0x3FF,
            'sinal_gps' => 5, // Valor fixo se incerto, ou decodificar sats
            'evento_codigo' => $ignicao,
            'raw_data' => bin2hex($raw)
        ];

        // Detecção de SOS no pacote de localização (Bits 3-5: Status do Alarme (001: SOS))
        $statusBits = ($statusByte >> 3) & 0x07;
        if ($statusBits === 0x01) {
            $data['evento_tipo'] = 'SOS';
            $data['em_panico']   = true;
            $data['evento_descricao'] = 'Botão de pânico acionado';
        }
        else
        {
           $data['evento_tipo'] = 'SOS';
           $data['em_panico']   = false;
           $data['evento_descricao'] = 'Botão de pânico não acionado';   
        }
        Log::info("[Gt06Parser] Pacote de Localização recebido", [
            'data_hora' => $dataHora,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'velocidade' => $gpsInfo['vel'],
            'angulo' => $gpsInfo['course'] & 0x3FF,
            'sinal_gps' => 5,
            'evento_codigo' => $ignicao,
            'raw_data' => bin2hex($raw)
            'statusByte' => $statusByte,
            'statusBits' => $statusBits,
            'ignicao' => $ignicao,
            'panico' => $panico,
            'evento_tipo' => $evento,
            'evento_descricao' => $descricao
        ]); 

        return $data;
    }

    private function parseAlarm(string $content, string $raw): ?array
    {
        $data = $this->parseLocation($content, $raw);
        if (!$data) return null;

        // No pacote 0x16, o byte de tipo de alarme é crucial.
        // Ele costuma vir após o LBS. Se LBS tem 0 bytes, ele vem logo após o GPS Info.
        // GPS Info termina no byte 18 do conteúdo (0-17).
        // Byte 18: LBS Length
        // Byte 19: Alarm Type (se LBS Len for 0)
        // Muitos trackers usam GPS(18) + LBS(8) + Alarme(1)
        // Tentamos o offset 26 do conteúdo (Raw 30) se o cálculo de LBS falhar
        $lbsLen = ord($content[18] ?? "\0");
        $alarmTypeOffset = 19 + $lbsLen;
        $alarmByte = ord($content[$alarmTypeOffset] ?? $content[26] ?? "\0");
        $alarmHex  = dechex($alarmByte);

        Log::info("[Gt06Parser] Pacote de Alarme recebido", [
            'raw' => bin2hex($raw),
            'alarm_byte' => dechex($alarmByte),
            'offset' => $alarmTypeOffset
        ]);

        $evento = null;
        $descricao = null;

        switch ($alarmByte) {
            case 0x01:
                $evento = 'PANICO';
                $descricao = 'Botão de Pânico acionado';
                break;
            case 0x02:
                $evento = 'CORTE_ENERGIA';
                $descricao = 'Alimentação externa cortada';
                break;
            case 0x03:
                $evento = 'VIOLACAO';
                $descricao = 'Alarme de vibração/choque';
                break;
            case 0x00:
                // Alarme Restituidor / Fim de Alarme
                $data['em_panico'] = false;
                return $data; 
        }

        if ($evento) {
            $data['tipo'] = 'alarme';
            $data['evento_tipo'] = $evento;
            $data['evento_descricao'] = $descricao;
        }

        $data['response'] = self::buildResponse(self::PROTO_ALARM, $raw);

        return $data;
    }

    private function parseStatus(string $content, string $raw): array
    {
        $infoByte = ord($content[0]);
        $alarmBits = ($infoByte >> 3) & 0x07; // Bits 3, 4, 5
        
        // SOS é o tipo 001
        $panico = ($alarmBits === 0x01);
        
        Log::info("[Gt06Parser] Status recebido", [
            'info_hex' => dechex($infoByte),
            'alarm_val' => $alarmBits,
            'panico' => $panico
        ]);

        return [
            'tipo' => 'status',
            'em_panico' => $panico,
            'raw_data' => bin2hex($raw),
            'response' => $this->buildResponse(self::PROTO_STATUS, $raw)
        ];
    }

    private static function bcdToText(string $bcd): string
    {
        $res = '';
        for ($i = 0; $i < strlen($bcd); $i++) {
            $res .= sprintf('%02x', ord($bcd[$i]));
        }
        return ltrim($res, '0');
    }

    private function buildResponse(int $protocolId, string $raw): string
    {
        $serial = substr($raw, -6, 2);
        $respBase = "\x05" . chr($protocolId) . $serial;
        $crc = self::crc16($respBase);
        
        return self::START_BIT . $respBase . pack('n', $crc) . "\x0D\x0A";
    }

    public static function crc16(string $data): int
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc <<= 1;
                }
            }
        }
        return ~$crc & 0xFFFF;
    }
}
