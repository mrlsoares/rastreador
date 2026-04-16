<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Parser do protocolo do rastreador TRX-16 (Arqia).
 *
 * Formato padrão esperado (texto delimitado por vírgula):
 * #IMEI,DD/MM/AAAA,HH:MM:SS,LAT,LON,VEL,ANGULO,SINAL,EVENTOS\r\n
 *
 * Exemplo:
 * #358899001234567,11/03/2025,14:30:00,-23.5489,-46.6388,60,90,5,0000
 *
 * O campo EVENTOS é um inteiro em decimal que representa flags de bits:
 * Bit 0 (0001): Ignição ON
 * Bit 1 (0002): Ignição OFF
 * Bit 2 (0004): Bateria Baixa
 * Bit 3 (0008): Violação
 * Bit 4 (0016): Botão de Pânico
 * Bit 5 (0032): Excesso de Velocidade
 * Bit 6 (0064): Entrada em Cerca
 * Bit 7 (0128): Saída de Cerca
 */
class TrxParser
{
    /**
     * Parseia uma linha de dados bruta recebida do TRX-16.
     *
     * @return array|null Dados parseados ou null se o formato for inválido
     */
    public static function parse(string $raw): ?array
    {
        // Limpa caracteres de controle e espaços
        $linha = trim($raw, " \r\n\0");

        if (empty($linha)) {
            return null;
        }

        // Tratamento de Heartbeat (keep-alive) comum como "xx"
        if (strtolower($linha) === 'xx') {
            return [
                'tipo'     => 'heartbeat',
                'raw_data' => $raw,
            ];
        }

        // Suporte aos dois formatos mais comuns do TRX-16:
        // Formato 1 (com #): #IMEI,data,hora,lat,lon,...
        // Formato 2 (sem #): IMEI,data,hora,lat,lon,...
        $linha = ltrim($linha, '#');

        $partes = explode(',', $linha);

        // Valida número mínimo de campos
        if (count($partes) < 8) {
            Log::warning('[TrxParser] Frame inválido (campos insuficientes)', [
                'raw' => $raw,
                'campos' => count($partes),
            ]);
            return null;
        }

        [$imei, $data, $hora, $lat, $lon, $vel, $angulo, $sinal] = $partes;
        $eventoCodigo = $partes[8] ?? '0000';

        // Valida IMEI (15 dígitos)
        if (!preg_match('/^\d{15}$/', trim($imei))) {
            Log::warning('[TrxParser] IMEI inválido', ['imei' => $imei, 'raw' => $raw]);
            return null;
        }

        // Valida e converte latitude/longitude
        $latitude  = (float) str_replace(',', '.', $lat);
        $longitude = (float) str_replace(',', '.', $lon);

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            Log::warning('[TrxParser] Coordenadas inválidas', compact('latitude', 'longitude'));
            return null;
        }

        // Parseia data/hora — tenta dois formatos (DD/MM/YYYY e YYYY-MM-DD)
        try {
            if (str_contains($data, '/')) {
                $dataHora = Carbon::createFromFormat('d/m/Y H:i:s', "$data $hora");
            } else {
                $dataHora = Carbon::createFromFormat('Y-m-d H:i:s', "$data $hora");
            }
        } catch (\Exception $e) {
            Log::warning('[TrxParser] Data/hora inválida', compact('data', 'hora'));
            $dataHora = Carbon::now();
        }

        return [
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

    /**
     * Decodifica o campo de eventos do TRX-16 (flags de bits).
     * Retorna array com os tipos de eventos detectados.
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
