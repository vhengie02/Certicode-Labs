"use strict";
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __setModuleDefault = (this && this.__setModuleDefault) || (Object.create ? (function(o, v) {
    Object.defineProperty(o, "default", { enumerable: true, value: v });
}) : function(o, v) {
    o["default"] = v;
});
var __importStar = (this && this.__importStar) || function (mod) {
    if (mod && mod.__esModule) return mod;
    var result = {};
    if (mod != null) for (var k in mod) if (k !== "default" && Object.prototype.hasOwnProperty.call(mod, k)) __createBinding(result, mod, k);
    __setModuleDefault(result, mod);
    return result;
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.open = exports.seal = void 0;
const crypto = __importStar(require("crypto"));
/**
 * Sealing for what teammates send each other. The channel's name is a secret, but a secret
 * that leaks must not expose the team's code or let someone else write into it, so every
 * message is encrypted and authenticated with the team attempt's key (AES-256-GCM).
 *
 * Wire form: base64(nonce || ciphertext || tag).
 */
const NONCE_BYTES = 12;
const TAG_BYTES = 16;
const KEY_BYTES = 32;
function seal(key, plain) {
    if (key.length !== KEY_BYTES) {
        throw new Error('The team key must be 32 bytes.');
    }
    const nonce = crypto.randomBytes(NONCE_BYTES);
    const cipher = crypto.createCipheriv('aes-256-gcm', key, nonce);
    const ciphertext = Buffer.concat([cipher.update(plain), cipher.final()]);
    return Buffer.concat([nonce, ciphertext, cipher.getAuthTag()]).toString('base64');
}
exports.seal = seal;
/**
 * The bytes inside a sealed message, or null when it isn't one of ours: wrong key, altered,
 * cut short, or not a message at all. Never throws, since anyone can put anything on the channel.
 */
function open(key, sealed) {
    if (typeof sealed !== 'string' || key.length !== KEY_BYTES || !/^[A-Za-z0-9+/]+={0,2}$/.test(sealed)) {
        return null;
    }
    const raw = Buffer.from(sealed, 'base64');
    if (raw.length < NONCE_BYTES + TAG_BYTES) {
        return null;
    }
    try {
        const decipher = crypto.createDecipheriv('aes-256-gcm', key, raw.subarray(0, NONCE_BYTES));
        decipher.setAuthTag(raw.subarray(raw.length - TAG_BYTES));
        const plain = Buffer.concat([decipher.update(raw.subarray(NONCE_BYTES, raw.length - TAG_BYTES)), decipher.final()]);
        return new Uint8Array(plain);
    }
    catch {
        return null;
    }
}
exports.open = open;
//# sourceMappingURL=seal.js.map