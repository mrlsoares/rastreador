# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel 11 GPS tracking system for the TRX-16 device family (via Arqia/Datora), plus an ESP32 telemetry sub-system. The core is a long-running TCP socket listener that ingests binary tracker packets over port 5023, parses multiple wire protocols, persists positions/events, and broadcasts live updates to a Leaflet map over WebSockets (Laravel Reverb). Codebase and comments are in Portuguese (pt-BR).

## Commands

```bash
# Tests (PHPUnit, config in phpunit.xml — SQLite :memory:, APP_ENV=testing)
php artisan test                              # all
php artisan test --filter=TelemetryApiTest    # single test class
php artisan test tests/Feature/TelemetryApiTest.php

# Lint / format (Laravel Pint)
./vendor/bin/pint            # fix
./vendor/bin/pint --test     # check only

# TCP listeners (choose ONE in production)
php artisan socket:listen                     # blocking, sequential — legacy/simple
php artisan workerman:listen start            # async, multi-worker — high concurrency
php artisan workerman:listen stop|restart|status

# Realtime + API tooling
php artisan reverb:start --host=0.0.0.0 --port=8080   # WebSocket server (live map)
php artisan l5-swagger:generate                        # regenerate API docs at /api/documentation

php artisan migrate
php artisan serve
```

There is no `tests/Unit` suite registered — only `tests/Feature` is wired in `phpunit.xml`. `.env.testing` supplies test env. The untracked `test_query.php` at repo root is a throwaway scratch script, not part of the suite.

## Architecture

### TCP ingestion pipeline (the heart of the system)

Packets flow: **listener command → ProtocolManager → Parser → TrackerService → EventProcessor → DB + broadcast**.

- **Listener commands** (`app/Console/Commands/`): `SocketListen` (blocking) and `WorkermanListen` (async, Workerman workers). Both do the same per-packet loop: pick a parser, parse, hand off to `TrackerService`, then send the protocol's ACK back on the connection. Env `SOCKET_HOST` / `SOCKET_PORT` / `SOCKET_WORKERS` override the CLI options.

- **ProtocolManager** (`app/Services/Protocols/ProtocolManager.php`): Strategy pattern. Holds an array of `ProtocolParserInterface` and returns the first parser whose `canParse()` matches the raw frame. It knows only the interface — **to add a protocol, register the concrete parser in `AppServiceProvider::register()` and nothing else changes.** Registered parsers: `Gt06Parser`, `Jt808Parser`, `TqParser`, `TrxParser`.

- **ProtocolParserInterface**: `getName()`, `canParse(raw)`, `parse(raw): ?array` (returns a standardized dados array), `getResponse(dados, raw): ?string` (ACK bytes). Parsers normalize wildly different binary formats into one array shape (imei, latitude, longitude, velocidade, evento_codigo, tipo, etc.).

- **TrackerService** (`app/Services/TrackerService.php`): orchestration only (SRP). Handles heartbeat/login packet types, maintains an in-process + Cache `IP → IMEI` map (`tracker_imei_{ip}`) so packets without an embedded IMEI on persistent connections still resolve, then persists the position inside a DB transaction via `firstOrCreate` on the tracker.

- **EventProcessor** (`app/Services/EventProcessor.php`): extracted from TrackerService; owns all event persistence and state sync. Decodes TRX bitmask event codes (`TrxParser::decodeEventos`) and direct GT06 alarms into `Evento` rows. Uses **Cache as state memory** to avoid redundant writes — ignition changes only persist on transition (`tracker_status_ignicao_{imei}`), and SOS/panic state double-checks DB + cache before updating (`tracker_status_sos_{imei}`).

### Two device families, kept separate

1. **TRX-16 GPS trackers** — the TCP pipeline above. Models: `Rastreador`, `Posicao`, `Evento`, `Telemetria`. Tables `rastreadores`, `posicoes`, `eventos`.
2. **ESP32** — HTTP-ingested telemetry (devices POST directly). Models `Esp32Dispositivo`, `Esp32Telemetria`; service `Esp32TelemetryService`; controllers under `app/Http/Controllers/Api/Esp32*`. Separate migration `create_esp32_tables` (time-series style with `payload_extra` JSON).

### API (`routes/api.php`, prefix `/api/v1`)

- ESP32 endpoints under `/v1/esp32/*` (ingest `POST /telemetry`, `GET /fleet` snapshot for the map, per-device historico/ultima, device CRUD).
- TRX rastreadores + posicoes CRUD/read endpoints.
- **Auth is inconsistent by design** — three schemes coexist: `POST /v1/telemetria` uses the custom `api_key` middleware (`CheckApiKey`, header `X-API-KEY` vs `TELEMETRIA_API_KEY`); `/v1/telemetria/{imei}/*` uses `auth:sanctum` (Bearer token); most other v1 routes are currently unauthenticated. The `api_key` alias is registered in `bootstrap/app.php` (not the default middleware file — Laravel 11 has no `app/Http/Kernel.php`).

### Realtime

Broadcast events (`app/Events/`) implement `ShouldBroadcast` over Reverb. `Esp32TelemetryReceived` fires on channel `esp32-fleet` (event name `TelemetryReceived`); `SosStatusChanged` for panic state. The `/mapa` and `/mapa-esp32` web views subscribe to these for live position updates. `RastreadorObserver` is registered in `AppServiceProvider::boot()`.

## Deployment

Target is a CentOS/AlmaLinux VPS (not the `docker/php` path implies — despite the repo location there is no Dockerfile here). Production runs the listener under **Supervisor** (`deploy/supervisor/rastreador-workerman.ini`) and Reverb + socket under **systemd**. Full step-by-step (PHP 8.2+ via Remi, Nginx, MariaDB, SELinux booleans, firewall port 5023, Swagger asset troubleshooting) is in `README.md` — consult it before changing anything deployment-related.

## Projetos irmãos (mesmo produto — editáveis nesta máquina)

**Você está aqui:** Backend Laravel. É uma das três partes de um mesmo produto (telemetria/rastreador ESP32) e é a **fonte da verdade do contrato de dados**. Ao analisar, corrigir ou adicionar melhorias no fluxo ESP32, considere os outros dois repos.

| Papel | Caminho local | Remote GitHub |
|-------|---------------|---------------|
| **Firmware ESP32** (LILYGO T-SIM7080G-S3) | `D:\Projetos\Arduino\Esp32\modem` | `git@github.com:mrlsoares/ESP32-TSIM7080S3.git` |
| **Backend Laravel 11** (ingestão HTTP + banco + mapa) — *este repo* | `D:\Projetos\docker\php\rastreador` | `git@github.com:mrlsoares/rastreador.git` |
| **App Android** (Capacitor + Ionic React; config/token via BLE) | `D:\Projetos\node\rastreador_ble` | `git@github.com:mrlsoares/rastreador_ble.git` |

**Contrato compartilhado (fonte da verdade = este backend):** payload JSON de `POST /api/v1/esp32/telemetry` + header `X-DEVICE-TOKEN` + protocolo de comandos BLE (Nordic UART) do firmware. Mudar qualquer campo do payload, regra de auth do token, ou schema `esp32_*` exige ajustar os **três** repos no mesmo ciclo: o firmware monta o JSON e recebe o token via `SETTOKEN:`, este backend ingere/valida, o app provisiona o token e lê a telemetria.
