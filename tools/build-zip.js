const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const rootDir = path.join(__dirname, '..');
const sourceDir = path.join(rootDir, 'src');
const pluginFile = path.join(sourceDir, 'simple-foss-calendar.php');
const pluginSlug = 'openagenda-events-calendar';
const sourcePluginFileName = 'simple-foss-calendar.php';
const targetPluginFileName = `${pluginSlug}.php`;

function readVersion() {
  const content = fs.readFileSync(pluginFile, 'utf8');
  const match = content.match(/define\(\s*'OPENAGENDA_VERSION'\s*,\s*'([^']+)'\s*\)/);

  if (!match) {
    throw new Error('Could not read OPENAGENDA_VERSION from simple-foss-calendar.php.');
  }

  return match[1];
}

function removeDirectory(target) {
  if (!fs.existsSync(target)) {
    return;
  }

  makeWritable(target);

  try {
    fs.rmSync(target, { recursive: true, force: true, maxRetries: 10, retryDelay: 250 });
    return;
  } catch (error) {
    try {
      execFileSync('powershell', [
        '-NoProfile',
        '-Command',
        `Remove-Item -LiteralPath ${quotePowerShell(target)} -Recurse -Force -ErrorAction Stop`,
      ], { stdio: 'ignore' });
      return;
    } catch (fallbackError) {
      console.warn(`Warning: could not remove temporary build directory: ${target}`);
    }
  }
}

function makeWritable(target) {
  fs.chmodSync(target, 0o700);

  if (!fs.statSync(target).isDirectory()) {
    return;
  }

  fs.readdirSync(target).forEach((entry) => {
    makeWritable(path.join(target, entry));
  });
}

function quotePowerShell(value) {
  return `'${value.replace(/'/g, "''")}'`;
}

const version = readVersion();
const buildRoot = fs.mkdtempSync(path.join(os.tmpdir(), `openagenda-build-${version}-`));
const pluginDir = path.join(buildRoot, pluginSlug);
let zipPath = path.join(rootDir, `${pluginSlug}-${version}.zip`);

function prepareZipPath(target) {
  if (!fs.existsSync(target)) {
    return target;
  }

  try {
    fs.rmSync(target, { force: true, maxRetries: 5, retryDelay: 250 });
    return target;
  } catch (error) {
    const fallback = path.join(rootDir, `${pluginSlug}-${version}-fixed.zip`);

    if (!fs.existsSync(fallback)) {
      return fallback;
    }

    return path.join(rootDir, `${pluginSlug}-${version}-${Date.now()}.zip`);
  }
}

function normalizePackageFilenames() {
  const sourcePluginFile = path.join(pluginDir, sourcePluginFileName);
  const targetPluginFile = path.join(pluginDir, targetPluginFileName);

  if (fs.existsSync(sourcePluginFile)) {
    fs.renameSync(sourcePluginFile, targetPluginFile);
  }

  const languagesDir = path.join(pluginDir, 'languages');
  if (!fs.existsSync(languagesDir)) {
    return;
  }

  fs.readdirSync(languagesDir).forEach((file) => {
    if (!file.startsWith('simple-foss-calendar')) {
      return;
    }

    const target = file.replace('simple-foss-calendar', pluginSlug);
    fs.renameSync(path.join(languagesDir, file), path.join(languagesDir, target));
  });
}

try {
  fs.mkdirSync(pluginDir, { recursive: true });
  fs.cpSync(sourceDir, pluginDir, { recursive: true });
  normalizePackageFilenames();
  zipPath = prepareZipPath(zipPath);

  execFileSync('powershell', [
    '-NoProfile',
    '-Command',
    `Compress-Archive -LiteralPath ${quotePowerShell(pluginDir)} -DestinationPath ${quotePowerShell(zipPath)} -Force`,
  ], { stdio: 'inherit' });

  console.log(`Wrote ${zipPath}`);
} finally {
  removeDirectory(buildRoot);
}
