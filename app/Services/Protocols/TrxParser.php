<?php

namespace App\Services\Protocols;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Parser do protocolo do rastreador TRX-16 (Arqia/Datora).
 * Formato ASCII CSV.
 */
class TrxParser implements ProtocolParserInterface
{
    public function getName(): string
    {
        return 'TRX-16';
    }

    public function canParse(string $raw): bool
    {
        $trim = trim($raw);
        // TRX-16 é ASCII/CSV. Deve ser UTF-8 válido e conter vírgulas.
        // Além disso, deve começar com # ou um IMEI de 15 dígitos seguido de vírgula.
        return mb_check_encoding($raw, 'UTF-8') 
               && str_contains($trim, ',')
               && (str_starts_with($trim, '#') || preg_match('/^\d{15},/', $trim));
    }

    public function parse(string $raw): ?array
    {
        $linha = trim($raw, " \r\n\0");

        if (empty($linha)) return null;

        if (strtolower($linha) === 'xx') {
            return [
                'tipo'     => 'heartbeat',
                'raw_data' => $raw,
            ];
        }

        $linha = ltrim($linha, '#');
        $partes = explode(',', $linha);

        if (count($partes) < 8) {
            Log::warning('[TrxParser] Frame inválido (campos insuficientes)', [
                'raw' => $raw,
                'campos' => count($partes),
            ]);
            return null;
        }

        [$imei, $data, $hora, $lat, $lon, $vel, $angulo, $sinal] = $partes;
        $eventoCodigo = $partes[8] ?? '0000';

        if (!preg_match('/^\d{15}$/', trim($imei))) {
            return null;
        }

        $latitude  = (float) str_replace(',', '.', $lat);
        $longitude = (float) str_replace(',', '.', $lon);

        try {
            if (str_contains($data, '/')) {
                $dataHora = Carbon::createFromFormat('d/m/Y H:i:s', "$data $hora");
            } else {
                $dataHora = Carbon::createFromFormat('Y-m-d H:i:s', "$data $hora");
            }
        } catch (\Exception $e) {
            $dataHora = Carbon::now();
        }

        return [
            'tipo'          => 'localizacao',
            'imei'          => trim($imei),
            'data_hora'     => $dataHora,
            'latitude'      => $latitude,
            'longitude'     => $longitude,
            'velocidade'    => (int) $vel,
            'angulo'        => (int) $angulo,
            'sinal_gps'     => (int) $sinal,
            'evento_codigo' => trim($eventoCodigo),
            'raw_data'      => $raw,
        ];
    }

    public function getResponse(array $dados, string $raw): ?string
    {
        return "OK\r\n";
    }

    /**
     * Decodifica o campo de eventos do TRX-16 (flags de bits).
     */
    public static function decodeEventos(string $codigo): array
    {
        $valor     = (int) $codigo;
        $eventos   = [];
        $mapeamento = \App\Models\Evento::TIPOS;

        foreach ($mapeamento as $bit => $info) {
            if ($valor & (int) $bit) {
                $eventos[] = $info;
            }
        }

        return $eventos;
    }
}
