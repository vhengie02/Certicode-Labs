// Starts an isolated VS Code (its own settings and extensions folders, deleted afterwards) that
// runs test.js, waits for its result, prints it and exits 0 when the check passed.
//
//   npm run sync-check                    local relay
//   npm run sync-check -- --break         must fail: shows the check can catch a mistake
//   SYNC_CHECK_SUPABASE_URL=... SYNC_CHECK_SUPABASE_KEY=... npm run sync-check     live channel
//
// The Supabase URL and publishable key are read from the environment and never written down.

const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const here = __dirname;
const work = fs.mkdtempSync(path.join(os.tmpdir(), 'certicode-sync-check-'));
const result = path.join(work, 'result.json');
const folder = path.join(work, 'lab');
fs.mkdirSync(folder);

const env = {
    ...process.env,
    SYNC_CHECK_RESULT: result,
    SYNC_CHECK_FOLDER: folder,
    SYNC_CHECK_BREAK: process.argv.includes('--break') ? '1' : '0',
};

const args = [
    '--user-data-dir', path.join(work, 'user-data'),
    '--extensions-dir', path.join(work, 'extensions'),
    '--disable-workspace-trust', '--skip-welcome', '--skip-release-notes', '--new-window',
    // The real extension: VS Code only gives its API to code inside the extension under development.
    // It activates on a vscode:// link only, so it stays idle here.
    `--extensionDevelopmentPath=${path.join(here, '..', '..')}`,
    `--extensionTestsPath=${path.join(here, 'test.js')}`,
    folder,
];

// On Windows `code` is a .cmd script, which runs through the shell: quote every argument, since
// paths can hold spaces
const windows = process.platform === 'win32';
const code = spawn(windows ? 'code.cmd' : 'code', windows ? args.map(arg => `"${arg}"`) : args, { env, shell: windows, stdio: 'ignore' });
code.on('error', error => {
    console.error(`Could not start VS Code: ${error.message}`);
    process.exit(2);
});

const started = Date.now();
const timer = setInterval(() => {
    if (fs.existsSync(result)) {
        clearInterval(timer);
        const outcome = JSON.parse(fs.readFileSync(result, 'utf8'));
        console.log(JSON.stringify(outcome, null, 2));
        setTimeout(() => {
            try { fs.rmSync(work, { recursive: true, force: true }); } catch { /* VS Code may still hold files */ }
            process.exit(outcome.ok ? 0 : 1);
        }, 3000);
    } else if (Date.now() - started > 5 * 60 * 1000) {
        clearInterval(timer);
        console.error('No result after 5 minutes.');
        process.exit(1);
    }
}, 1000);
