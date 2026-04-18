<?php

namespace App\Services\Protocols;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Stub para o protocolo TQ (Topin/Watch).
 */
class TqParser implements ProtocolParserInterface
{
    public function getName(): string
    {
        return 'TQ';
    }

    public function canParse(string $raw): bool
    {
        // TQ protocolos (Topin/ZW) costumam começar com [ ou $ or *
        return str_starts_with($raw, '[') || str_starts_with($raw, '$') || str_starts_with($raw, '*');
    }

    public function parse(string $raw): ?array
    {
        $linha = trim($raw, " \r\n\0");
        if (empty($linha)) return null;

        // Suporte ao formato H02 (Topin/Tq): *HQ,ID,V6,TIME,A/V,LAT,N/S,LON,W/E,SPEED,COURSE,DATE,STATUS,#
        if (str_starts_with($linha, '*')) {
            return $this->parseH02($linha, $raw);
        }

        // Suporte ao formato $: $ tipo ( dados )
        if (str_starts_with($raw, '$ ')) {
             return $this->parseTqBinary($raw);
        }

        return null;
    }

    private function parseTqBinary(string $raw): ?array
    {
        $hex = bin2hex($raw);
        
        // Baseado nos logs: $ <ID:4 bytes> <Time:3> <Date:3> <Lat:4> <Lon:4> ...
        // raw_hex: 24 20 31 85 95 28 18 15 46 18 04 26 29 10 80 18 06 05 10 87 86 ...
        
        // ID (4 bytes após "$ "): 31 85 95 28
        // Time (3 bytes): 18 15 46
        // Date (3 bytes): 18 04 26
        // Lat (4 bytes): 29 10 80 18
        // Lon (4 bytes): 06 05 10 87 86... (pera, lon costuma ser maior)
        
        $idBin = substr($raw, 2, 4);
        $id = bin2hex($idBin); // "31859528"
        
        $horaBin = substr($raw, 6, 3);
        $hora = bin2hex($horaBin); // "181546"
        
        $dataBin = substr($raw, 9, 3);
        $data = bin2hex($dataBin); // "180426"

        try {
            $dataHora = Carbon::createFromFormat('Hisdmy', $hora . $data);
        } catch (\Exception $e) {
            $dataHora = now();
        }

        // Parsing de Coordenadas BCD (Topin style)
        $latRaw = bin2hex(substr($raw, 12, 4)); // "29108018"
        $lonRaw = bin2hex(substr($raw, 16, 5)); // "0605108786" -> 051.08786?

        $latitude = $this->parseBcdCoordinate($latRaw);
        $longitude = $this->parseBcdCoordinate($lonRaw);

        // Hemisfério (Brasil é sempre S e W)
        if ($latitude > 0) $latitude = -$latitude;
        if ($longitude > 0) $longitude = -$longitude;

        return [
            'tipo'          => 'localizacao',
            'imei'          => $id,
            'data_hora'     => $dataHora,
            'latitude'      => $latitude,
            'longitude'     => $longitude,
            'velocidade'    => 0, // A ser mapeado
            'sinal_gps'     => 5,
            'raw_data'      => $hex,
        ];
    }

    private function parseBcdCoordinate(string $bcd): ?float
    {
        // 29108018 -> 29.108018
        // 0605108786 -> 060.5108786? Ou 51.08786?
        
        $val = (float) $bcd;
        if (strlen($bcd) == 8) {
            return $val / 1000000;
        }
        if (strlen($bcd) >= 10) {
            return $val / 10000000; 
        }
        return $val;
    }

    private function parseH02(string $linha, string $raw): ?array
    {
        $partes = explode(',', trim($linha, '#'));
        
        if (count($partes) < 12) {
            return null;
        }

        $id   = $partes[1];
        $tipo = $partes[2]; // ex: V6
        $hora = $partes[3];
        $valid = $partes[4]; // A ou V
        $lat  = $partes[5];
        $ns   = $partes[6];
        $lon  = $partes[7];
        $ew   = $partes[8];
        $vel  = (float) $partes[9];
        $course = (float) $partes[10];
        $data = $partes[11];

        if ($valid !== 'A' && $valid !== 'V') return null;

        try {
            $dataHora = Carbon::createFromFormat('dmyHis', $data . $hora);
        } catch (\Exception $e) {
            $dataHora = now();
        }

        return [
            'tipo'          => 'localizacao',
            'imei'          => $id, // TQ/H02 usa o ID diretamente (muitas vezes os últimos 10 dígitos do IMEI)
            'data_hora'     => $dataHora,
            'latitude'      => $this->convertNmeaToDecimal($lat, $ns),
            'longitude'     => $this->convertNmeaToDecimal($lon, $ew),
            'velocidade'    => round($vel * 1.852, 2), // Knots para Km/h
            'angulo'        => (int) $course,
            'sinal_gps'     => ($valid === 'A') ? 5 : 0,
            'raw_data'      => $linha,
        ];
    }

    private function convertNmeaToDecimal(string $nmea, string $direcao): ?float
    {
        if (empty($nmea) || $nmea == '0000.0000') return null;

        $ponto = strpos($nmea, '.');
        if ($ponto === false) return null;

        $graus = (int) substr($nmea, 0, $ponto - 2);
        $minutos = (float) substr($nmea, $ponto - 2);
        $decimal = $graus + ($minutos / 60);

        if ($direcao === 'S' || $direcao === 'W') {
            $decimal = -$decimal;
        }

        return round($decimal, 6);
    }

    public function getResponse(array $dados, string $raw): ?string
    {
        // H02 geralmente não exige resposta para pacotes de localização simples
        return null;
    }
}
