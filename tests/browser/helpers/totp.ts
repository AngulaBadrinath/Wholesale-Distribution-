import crypto from 'crypto';

/**
 * Decode standard RFC 4648 Base32 string into a Buffer.
 */
export function base32Decode(base32: string): Buffer {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const cleaned = base32.toUpperCase().replace(/[\s=]/g, '');
    let bits = '';
    for (const char of cleaned) {
        const val = alphabet.indexOf(char);
        if (val === -1) continue;
        bits += val.toString(2).padStart(5, '0');
    }
    const bytes: number[] = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) {
        bytes.push(parseInt(bits.substring(i, i + 8), 2));
    }
    return Buffer.from(bytes);
}

/**
 * Generate a standard 6-digit TOTP code (RFC 6238) from a Base32 secret key.
 */
export function generateTOTP(secret: string): string {
    const key = base32Decode(secret);
    const epoch = Math.floor(Date.now() / 1000);
    const timeStep = Math.floor(epoch / 30);
    const timeBuffer = Buffer.alloc(8);
    timeBuffer.writeBigInt64BE(BigInt(timeStep), 0);

    const hmac = crypto.createHmac('sha1', key).update(timeBuffer).digest();
    const offset = hmac[hmac.length - 1] & 0x0f;
    const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1000000;
    return code.toString().padStart(6, '0');
}
