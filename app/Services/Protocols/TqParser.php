<?php

namespace App\Services\Protocols;

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
        if (str_starts_with($linha, '$')) {
             return [
                 'tipo' => 'tq_binario',
                 'raw_data' => bin2hex($raw)
             ];
        }

        return null;
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
