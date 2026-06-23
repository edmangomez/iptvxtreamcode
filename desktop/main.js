const { app, BrowserWindow, shell } = require('electron');
const path = require('path');
const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');

let mainWindow = null;
let phpProcess = null;
const appPort = process.env.IPTV_LOCAL_PORT || '8090';
let phpStartupError = '';

function getProjectRoot() {
  if (!app.isPackaged) {
    return path.join(__dirname, '..');
  }
  const c1 = path.join(process.resourcesPath, 'www');
  const c2 = path.join(process.resourcesPath, 'app.asar.unpacked', 'www');
  const c3 = path.join(process.resourcesPath, 'app.asar.unpacked');
  const c4 = process.resourcesPath;

  const hasRootPhp = (p) => p && fs.existsSync(path.join(p, 'index.php'));

  if (hasRootPhp(c1)) return c1;
  if (hasRootPhp(c2)) return c2;
  if (hasRootPhp(c3)) return c3;
  if (hasRootPhp(c4)) return c4;
  return process.resourcesPath;
}

function resolvePhpCandidates() {
  const candidates = [];
  if (app.isPackaged) {
    if (process.platform === 'win32') {
      candidates.push(path.join(process.resourcesPath, 'bin', 'php', 'win', 'php.exe'));
    } else if (process.platform === 'darwin') {
      candidates.push(path.join(process.resourcesPath, 'bin', 'php', 'mac', 'php'));
    } else {
      candidates.push(path.join(process.resourcesPath, 'bin', 'php', 'linux', 'php'));
    }
  }
  candidates.push(process.env.IPTV_PHP_BIN || 'php');
  return candidates.filter(Boolean);
}

function resolvePhpExecutable() {
  const candidates = resolvePhpCandidates();
  for (const c of candidates) {
    if (c === 'php') return c;
    if (fs.existsSync(c)) return c;
  }
  return candidates[candidates.length - 1] || 'php';
}

function waitForServerReady(timeoutMs = 15000) {
  const start = Date.now();
  return new Promise((resolve, reject) => {
    const tick = () => {
      const req = http.get(`http://127.0.0.1:${appPort}/`, (res) => {
        res.resume();
        resolve();
      });
      req.on('error', () => {
        if (Date.now() - start > timeoutMs) {
          reject(new Error('PHP server start timeout'));
          return;
        }
        setTimeout(tick, 300);
      });
    };
    tick();
  });
}

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1400,
    height: 900,
    minWidth: 1000,
    minHeight: 700,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  if (phpStartupError) {
    const html = `
      <html><head><meta charset="utf-8"><title>IPTV Desktop Error</title></head>
      <body style="font-family: Segoe UI, Arial; background:#0d1117; color:#e6edf3; padding:20px;">
        <h2>No se pudo iniciar PHP local</h2>
        <p>Revisa los runtimes en <code>desktop/bin/php</code> o instala PHP en PATH.</p>
        <pre style="white-space:pre-wrap; background:#161b22; padding:12px; border-radius:8px;">${phpStartupError}</pre>
      </body></html>`;
    mainWindow.loadURL(`data:text/html,${encodeURIComponent(html)}`);
  } else {
    mainWindow.loadURL(`http://127.0.0.1:${appPort}/`);
  }

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });
}

function startPhpServer() {
  const projectRoot = getProjectRoot();
  const phpExec = resolvePhpExecutable();
  phpStartupError = '';

  if (!fs.existsSync(path.join(projectRoot, 'index.php'))) {
    phpStartupError = `No se encontro index.php en ${projectRoot}`;
    console.error('[desktop] invalid project root:', projectRoot);
    return;
  }

  phpProcess = spawn(phpExec, ['-S', `127.0.0.1:${appPort}`, '-t', projectRoot], {
    cwd: projectRoot,
    windowsHide: true,
  });

  phpProcess.on('error', (err) => {
    phpStartupError = err.message;
    console.error('[desktop] php start error:', err.message);
  });

  phpProcess.on('exit', (code) => {
    if (code !== 0) {
      phpStartupError = `PHP finalizo con codigo ${code}`;
    }
  });

  phpProcess.stderr.on('data', (chunk) => {
    const txt = String(chunk || '').trim();
    if (txt) console.error('[php]', txt);
  });
}

function stopPhpServer() {
  if (!phpProcess) return;
  try {
    phpProcess.kill();
  } catch (e) {
    console.error('[desktop] php stop error:', e.message);
  }
  phpProcess = null;
}

app.whenReady().then(() => {
  if (!app.requestSingleInstanceLock()) {
    app.quit();
    return;
  }

  startPhpServer();
  waitForServerReady()
    .then(() => createWindow())
    .catch((err) => {
      console.error('[desktop] server readiness error:', err.message);
      if (!phpStartupError) phpStartupError = err.message;
      createWindow();
    });
});

app.on('window-all-closed', () => {
  stopPhpServer();
  if (process.platform !== 'darwin') app.quit();
});

app.on('before-quit', () => {
  stopPhpServer();
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) createWindow();
});

app.on('second-instance', () => {
  if (mainWindow) {
    if (mainWindow.isMinimized()) mainWindow.restore();
    mainWindow.focus();
  }
});
