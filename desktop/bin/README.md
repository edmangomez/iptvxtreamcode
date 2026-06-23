# Runtimes Embebidos

Coloca aqui los binarios para builds standalone.

Estructura esperada:

- `php/win/php.exe`
- `php/linux/php`
- `php/mac/php`
- `ffmpeg/win/ffmpeg.exe`
- `ffmpeg/linux/ffmpeg`
- `ffmpeg/mac/ffmpeg`

Notas:

- En Linux y macOS, dar permisos de ejecucion (`chmod +x`).
- Si no existen estos binarios, la app intentara usar `php` del sistema.
