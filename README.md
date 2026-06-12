# xtream-player

Aplicación web PHP para visualizar la lista de canales IPTV de tu proveedor mediante la API de **Xtream Codes**.

## Características

-   Login seguro con sesión PHP
-   Dashboard con información de la cuenta
-   Canales en vivo con categorías y EPG
-   Películas (VOD) con póster y metadatos
-   Series con temporadas y episodios
-   Búsqueda global de canales
-   Reproductor de video embebido (Video.js)
-   Tema oscuro responsive

## Requisitos

-   PHP 7.4 o superior
-   Extensiones PHP: `curl`, `json`, `session`
-   Servidor web: Apache, Nginx, o PHP built-in server

## Instalación

### Opción 1 — PHP Built-in Server (rápido)

```bash
cd /ruta/del/proyecto
php -S 0.0.0.0:8080
```

Acceder a `http://localhost:8080`

### Opción 2 — Apache

```bash
# Copiar los archivos al directorio web
sudo cp -r xtream-player /var/www/html/
sudo chown -R www-data:www-data /var/www/html/xtream-player
```

Acceder a `http://localhost/xtream-player/`

### Opción 3 — Nginx

```nginx
server {
    listen 80;
    server_name ejemplo.com;
    root /var/www/html/xtream-player;
    index index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
}
```

## Uso

1.  Abre la aplicación en tu navegador
2.  Ingresa la **URL del servidor** (ej: `http://midominio:8080`), tu **usuario** y **contraseña** de Xtream Codes
3.  Haz clic en **Conectar**
4.  Explora las secciones: Live, VOD, Series

## Estructura del Proyecto

```
xtream-player/
├── assets/
│   └── style.css
├── config.php
├── dashboard.php
├── index.php
├── live.php
├── logout.php
├── player.php
├── proxy.php
├── search.php
├── series.php
├── series_info.php
├── vod.php
├── CHECKLIST.md
└── README.md
```

## API Utilizada

| Endpoint | Descripción |
|---|---|
| `player_api.php?username=X&password=X` | Información del usuario |
| `&action=get_live_categories` | Categorías de TV |
| `&action=get_live_streams` | Canales en vivo |
| `&action=get_short_epg&stream_id=X` | EPG del canal |
| `&action=get_vod_categories` | Categorías de películas |
| `&action=get_vod_streams` | Películas |
| `&action=get_vod_info&vod_id=X` | Info de película |
| `&action=get_series_categories` | Categorías de series |
| `&action=get_series` | Series |
| `&action=get_series_info&series_id=X` | Episodios de serie |

## Licencia

MIT
