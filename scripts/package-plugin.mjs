import { spawnSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const slug = 'wp-auto-translator';
const stageRoot = path.join(root, 'build');
const stage = path.join(stageRoot, slug);
const dist = path.join(root, 'dist');
const zipPath = path.join(dist, `${slug}.zip`);

const composerArgs = [
    'install',
    '--no-dev',
    '--optimize-autoloader',
    '--no-interaction',
    '--prefer-dist',
];

rmSync(stageRoot, { recursive: true, force: true });
mkdirSync(stage, { recursive: true });
mkdirSync(dist, { recursive: true });

for (const file of ['wp-auto-translator.php', 'uninstall.php', 'composer.json', 'composer.lock', 'README.md']) {
    cpSync(path.join(root, file), path.join(stage, file));
}
cpSync(path.join(root, 'src'), path.join(stage, 'src'), { recursive: true });
cpSync(path.join(root, 'assets'), path.join(stage, 'assets'), { recursive: true });

installDependencies(stage);

if (!existsSync(path.join(stage, 'vendor', 'autoload.php'))) {
    fail('composer install did not create vendor/autoload.php.');
}

rmSync(zipPath, { force: true });
run('zip', ['-r', zipPath, slug, '-x', '*.DS_Store'], { cwd: stageRoot });

const listing = spawnSync('unzip', ['-l', zipPath], { encoding: 'utf8' });
const listingText = `${listing.stdout ?? ''}\n${listing.stderr ?? ''}`;
if (listing.status !== 0 || !listingText.includes(`${slug}/wp-auto-translator.php`)) {
    fail(`The zip is missing ${slug}/wp-auto-translator.php.`);
}
if (listingText.includes('phpunit')) {
    fail('The zip contains PHPUnit. It should ship production dependencies only.');
}

console.log(`Wrote ${path.relative(root, zipPath)}`);

function installDependencies(cwd) {
    if (commandExists('composer')) {
        const local = spawnSync('composer', composerArgs, { cwd, stdio: 'inherit' });
        if (local.status === 0) {
            return;
        }
        console.error('Local composer install failed. Trying the composer Docker image.');
        rmSync(path.join(cwd, 'vendor'), { recursive: true, force: true });
    }

    const docker = findDocker();
    if (docker === null) {
        fail('Install Composer, or Docker, so the plugin zip can include Neuron.');
    }

    const mount = docker.endsWith('.exe') ? windowsPath(cwd) : cwd;
    const uid = spawnSync('id', ['-u'], { encoding: 'utf8' });
    const gid = spawnSync('id', ['-g'], { encoding: 'utf8' });
    run(docker, [
        'run',
        '--rm',
        '-u',
        `${uid.stdout.trim()}:${gid.stdout.trim()}`,
        '-e',
        'COMPOSER_HOME=/tmp/composer',
        '-v',
        `${mount}:/app`,
        '-w',
        '/app',
        'composer:2',
        ...composerArgs,
    ]);
}

function findDocker() {
    if (process.env.DOCKER && existsSync(process.env.DOCKER)) {
        return process.env.DOCKER;
    }
    if (commandExists('docker')) {
        return 'docker';
    }
    const desktop = '/mnt/c/Program Files/Docker/Docker/resources/bin/docker.exe';
    if (existsSync(desktop)) {
        return desktop;
    }
    return null;
}

function windowsPath(linuxPath) {
    const converted = spawnSync('wslpath', ['-w', linuxPath], { encoding: 'utf8' });
    if (converted.status !== 0) {
        fail(`Could not convert ${linuxPath} to a Windows path for Docker.`);
    }
    return converted.stdout.trim();
}

function commandExists(name) {
    return spawnSync('which', [name], { stdio: 'ignore' }).status === 0;
}

function run(command, args, options = {}) {
    const result = spawnSync(command, args, { stdio: 'inherit', ...options });
    if (result.status !== 0) {
        fail(`${command} exited with status ${result.status ?? 'unknown'}.`);
    }
}

function fail(message) {
    console.error(message);
    process.exit(1);
}
