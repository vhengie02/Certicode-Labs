/** Files that are notes or data rather than code; the same list as LabSession::isCodeFile. */
const NOT_CODE = /\.(md|txt|json|env|log|lock|ya?ml)$/i;

/** Names Windows refuses or treats as devices, whatever the extension. */
const WINDOWS_RESERVED = /^(con|prn|aux|nul|com[0-9]|lpt[0-9])(\..*)?$/i;

/**
 * Whether a teammate may share a file under this name: a code file, by a plain relative path
 * inside the lab folder. Names come from teammates, so nothing may point outside it, and
 * nothing may mean something else on Windows.
 */
export function isShareableName(name: string): boolean {
    if (typeof name !== 'string' || name.length === 0 || name.length > 200 || NOT_CODE.test(name)) { return false; }
    if (name.startsWith('/') || name.includes('\\') || name.includes(':') || name.includes('\0')) { return false; }
    return name.split('/').every(part => part !== '' && part !== '.' && part !== '..' && !part.startsWith('.')
        && !part.endsWith('.') && !part.endsWith(' ') && !WINDOWS_RESERVED.test(part));
}
