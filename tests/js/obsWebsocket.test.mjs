import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/obsWebsocket.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'obs-')), 'obsWebsocket.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const lib = await import(out);
const { authString, buildIdentify, buildRequest, parseServerMessage, explainClose, normalizeUrl, captureInterval, shortTimecode, ObsConnection, captureProgram, readStatus } = lib;

const sha = (text) => createHash('sha256').update(text).digest('base64');

test('a autenticação segue o obs-websocket v5 (dois SHA-256 em base64)', async () => {
    const expected = sha(sha('segredo' + 'sal') + 'desafio');

    assert.equal(await authString('segredo', 'sal', 'desafio'), expected);
});

test('o Identify só leva autenticação quando o OBS pediu', async () => {
    const without = JSON.parse(await buildIdentify({ kind: 'hello', authentication: null }, 'x'));
    const withAuth = JSON.parse(await buildIdentify({ kind: 'hello', authentication: { salt: 's', challenge: 'c' } }, 'x'));

    assert.equal(without.op, 1);
    assert.equal(without.d.authentication, undefined);
    assert.equal(withAuth.d.authentication, sha(sha('xs') + 'c'));
    assert.equal(withAuth.d.rpcVersion, 1);
});

test('lê as mensagens do servidor e ignora lixo', () => {
    assert.deepEqual(parseServerMessage('{"op":0,"d":{"authentication":{"challenge":"c","salt":"s"}}}'), { kind: 'hello', authentication: { challenge: 'c', salt: 's' } });
    assert.deepEqual(parseServerMessage('{"op":0,"d":{}}'), { kind: 'hello', authentication: null });
    assert.deepEqual(parseServerMessage('{"op":2,"d":{}}'), { kind: 'identified' });
    assert.deepEqual(parseServerMessage('{"op":7,"d":{"requestId":"r1","requestStatus":{"result":true,"code":100},"responseData":{"a":1}}}'), { kind: 'response', requestId: 'r1', ok: true, code: 100, comment: null, data: { a: 1 } });
    assert.equal(parseServerMessage('não é json').kind, 'other');
    assert.equal(JSON.parse(buildRequest('GetStats', 'r9')).d.requestType, 'GetStats');
});

test('explica os fechamentos do OBS em português', () => {
    assert.match(explainClose(4009, false), /Senha/);
    assert.match(explainClose(4010, false), /exige senha/);
    assert.match(explainClose(1006, false), /Não foi possível/);
    assert.match(explainClose(1006, true), /caiu/);
});

test('endereço, intervalo e tempo', () => {
    assert.equal(normalizeUrl(''), 'ws://localhost:4455');
    assert.equal(normalizeUrl('192.168.0.10:4455'), 'ws://192.168.0.10:4455');
    assert.equal(normalizeUrl('wss://obs.exemplo.com/caminho'), 'wss://obs.exemplo.com');
    assert.equal(normalizeUrl('http://x'), null);
    assert.equal(captureInterval(0.1), 500);
    assert.equal(captureInterval(99), 5000);
    assert.equal(captureInterval(Number.NaN), 1000);
    assert.equal(shortTimecode('00:12:34.567'), '00:12:34');
    assert.equal(shortTimecode(null), null);
});

/** Servidor de mentira: responde como o OBS, com ou sem senha. */
function fakeObs({ password = null, scene = 'Jogo', image = 'data:image/jpg;base64,AAAA', closeCode = null } = {}) {
    const log = [];
    const factory = () => {
        const socket = {
            onopen: null, onmessage: null, onclose: null, onerror: null,
            send(text) {
                const message = JSON.parse(text);
                log.push(message);

                if (message.op === 1) {
                    const ok = password === null || message.d.authentication === sha(sha(password + 'salgado') + 'desafiado');

                    if (!ok) setTimeout(() => socket.onclose?.({ code: 4009 }), 0);
                    else setTimeout(() => socket.onmessage?.({ data: JSON.stringify({ op: 2, d: { negotiatedRpcVersion: 1 } }) }), 0);
                }

                if (message.op === 6) {
                    const { requestType, requestId } = message.d;
                    const data = {
                        GetCurrentProgramScene: { currentProgramSceneName: scene },
                        GetSourceScreenshot: { imageData: image },
                        GetStreamStatus: { outputActive: true, outputTimecode: '01:02:03.456' },
                        GetRecordStatus: { outputActive: false, outputTimecode: '00:00:00.000' },
                        GetStats: { activeFps: 59.94, cpuUsage: 12.34, outputSkippedFrames: 3 },
                    }[requestType];
                    const body = data ? { requestId, requestStatus: { result: true, code: 100 }, responseData: data } : { requestId, requestStatus: { result: false, code: 600, comment: 'Pedido desconhecido' } };
                    setTimeout(() => socket.onmessage?.({ data: JSON.stringify({ op: 7, d: body }) }), 0);
                }
            },
            close() {},
        };
        setTimeout(() => {
            if (closeCode !== null) return socket.onclose?.({ code: closeCode });
            socket.onmessage?.({ data: JSON.stringify({ op: 0, d: password === null ? {} : { authentication: { challenge: 'desafiado', salt: 'salgado' } } }) });
        }, 0);

        return socket;
    };

    return { factory, log };
}

test('conecta sem senha, captura a cena no ar e lê o estado', async () => {
    const obs = fakeObs();
    const connection = new ObsConnection('ws://localhost:4455', '', obs.factory, 500);
    await connection.connect();

    const shot = await captureProgram(connection);
    assert.deepEqual(shot, { scene: 'Jogo', image: 'data:image/jpg;base64,AAAA' });

    const status = await readStatus(connection, shot.scene);
    assert.deepEqual(status, { scene: 'Jogo', streaming: true, streamTimecode: '01:02:03', recording: false, recordTimecode: null, fps: 59.9, cpu: 12.3, skippedFrames: 3 });

    const screenshot = obs.log.find((m) => m.d?.requestType === 'GetSourceScreenshot');
    assert.equal(screenshot.d.requestData.sourceName, 'Jogo');
    assert.equal(screenshot.d.requestData.imageWidth, 640);
    connection.close();
});

test('autentica com senha e recusa a senha errada', async () => {
    await new ObsConnection('ws://x', 'certa', fakeObs({ password: 'certa' }).factory, 500).connect();

    await assert.rejects(new ObsConnection('ws://x', 'errada', fakeObs({ password: 'certa' }).factory, 500).connect(), /Senha do OBS incorreta/);
});

test('mensagem clara quando o OBS não responde ou fecha, e pedido recusado vira erro', async () => {
    await assert.rejects(new ObsConnection('ws://x', '', fakeObs({ closeCode: 1006 }).factory, 500).connect(), /Não foi possível falar com o OBS/);

    const connection = new ObsConnection('ws://x', '', fakeObs().factory, 500);
    await connection.connect();
    await assert.rejects(connection.request('PedidoQueNaoExiste'), /Pedido desconhecido/);
    connection.close();
    await assert.rejects(connection.request('GetStats'), /Sem conexão/);
});

test('imagem que não é data URI de imagem é descartada', async () => {
    const connection = new ObsConnection('ws://x', '', fakeObs({ image: 'javascript:alert(1)' }).factory, 500);
    await connection.connect();

    assert.deepEqual(await captureProgram(connection), { scene: 'Jogo', image: null });
    connection.close();
});
