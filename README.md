# Rastreador GPS TRX-16 (Laravel)

Este repositório contém o sistema de rastreamento com um ouvinte TCP (socket) em PHP para dispositivos GPS da família TRX-16 (via Arqia/Datora).

Abaixo estão os comandos resumidos para deploy e configuração em uma **VPS Linux CentOS** (CentOS 7, 8, 9 ou AlmaLinux/Rocky Linux).

---

## 1. Atualizar o sistema e instalar dependências essenciais
```bash
sudo dnf update -y
sudo dnf install -y epel-release yum-utils git unzip curl nano
```

## 2. Instalar PHP 8.2+ e extensões
No CentOS, recomenda-se habilitar o repositório Remi para versões mais recentes do PHP:
```bash
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm  # Para CentOS/Alma 9
sudo dnf module enable php:remi-8.2 -y
sudo dnf install -y php php-cli php-fpm php-mysqlnd php-xml php-mbstring php-curl php-zip
```

## 3. Instalar o Composer
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
```

## 4. Instalar Nginx e Banco de Dados (MariaDB)
```bash
sudo dnf install -y nginx mariadb-server mariadb
sudo systemctl enable --now nginx mariadb

# Configuração inicial do MariaDB
sudo mysql_secure_installation
```

## 5. Baixar o Projeto
```bash
# Vá para o diretório padrão web
cd /var/www/

# Clone o repositório
git clone https://github.com/mrlsoares/rastreador.git
cd rastreador
```

## 6. Configurar o App (Laravel)
```bash
# Instalar dependências do Laravel
composer install --optimize-autoloader --no-dev

# Configurar o .env
cp .env.example .env
nano .env # Edite as credenciais do banco (DB_DATABASE, DB_USERNAME, DB_PASSWORD)

# Gerar a chave do aplicativo
php artisan key:generate

# Rodar as migrações (Criação do banco e tabelas `rastreadores` e `posicoes`)
php artisan migrate
```

## 7. Ajustar Permissões (SELinux e Pastas)
Para o Laravel gravar logs e cache corretamente:
```bash
# Se o SELinux estiver enforcing, permita a gravação:
sudo chcon -Rt httpd_sys_rw_content_t storage
sudo chcon -Rt httpd_sys_rw_content_t bootstrap/cache

# Ajustar dono
sudo chown -R apache:apache storage bootstrap/cache /var/lib/php/session
sudo chmod -R 775 storage bootstrap/cache
```

## 8. Configurar Nginx (Web Server API)
Crie um arquivo de configuração para o domínio do rastreador ou IP:
```bash
sudo nano /etc/nginx/conf.d/rastreador.conf
```
*Conteúdo base do Nginx:*
```nginx
server {
    listen 80;
    server_name seu_dominio.com ou_seu_ip;
    root /var/www/rastreador/public;

    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock; # Pode variar dependendo da configuração do PHP-FPM
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```
Reinicie o Nginx e o PHP-FPM:
```bash
sudo systemctl restart nginx php-fpm
```

## 9. Manter o Socket Listener Rodando (Systemd)
O comando que escuta a porta **5023** via TCP precisa rodar continuamente. No CentOS, a melhor forma é gerir isso pelo **Systemd**.

**Crie o arquivo do serviço:**
```bash
sudo nano /etc/systemd/system/rastreador-socket.service
```

*Cole o seguinte:*
```ini
[Unit]
Description=Rastreador GPS Socket Listener (Laravel Console)
After=network.target

[Service]
User=root
# Ajuste o caminho até a pasta do projeto
WorkingDirectory=/var/www/rastreador
ExecStart=/usr/bin/php artisan socket:listen
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

**Ativar e Iniciar o Serviço:**
```bash
sudo systemctl daemon-reload
sudo systemctl enable rastreador-socket
sudo systemctl start rastreador-socket

# Para ver o status:
sudo systemctl status rastreador-socket
```

## 10. Liberar a porta 5023 no Firewall do CentOS
Se o `firewalld` estiver rodando, libere o tráfego TCP para os rastreadores enviarem dados:
```bash
sudo firewall-cmd --permanent --add-port=5023/tcp
sudo firewall-cmd --permanent --add-port=80/tcp
sudo firewall-cmd --reload
```

## Verificação dos Logs
Para visualizar em tempo real os pacotes chegando:
```bash
tail -f /var/www/rastreador/storage/logs/laravel.log | grep "\[Socket\]"
```

## 11. Habilitar a API Externa e WebSockets (Novas Funcionalidades)
Caso seu servidor não tenha a documentação do Swagger ou as rotas de API instaladas, rode os comandos abaixo na pasta raiz (`/var/www/rastreador`):

```bash
# 1. Baixar pacote do Swagger/Documentação
composer require "darkaonline/l5-swagger"

# 2. Publicar arquivos base do Swagger
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"

# 3. Instalar o ecossistema de Tokens (Sanctum)
php artisan install:api --without-migration-prompt

# 4. Rodar as migrações finais (Criará a tabela de tokens)
php artisan migrate

# 5. Gerar o painel visual (Acesse depois via http://seu_dominio/api/documentation)
php artisan l5-swagger:generate
```

## 12. Manter o WebSocket (Reverb) Rodando (Para Posição ao Vivo no Mapa)
Assim como o Socket Listener, o mapa em tempo real precisa que o **Reverb** fique ativo. Crie um segundo serviço no Systemd:

```bash
sudo nano /etc/systemd/system/rastreador-reverb.service
```

*Cole o seguinte:*
```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
User=root
WorkingDirectory=/var/www/rastreador
ExecStart=/usr/bin/php artisan reverb:start --host=0.0.0.0 --port=8080
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

**Ativar e Iniciar o Reverb:**
```bash
sudo systemctl daemon-reload
sudo systemctl enable rastreador-reverb
sudo systemctl start rastreador-reverb
```
Lembre-se de liberar a porta `8080` (se não usar o proxy reverso via nginx HTTPS) liberando no firewall: `sudo firewall-cmd --permanent --add-port=8080/tcp`. Se usar HTTPS com a configuração Nginx que montamos, passe tudo via porta `443`.
