"use strict";
/**
 * Each lab's files live in a folder of their own inside the workspace, named after the lab:
 * "java-oop-basics-lab12" for lab 12, "Java OOP Basics". The "-lab12" ending is what finds the
 * folder again, so a resume keeps using it even after the instructor renames the lab.
 */
Object.defineProperty(exports, "__esModule", { value: true });
exports.relativePathInside = exports.isLabFolderFor = exports.labFolderName = exports.labIdOf = void 0;
// Long enough to recognise the lab, short enough to keep paths well under Windows' limit.
const MAX_TITLE_LENGTH = 40;
/** The lab's id, or undefined when the server didn't send a usable one. */
function labIdOf(lab) {
    const id = Number(lab?.id);
    return Number.isInteger(id) && id > 0 ? id : undefined;
}
exports.labIdOf = labIdOf;
/**
 * The folder name for a new lab folder. Only lowercase letters, digits and dashes, so it is
 * the same on Windows, macOS and Linux, needs no quotes in a terminal, and can't be a name
 * Windows reserves (CON, NUL...).
 */
function labFolderName(title, id) {
    const slug = String(title ?? '')
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+/, '')
        .slice(0, MAX_TITLE_LENGTH)
        .replace(/-+$/, '');
    return slug ? `${slug}-lab${id}` : `lab${id}`;
}
exports.labFolderName = labFolderName;
/** Whether a folder name is this lab's folder, whatever title it was made under. */
function isLabFolderFor(name, id) {
    const lower = name.toLowerCase();
    return lower === `lab${id}` || lower.endsWith(`-lab${id}`);
}
exports.isLabFolderFor = isLabFolderFor;
/**
 * A file's path relative to a folder, with "/" separators, or undefined when the file is not
 * inside it. Windows paths are compared without case, as the file system does.
 */
function relativePathInside(folderPath, filePath, windows = process.platform === 'win32') {
    const folder = folderPath.replace(/\\/g, '/').replace(/\/+$/, '') + '/';
    const file = filePath.replace(/\\/g, '/');
    const inside = windows ? file.toLowerCase().startsWith(folder.toLowerCase()) : file.startsWith(folder);
    return inside && file.length > folder.length ? file.slice(folder.length) : undefined;
}
exports.relativePathInside = relativePathInside;
//# sourceMappingURL=labFolder.js.map