// "Serviço de push" de verificação: recebe a requisição que o servidor manda para o endpoint do navegador e confere,
// de forma independente da biblioteca PHP, que (1) o cabeçalho VAPID tem uma assinatura ES256 válida para a chave
// pública configurada e (2) o corpo (RFC 8291, aes128gcm) decifra com as chaves do "navegador" para o JSON enviado.
//
// Uso: node tests/e2e/push-service.mjs <porta> <vapidPublicB64url> <receptorD> <receptorX> <receptorY> <authB64url> [timeoutMs]
import crypto from 'node:crypto';
import http from 'node:http';

const [port, vapidPublic, receiverD, receiverX, receiverY, authSecret, timeout = '15000'] = process.argv.slice(2);

const b64 = (value) => Buffer.from(value, 'base64url');
const fail = (message) => {
    console.log(`RESULT ${JSON.stringify({ ok: false, error: message })}`);
    process.exit(1);
};

function verifyVapid(header) {
    const match = /^vapid t=([^,]+), k=(.+)$/.exec(header ?? '');
    if (!match) return 'cabeçalho Authorization fora do formato "vapid t=…, k=…"';

    const [, jwt, k] = match;
    if (k !== vapidPublic) return 'a chave "k" do cabeçalho não é a chave pública VAPID configurada';

    const [head, claims, signature] = jwt.split('.');
    const parsedHead = JSON.parse(b64(head).toString());
    const parsedClaims = JSON.parse(b64(claims).toString());
    if (parsedHead.alg !== 'ES256') return `algoritmo inesperado: ${parsedHead.alg}`;
    if (!String(parsedClaims.aud).startsWith('http://127.0.0.1')) return `aud inesperado: ${parsedClaims.aud}`;
    if (!(parsedClaims.exp > Date.now() / 1000)) return 'o token já expirou';
    if (!String(parsedClaims.sub).startsWith('mailto:') && !String(parsedClaims.sub).startsWith('https:')) return `sub inesperado: ${parsedClaims.sub}`;

    const pub = b64(vapidPublic);
    const key = crypto.createPublicKey({ key: { kty: 'EC', crv: 'P-256', x: pub.subarray(1, 33).toString('base64url'), y: pub.subarray(33, 65).toString('base64url') }, format: 'jwk' });
    const valid = crypto.verify('sha256', Buffer.from(`${head}.${claims}`), { key, dsaEncoding: 'ieee-p1363' }, b64(signature));

    return valid ? null : 'assinatura ES256 inválida';
}

function decrypt(body) {
    const salt = body.subarray(0, 16);
    const idLen = body[20];
    const senderPublic = body.subarray(21, 21 + idLen);
    const encrypted = body.subarray(21 + idLen);

    const receiver = crypto.createECDH('prime256v1');
    receiver.setPrivateKey(b64(receiverD));
    const receiverPublic = Buffer.concat([Buffer.from([4]), b64(receiverX), b64(receiverY)]);
    const shared = receiver.computeSecret(senderPublic);

    const keyInfo = Buffer.concat([Buffer.from('WebPush: info\0'), receiverPublic, senderPublic]);
    const ikm = Buffer.from(crypto.hkdfSync('sha256', shared, b64(authSecret), keyInfo, 32));
    const cek = Buffer.from(crypto.hkdfSync('sha256', ikm, salt, Buffer.from('Content-Encoding: aes128gcm\0'), 16));
    const nonce = Buffer.from(crypto.hkdfSync('sha256', ikm, salt, Buffer.from('Content-Encoding: nonce\0'), 12));

    const tag = encrypted.subarray(encrypted.length - 16);
    const decipher = crypto.createDecipheriv('aes-128-gcm', cek, nonce);
    decipher.setAuthTag(tag);
    const padded = Buffer.concat([decipher.update(encrypted.subarray(0, encrypted.length - 16)), decipher.final()]);

    // Fim do registro: o último 0x02 (registro final) e zeros de preenchimento.
    let end = padded.length - 1;
    while (end >= 0 && padded[end] === 0) end--;
    if (padded[end] !== 2) throw new Error('delimitador do registro final ausente');

    return padded.subarray(0, end).toString('utf8');
}

const server = http.createServer((request, response) => {
    const chunks = [];
    request.on('data', (chunk) => chunks.push(chunk));
    request.on('end', () => {
        try {
            const vapidError = verifyVapid(request.headers.authorization);
            if (vapidError) return fail(vapidError);
            if (request.headers['content-encoding'] !== 'aes128gcm') return fail(`content-encoding inesperado: ${request.headers['content-encoding']}`);
            if (!request.headers.ttl) return fail('cabeçalho TTL ausente');

            const payload = decrypt(Buffer.concat(chunks));
            response.writeHead(201).end();
            console.log(`RESULT ${JSON.stringify({ ok: true, payload: JSON.parse(payload), ttl: request.headers.ttl })}`);
            server.close();
            process.exit(0);
        } catch (error) {
            fail(String(error));
        }
    });
});

server.listen(Number(port), '127.0.0.1', () => console.log('LISTENING'));
setTimeout(() => fail('timeout sem receber a notificação'), Number(timeout));
