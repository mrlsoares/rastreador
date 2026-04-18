<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Parser do protocolo binário GT06 (Concox/Accurate).
 */
class Gt06Parser
{
    public const START_BIT = "\x78\x78";
    
    public const PROTOCOL_LOGIN      = 0x01;
    public const PROTOCOL_LOCATION   = 0x12;
    public const PROTOCOL_STATUS     = 0x13;
    public const PROTOCOL_ALARM      = 0x16;

    /**
     * Parseia um frame binário GT06.
     */
    public static function parse(string $raw): ?array
    {
        if (strlen($raw) < 10) return null;

        // Verifica Start Bits (0x78 0x78)
        if (substr($raw, 0, 2) !== self::START_BIT) {
            return null;
        }

        $length = ord($raw[2]);
        $protocolId = ord($raw[3]);
        
        // O conteúdo começa no byte 4
        $content = substr($raw, 4, $length - 5); // Retira ProtocolID e Serial/CRC
        
        switch ($protocolId) {
            case self::PROTOCOL_LOGIN:
                return self::parseLogin($content, $raw);
            case self::PROTOCOL_LOCATION:
                return self::parseLocation($content, $raw);
            case self::PROTOCOL_ALARM:
                return self::parseAlarm($content, $raw);
            case self::PROTOCOL_STATUS:
                return self::parseStatus($content, $raw);
            default:
                return [
                    'tipo' => 'desconhecido',
                    'protocol_id' => $protocolId,
                    'raw_data' => bin2hex($raw)
                ];
        }
    }

    /**
     * Parseia pacote de Login (ID 0x01)
     */
    private static function parseLogin(string $content, string $raw): array
    {
        // Terminal ID (IMEI) - 8 bytes BCD
        $imeiRaw = substr($content, 0, 8);
        $imei = self::bcdToText($imeiRaw);

        return [
            'tipo' => 'login',
            'imei' => $imei,
            'raw_data' => bin2hex($raw),
            'response' => self::buildResponse(self::PROTOCOL_LOGIN, $raw)
        ];
    }

    /**
     * Parseia pacote de Localização (ID 0x12)
     */
    private static function parseLocation(string $content, string $raw): ?array
    {
        if (strlen($content) < 18) return null;

        // Data/Hora (6 bytes: YY MM DD HH MM SS)
        $dt = unpack('C6', substr($content, 0, 6));
        $dataHora = Carbon::create(2000 + $dt[1], $dt[2], $dt[3], $dt[4], $dt[5], $dt[6]);

        // GPS Info
        $gpsInfo = unpack('Nlat/Nlon/Cvel/ncourse', substr($content, 7, 11));
        
        $latitude = $gpsInfo['lat'] / 1800000;
        $longitude = $gpsInfo['lon'] / 1800000;
        
        // Verifica flags de N/S/E/W no byte de status (byte 11 do GPS info, 18 total)
        $status = ord($content[17]);
        if (!($status & 0x04)) $latitude = -$latitude;
        if ($status & 0x08) $longitude = -$longitude;

        // Ignição (Bit 1 do byte de status do terminal) em alguns modelos
        // Em outros modelos o status do terminal vem em pacote separado (0x13)
        // Por padrão, muitos GT06 enviam status de ignição no byte 17 do frame 0x12
        $ignicao = ($status & 0x02) ? '0001' : '0002'; // 0001 = ON, 0002 = OFF (TRX compat)

        return [
            'tipo' => 'localizacao',
            'data_hora' => $dataHora,
            'latitude' => round($latitude, 6),
            'longitude' => round($longitude, 6),
            'velocidade' => $gpsInfo['vel'],
            'angulo' => $gpsInfo['course'] & 0x3FF, // 10 bits
            'sinal_gps' => ($status & 0x30) >> 4,
            'evento_codigo' => $ignicao,
            'raw_data' => bin2hex($raw)
        ];
    }

    /**
     * Parseia pacote de Alarme/SOS (ID 0x16)
     */
    private static function parseAlarm(string $content, string $raw): ?array
    {
        // Alarme tem estrutura similar à localização + campos de alarme
        $data = self::parseLocation($content, $raw);
        if (!$data) return null;

        // O byte de alarme aparece após o GPS e LBS
        // Simplificando: buscamos o byte de tipo de alarme que no GT06 costuma ser SOS (0x01)
        // No protocolo oficial, o byte de alarme está em uma posição variável se o LBS for variável,
        // mas para muitos modelos chineses ele vem logo após o GPS Info (byte 18 do conteúdo)
        $alarmByte = ord($content[strlen($content) - 1]); // Frequentemente o último byte antes do serial

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
        $data['response'] = self::buildResponse(self::PROTOCOL_ALARM, $raw);

        return $data;
    }

    /**
     * Parseia pacote de Status/Heartbeat (ID 0x13)
     */
    private static function parseStatus(string $content, string $raw): array
    {
        return [
            'tipo' => 'heartbeat',
            'raw_data' => bin2hex($raw),
            'response' => self::buildResponse(self::PROTOCOL_STATUS, $raw)
        ];
    }

    /**
     * Converte BCD para Texto (para IMEI)
     */
    private static function bcdToText(string $bcd): string
    {
        $res = '';
        for ($i = 0; $i < strlen($bcd); $i++) {
            $res .= sprintf('%02x', ord($bcd[$i]));
        }
        return ltrim($res, '0');
    }

    /**
     * Constrói resposta para o rastreador.
     * Estrutura: 0x78 0x78 0x05 PROTOCOL SERIAL CRC 0x0D 0x0A
     */
    public static function buildResponse(int $protocolId, string $raw): string
    {
        $serial = substr($raw, -6, 2);
        $respBase = "\x05" . chr($protocolId) . $serial;
        $crc = self::crc16($respBase);
        
        return self::START_BIT . $respBase . pack('n', $crc) . "\x0D\x0A";
    }

    /**
     * Cálculo CRC-ITU (X16 + X12 + X5 + 1)
     */
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
