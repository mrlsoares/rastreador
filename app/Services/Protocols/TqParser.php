<?php

namespace App\Services\Protocols;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Parser para o protocolo TQ (Topin/Watch/H02).
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
        
        // Baseado nos logs: $ <ID:5 bytes> <Time:3> <Date:3> <Lat:4> <Lon:4> ...
        // raw_hex: 24 (20 31 85 95 28) (18 23 20) (18 04 26) (29 10 80 18) (06) (05 10 87 86)...
        
        // ID (5 bytes após "$"): 20 31 85 95 28 -> "2031859528"
        $idBin = substr($raw, 1, 5);
        $idPartial = bin2hex($idBin); 
        
        // Reconstrói o IMEI completo conforme informado pelo usuário
        $imei = (strlen($idPartial) === 10) ? '86802' . $idPartial : $idPartial;

        $horaBin = substr($raw, 6, 3);
        $hora = bin2hex($horaBin);
        
        $dataBin = substr($raw, 9, 3);
        $data = bin2hex($dataBin);

        try {
            $dataHora = Carbon::createFromFormat('Hisdmy', $hora . $data);
        } catch (\Exception $e) {
            $dataHora = now();
        }

        // Parsing de Coordenadas BCD (NMEA DDMM.MMMM)
        // Lat: 29108018 -> 29 deg, 10.8018 min
        $latRaw = bin2hex(substr($raw, 12, 4)); 
        // Lon: 05108786 -> 051 deg, 08.786 min (offset 17, 4 bytes)
        $lonRaw = bin2hex(substr($raw, 17, 4)); 

        $latitude = $this->parseBcdCoordinate($latRaw, 2);
        $longitude = $this->parseBcdCoordinate($lonRaw, 3);

        // Detecção de Alerta no frame Binário
        $alertaBin = ord(substr($raw, 32, 1));
        $evento = null;
        $descricao = null;
        if ($alertaBin === 0x01 || $alertaBin === 0x02) {
            $evento = 'SOS';
            $descricao = 'Botão de pânico acionado';
        }

        Log::info("[TqParser] Packet Binary", [
            'imei'   => $imei,
            'alerta' => $alertaBin,
            'raw'    => $hex
        ]);

        // Hemisfério (Brasil é sempre S e W no caso deste usuário)
        if ($latitude > 0) $latitude = -$latitude;
        if ($longitude > 0) $longitude = -$longitude;

        return [
            'tipo'              => $evento ? 'alerta' : 'localizacao',
            'evento_tipo'       => $evento,
            'evento_descricao'  => $descricao,
            'imei'              => $imei,
            'data_hora'         => $dataHora,
            'latitude'          => $latitude,
            'longitude'         => $longitude,
            'velocidade'        => 0,
            'sinal_gps'         => 5,
            'raw_data'          => $hex,
        ];
    }

    private function parseBcdCoordinate(string $bcd, int $digitosGraus): ?float
    {
        // Ex: Lat: "29108018", 2 -> 29 + (10.8018 / 60)
        // Ex: Lon: "05108786", 3 -> 051 + (08.786 / 60)
        
        if (strlen($bcd) < $digitosGraus) return null;

        $graus = (int) substr($bcd, 0, $digitosGraus);
        $minutosRaw = substr($bcd, $digitosGraus);
        
        // Em NMEA, os minutos sempre têm 2 dígitos inteiros (MM.MMMM)
        // Então dividimos pelo número de casas decimais (comprimento - 2)
        $divisorMinutos = pow(10, strlen($minutosRaw) - 2);
        $minutos = (float) $minutosRaw / $divisorMinutos;

        $decimal = $graus + ($minutos / 60);

        return round($decimal, 6);
    }

    private function parseH02(string $linha, string $raw): ?array
    {
        $partes = explode(',', trim($linha, '#'));
        
        if (count($partes) < 12) {
            return null;
        }

        $idPartial = $partes[1];
        
        // Reconstrói o IMEI completo conforme informado pelo usuário
        $imei = (strlen($idPartial) === 10) ? '86802' . $idPartial : $idPartial;

        $tipoPacket = $partes[2]; 
        $hora = $partes[3];
        $valid = $partes[4]; 
        $lat  = $partes[5];
        $ns   = $partes[6];
        $lon  = $partes[7];
        $ew   = $partes[8];
        $vel  = (float) $partes[9];
        $course = (float) $partes[10];
        $data = $partes[11];
        $status = $partes[12] ?? 'N/A';

        Log::info("[TqParser] Packet H02 ASCII", [
            'tipo'   => $tipoPacket,
            'status' => $status,
            'imei'   => $imei
        ]);

        if ($valid !== 'A' && $valid !== 'V') return null;

        $evento = null;
        $descricao = null;
        if ($tipoPacket === 'V1' || $tipoPacket === 'V2' || $tipoPacket === 'EX') {
            $evento = 'SOS';
            $descricao = 'Alerta de pânico comunicado por pacote ASCII';
        }

        try {
            $dataHora = Carbon::createFromFormat('dmyHis', $data . $hora);
        } catch (\Exception $e) {
            $dataHora = now();
        }

        return [
            'tipo'              => $evento ? 'alerta' : 'localizacao',
            'evento_tipo'       => $evento,
            'evento_descricao'  => $descricao,
            'imei'              => $imei,
            'data_hora'         => $dataHora,
            'latitude'          => $this->convertNmeaToDecimal($lat, $ns),
            'longitude'         => $this->convertNmeaToDecimal($lon, $ew),
            'velocidade'        => round($vel * 1.852, 2),
            'angulo'            => (int) $course,
            'sinal_gps'         => ($valid === 'A') ? 5 : 0,
            'raw_data'          => $linha,
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
        return null;
    }
}
