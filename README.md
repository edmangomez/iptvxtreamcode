# IPTV Xtream Code

IPTV Player with Xtream Codes API

## Estado actual

Este proyecto ya incluye:

- Modo de autenticacion configurable: `local`, `remote`, `hybrid`.
- Cache offline para autenticacion hibrida (dias configurables).
- API de autenticacion remota en `admin/api/auth.php`.
- Multiview Live con 2 slots (A/B) en `player.php`.
- Base de app de escritorio Electron en `desktop/`.
- Checklist de fases en `PHASES_CHECKLIST.md`.

## Configuracion de autenticacion

Desde `admin/settings.php` puedes configurar:

- `auth_mode`: local / remote / hybrid.
- `remote_auth_url`: URL base del servidor remoto (VPS).
- `remote_shared_key`: clave compartida opcional.
- `offline_grace_days`: dias para login offline en modo hibrido.
- `remote_timeout_sec`: timeout de requests remotas.
- `remote_verify_ssl`: valida certificado SSL en remoto.
- `remote_api_token_ttl_sec`: TTL de token emitido por API remota.
- `token_ttl_sec`: duracion de token local.

### Flujo recomendado

- En desarrollo local: `auth_mode=local`.
- En produccion con VPS: `auth_mode=hybrid`.

## API remota (admin)

Ruta: `admin/api/auth.php`

Acciones disponibles por `POST`:

- `action=user_login`
  - body: `{ "username": "...", "password": "...", "shared_key": "..." }`
- `action=validate`
  - body: `{ "token": "...", "shared_key": "..." }`
- `action=refresh`
  - body: `{ "token": "...", "shared_key": "..." }`
- `action=revoke`
  - body: `{ "token": "...", "shared_key": "..." }`

## Live Multiview

En la seccion Live del reproductor:

- Boton `Single` para vista normal.
- Boton `Dual` para vista de 2 transmisiones.
- Selector de `Slot A` o `Slot B` para decidir donde abrir el siguiente canal.
- Audio activo por slot seleccionado para evitar mezcla de audio.
- Estado por slot (`Idle`, `Loading`, `Playing`, `Retry`, `Error`).
- Fallback automatico a `single` tras fallos repetidos en modo dual.

## Desktop (Electron)

Carpeta: `desktop/`

### Requisitos

- Node.js 18+
- PHP instalado en sistema (por ahora)

### Ejecutar

```bash
cd desktop
npm install
npm run start
```

Esto abre una ventana de escritorio y levanta PHP local en `127.0.0.1:8090`.

Si no encuentra PHP embebido, intenta usar `php` del sistema.

### Build instalables

```bash
cd desktop
npm install
npm run build:win
npm run build:linux
npm run build:mac
```

Los artefactos se generan en `desktop/release/`.

Notas:

- En Windows sin privilegios de symlink, se recomienda `signAndEditExecutable: false` (ya configurado).
- Agrega binarios embebidos de `php/ffmpeg` en `desktop/bin` para builds totalmente standalone.

### Verificar runtimes embebidos

```bash
cd desktop
npm run check:runtimes
```

## Smoke test auth remota

Script: `tools/auth-smoke-test.ps1`

Ejemplo:

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\auth-smoke-test.ps1 -BaseUrl "http://127.0.0.1:8081" -Username "cliente1" -Password "pass123"
```

## Control de fases

Revisa `PHASES_CHECKLIST.md` para el avance detallado de cada fase.
