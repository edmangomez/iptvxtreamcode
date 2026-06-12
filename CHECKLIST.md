# Checklist — xtream-player

## FASE 1 — Proyecto Base y Login
- [x] Crear estructura de directorios (`xtream-player/` y `assets/`)
- [x] `config.php` — Sesión PHP, helpers (buildApiUrl, buildStreamUrl, curlRequest, apiRequest)
- [x] `proxy.php` — Proxy cURL para consumir API Xtream Codes
- [x] `index.php` — Login con formulario, test de conexión, validación
- [x] `logout.php` — Destruir sesión y redirigir
- [x] `assets/style.css` — Tema oscuro profesional
- [x] `CHECKLIST.md` + `README.md` — Documentación
- [x] **Pruebas Fase 1** — php -l, servidor, login/logout

---

## FASE 2 — Dashboard e Información del Usuario
- [x] `dashboard.php` — Llamar player_api.php y mostrar info de la cuenta
- [x] `partials/navbar.php` — Navegación global reutilizable
- [x] Mostrar: username, expiración, status, conexiones, días restantes
- [x] Tarjetas de acceso rápido a Live, VOD, Series con hover
- [x] Estilos: card-hover, navbar-toggler-icon
- [x] **Pruebas Fase 2** — php -l, dashboard carga, navegación funciona

---

## FASE 3 — Canales en Vivo (Live TV)
- [x] `live.php` — Sidebar categorías + grid de canales
- [x] Al seleccionar categoría, cargar get_live_streams
- [x] Cada canal: logo, nombre, botón "Ver"
- [x] Búsqueda por nombre de canal
- [x] `player.php` — Reproductor Video.js con stream .ts/.m3u8
- [x] **Pruebas Fase 3** — php -l, navegación live, reproductor

---

## FASE 4 — Películas (VOD)
- [x] `vod.php` — Sidebar categorías + grid tarjetas con póster, título, año, rating
- [x] Al dar clic → player.php con stream VOD y extensión resuelta
- [x] Resolver extensión vía get_vod_info + container_extension
- [x] Búsqueda por nombre de película
- [x] **Pruebas Fase 4** — php -l, navegación VOD

---

## FASE 5 — Series
- [x] `series.php` — Sidebar categorías + grid series con póster, año, rating
- [x] `series_info.php` — Info serie + accordion temporadas + lista episodios
- [x] Reproductor para episodio individual (player.php con ext vía URL)
- [x] Búsqueda por nombre de serie
- [x] **Pruebas Fase 5** — php -l, navegación series

---

## FASE 6 — Búsqueda y EPG
- [x] `search.php` — Búsqueda global en Live + VOD + Series (hasta 50 resultados por sección)
- [x] EPG en player.php para canales live (get_short_epg) con tabla y badge "EN VIVO"
- [x] Barra de búsqueda en navbar (formulario en todas las páginas)
- [x] **Pruebas Fase 6** — php -l, búsqueda, EPG

---

## FASE 7 — Pulido, Errores y Prueba Final
- [x] `config.php` — checkSession() cada 5 min, auto-logout si auth expira
- [x] `config.php` — curlRequest con CONNECTTIMEOUT=10s, mensajes de error en español
- [x] Estados vacíos consistentes en live.php, vod.php, series.php, search.php
- [x] Loader/spinner global (page-loader) en navbar.php
- [x] `style.css` — Estilos para accordion, list-group, tabla oscura, spinner
- [x] Diseño responsive (Bootstrap grid + media queries)
- [x] **Prueba de integración completa** — 12 PHP sin errores, todas las páginas redirigen sin sesión, assets sirven
