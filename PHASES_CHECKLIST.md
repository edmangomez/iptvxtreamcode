# Checklist de Fases

Estado general: en progreso.

## Fase 1 - Base cross-platform (Desktop shell)

- [x] Crear carpeta `desktop/` para la app Electron
- [x] Crear `package.json` base con scripts de desarrollo
- [x] Crear `desktop/main.js` para abrir la app web local
- [x] Crear `desktop/preload.js`
- [x] Integrar arranque automatico de PHP local desde Electron
- [x] Crear estructura `desktop/bin` y validador de runtimes embebidos
- [ ] Empaquetar binarios de PHP/ffmpeg por plataforma (Win/Linux/macOS)
- [x] Configurar scripts de build instalable por OS (electron-builder)
- [x] Generar build instalable Windows (`build:win`)
- [ ] Generar builds instalables Linux y macOS

## Fase 2 - Configuracion local/remota de autenticacion

- [x] Crear estructura de `app_settings` en backend
- [x] Agregar modos `local`, `remote`, `hybrid`
- [x] Agregar cache offline con ventana configurable
- [x] Agregar soporte de login remoto con fallback offline en modo hibrido
- [x] Agregar UI admin para gestionar configuracion
- [x] Endurecer validaciones SSL para produccion

## Fase 3 - API remota (Admin/VPS)

- [x] Crear endpoint remoto en `admin/api/auth.php`
- [x] Exponer `user_login` para autenticacion desde desktop
- [x] Exponer `validate` para validar token remoto
- [x] Exponer `refresh` para renovacion de token
- [x] Agregar revocacion de sesiones remotas

## Fase 4 - Live Multiview (2 transmisiones)

- [x] Agregar layout de doble reproductor en Live
- [x] Agregar selector de vista `Single/Dual`
- [x] Agregar selector de slot activo `A/B`
- [x] Reproduccion HLS independiente por slot
- [x] Control de audio activo por slot (A o B)
- [x] Indicadores de estado por slot (loading/error/retrying)
- [x] Fallback automatico a single si el equipo no soporta dual estable

## Fase 5 - QA, docs y operacion

- [x] Actualizar README con arquitectura y roadmap
- [x] Crear checklist versionado en repo
- [ ] Pruebas manuales completas en Windows
- [ ] Pruebas manuales completas en Linux
- [ ] Pruebas manuales completas en macOS
- [ ] Definir estrategia de actualizaciones
- [x] Agregar smoke test para API auth remota

## Decisiones registradas

- Autenticacion objetivo: local + remota + hibrida con cache offline.
- Roadmap de escritorio: mantener base PHP actual y envolver con Electron.
- Feature prioritaria de experiencia: multiview Live con 2 transmisiones.
