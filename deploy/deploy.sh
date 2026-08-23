#!/usr/bin/env bash
# ============================================================================
# Deploy — rastreador (Laravel) em produção (VPS CentOS/AlmaLinux).
# Uso:   sudo -u nginx bash deploy/deploy.sh
# Requer: rodar a partir de /var/www/rastreador (ou ajustar APP_DIR).
#
# Variáveis opcionais:
#   BRANCH=master              # branch a puxar
#   SKIP_BACKUP=1              # pula o pg_dump
#   MONITOR_PASSWORD='...'     # se setado, troca a senha do usuário monitor
#   SKIP_SUPERVISOR=1         # não reinicia o listener workerman
#   PHP=/usr/bin/php           # binário do PHP
# ============================================================================
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/rastreador}"
BRANCH="${BRANCH:-master}"
PHP="${PHP:-php}"

log()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m[aviso] %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m[erro] %s\033[0m\n' "$*" >&2; exit 1; }

# --- pré-checagens -----------------------------------------------------------
cd "$APP_DIR" || die "APP_DIR não existe: $APP_DIR"
[ -f artisan ]      || die "artisan não encontrado em $APP_DIR"
command -v git >/dev/null || die "git ausente"
command -v "$PHP" >/dev/null || die "php ausente ($PHP)"

# garante volta ao modo online e limpeza mesmo em falha
cleanup() { $PHP artisan up >/dev/null 2>&1 || true; }
trap cleanup EXIT

log "Deploy rastreador — branch '$BRANCH' em $APP_DIR"

# --- backup do banco (pgsql) -------------------------------------------------
if [ "${SKIP_BACKUP:-0}" != "1" ]; then
  log "Backup do banco (pg_dump)"
  DB_CONNECTION=$(grep -E '^DB_CONNECTION=' .env | cut -d= -f2- | tr -d '"' || true)
  if [ "${DB_CONNECTION:-pgsql}" = "pgsql" ]; then
    DB_HOST=$(grep -E '^DB_HOST='     .env | cut -d= -f2- | tr -d '"')
    DB_PORT=$(grep -E '^DB_PORT='     .env | cut -d= -f2- | tr -d '"'); DB_PORT="${DB_PORT:-5432}"
    DB_DATABASE=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"')
    DB_USERNAME=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
    DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')
    OUT="$HOME/backup_${DB_DATABASE}_$(date +%F_%H%M%S).sql"
    if PGPASSWORD="$DB_PASSWORD" pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" "$DB_DATABASE" > "$OUT" 2>/dev/null; then
      log "Backup salvo: $OUT"
    else
      warn "pg_dump falhou — continuando (use SKIP_BACKUP=1 para pular de propósito)"
    fi
  else
    warn "DB_CONNECTION=$DB_CONNECTION (não pgsql) — pulando backup automático"
  fi
else
  warn "SKIP_BACKUP=1 — sem backup"
fi

# --- modo manutenção ---------------------------------------------------------
log "Ativando modo manutenção"
$PHP artisan down --render="errors::503" >/dev/null 2>&1 || $PHP artisan down || true

# --- código ------------------------------------------------------------------
log "git pull origin $BRANCH"
git fetch --all --prune
git checkout "$BRANCH"
git pull origin "$BRANCH"

# --- dependências (inalteradas neste release, mas garante autoload) ----------
log "composer install (--no-dev)"
composer install --no-dev --optimize-autoloader --no-interaction

# --- migrations: NÃO há novas neste release (no-op seguro) --------------------
log "migrate --force (no-op se não houver migrations)"
$PHP artisan migrate --force

# --- pré-checagem de papéis (risco do gating de rotas) -----------------------
log "Checando usuários sem papel (tomariam 403 nas rotas restritas)"
SEM_ROLE=$($PHP artisan tinker --execute="echo App\Models\User::doesntHave('roles')->count();" 2>/dev/null | tail -n1 | tr -dc '0-9')
if [ -n "${SEM_ROLE:-}" ] && [ "$SEM_ROLE" -gt 0 ]; then
  warn "$SEM_ROLE usuário(s) SEM papel — vão receber 403 nas rotas restritas. Confira depois:"
  warn "  php artisan tinker --execute=\"App\\Models\\User::doesntHave('roles')->pluck('email')->each(fn(\\\$e)=>print(\\\$e.PHP_EOL));\""
fi

# --- seeder: papel 'leitor' + usuário monitor (idempotente) ------------------
log "Seeder RolesEUsuarioAdminSeeder"
$PHP artisan db:seed --class=RolesEUsuarioAdminSeeder --force

# --- senha do monitor (opcional via env) -------------------------------------
if [ -n "${MONITOR_PASSWORD:-}" ]; then
  log "Trocando senha do usuário monitor@rastreador.local"
  MONITOR_PASSWORD="$MONITOR_PASSWORD" $PHP artisan tinker --execute="\$u=App\Models\User::where('email','monitor@rastreador.local')->first(); if(\$u){\$u->password=Illuminate\Support\Facades\Hash::make(getenv('MONITOR_PASSWORD')); \$u->save(); echo 'senha atualizada';} else { echo 'monitor nao encontrado'; }"
else
  warn "MONITOR_PASSWORD não informado — a senha do monitor segue no default. TROQUE manualmente!"
fi

# --- cache do Spatie (novo papel) --------------------------------------------
log "permission:cache-reset"
$PHP artisan permission:cache-reset || $PHP artisan cache:clear

# --- rebuild dos caches (route mudou => obrigatório) -------------------------
log "Rebuild config/route/view cache"
$PHP artisan config:clear && $PHP artisan config:cache
$PHP artisan route:clear  && $PHP artisan route:cache
$PHP artisan view:clear   && $PHP artisan view:cache

# swagger: api-docs.json já vem commitado; descomente se GENERATE_ALWAYS=false
# e você preferir regenerar no servidor:
# $PHP artisan l5-swagger:generate

# --- recarrega PHP (opcache) e listener --------------------------------------
log "Reload php-fpm (opcache)"
sudo systemctl reload php-fpm || warn "reload php-fpm falhou (rode manualmente)"

if [ "${SKIP_SUPERVISOR:-0}" != "1" ]; then
  log "Restart workerman (supervisor)"
  sudo supervisorctl restart rastreador-workerman || warn "supervisorctl falhou (rode manualmente)"
fi

# --- fim ---------------------------------------------------------------------
log "Saindo do modo manutenção"
$PHP artisan up
trap - EXIT

log "Deploy concluído."
echo "Smoke sugerido:"
echo "  login monitor -> GET /api/v1/esp32/monitor/dispositivos (200)"
echo "  GET /api/v1/esp32/{mac}/ultima em device vazio -> 200 success:false"
echo "  GET /api/v1/esp32/fleet -> 403"
echo "  Swagger: https://rastreador.ddnsgratis.com.br/api/documentation"
