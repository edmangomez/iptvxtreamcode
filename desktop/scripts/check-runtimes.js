const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');

const expected = [
  'bin/php/win/php.exe',
  'bin/php/linux/php',
  'bin/php/mac/php',
  'bin/ffmpeg/win/ffmpeg.exe',
  'bin/ffmpeg/linux/ffmpeg',
  'bin/ffmpeg/mac/ffmpeg',
];

let ok = true;
for (const rel of expected) {
  const abs = path.join(root, rel);
  const exists = fs.existsSync(abs);
  const flag = exists ? 'OK  ' : 'MISS';
  console.log(`${flag} ${rel}`);
  if (!exists) ok = false;
}

if (!ok) {
  console.log('\nFaltan runtimes embebidos.');
  process.exit(1);
}

console.log('\nRuntimes completos.');
