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
        $imei = self::bcdToText($imeiRaw);

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
        $dataHora = Carbon::create(2000 + $dt[1], $dt[2], $dt[3], $dt[4], $dt[5], $dt[6]);

        $gpsInfo = unpack('Nlat/Nlon/Cvel/ncourse', substr($content, 7, 11));
        
        $latitude = $gpsInfo['lat'] / 1800000;
        $longitude = $gpsInfo['lon'] / 1800000;
        
        $status = ord($content[17]);
        if (!($status & 0x04)) $latitude = -$latitude;
        if ($status & 0x08) $longitude = -$longitude;

        $ignicao = ($status & 0x02) ? '0001' : '0002';

        return [
            'tipo' => 'localizacao',
            'data_hora' => $dataHora,
            'latitude' => round($latitude, 6),
            'longitude' => round($longitude, 6),
            'velocidade' => $gpsInfo['vel'],
            'angulo' => $gpsInfo['course'] & 0x3FF,
            'sinal_gps' => ($status & 0x30) >> 4,
            'evento_codigo' => $ignicao,
            'raw_data' => bin2hex($raw)
        ];
    }

    private function parseAlarm(string $content, string $raw): ?array
    {
        $data = $this->parseLocation($content, $raw);
        if (!$data) return null;

        $alarmByte = ord($content[strlen($content) - 1]);

        $evento = 'ALARM_DESCONHECIDO';
        $descricao = 'Alarme desconhecido';

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
        }

        $data['tipo'] = 'alarme';
        $data['evento_tipo'] = $evento;
        $data['evento_descricao'] = $descricao;
        $data['response'] = $this->buildResponse(self::PROTO_ALARM, $raw);

        return $data;
    }

    private function parseStatus(string $content, string $raw): array
    {
        return [
            'tipo' => 'heartbeat',
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
