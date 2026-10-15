/**
 * Cliente do obs-websocket v5 (OBS Studio 28+), código sem React e testado em Node. Roda NO NAVEGADOR DO OPERADOR e fala
 * direto com o OBS da própria máquina (`ws://localhost:4455`): o servidor da plataforma nunca vê o endereço, a senha nem a
 * imagem. Serve para o monitor "OBS (saída real)" do Modo operador: cena no ar, captura da saída, transmissão e gravação.
 *
 * Protocolo: o OBS manda `Hello` (op 0, com `authentication` quando há senha); respondemos `Identify` (op 1); ele devolve
 * `Identified` (op 2). Depois cada pedido é `Request` (op 6) com um `requestId` e volta como `RequestResponse` (op 7).
 */

export const DEFAULT_URL = 'ws://localhost:4455';
export const RPC_VERSION = 1;

/** Códigos de fechamento do obs-websocket que a tela explica em português. */
const CLOSE_MESSAGES: Record<number, string> = {
    4004: 'O OBS recusou a conexão: servidor WebSocket desligado ou sem permissão.',
    4005: 'O OBS recusou a mensagem enviada.',
    4009: 'Senha do OBS incorreta.',
    4010: 'O OBS exige senha: informe a senha do servidor WebSocket.',
    4011: 'O OBS encerrou a sessão (reiniciado ou desconectado).',
};

export type ObsStatus = {
    scene: string | null;
    streaming: boolean;
    streamTimecode: string | null;
    recording: boolean;
    recordTimecode: string | null;
    fps: number | null;
    cpu: number | null;
    skippedFrames: number | null;
};

export const emptyStatus = (): ObsStatus => ({ scene: null, streaming: false, streamTimecode: null, recording: false, recordTimecode: null, fps: null, cpu: null, skippedFrames: null });

export type ServerMessage =
    | { kind: 'hello'; authentication: { challenge: string; salt: string } | null }
    | { kind: 'identified' }
    | { kind: 'response'; requestId: string; ok: boolean; code: number; comment: string | null; data: Record<string, unknown> }
    | { kind: 'other' };

const toBase64 = (bytes: ArrayBuffer): string => {
    let binary = '';
    new Uint8Array(bytes).forEach((byte) => (binary += String.fromCharCode(byte)));

    return btoa(binary);
};

const sha256 = async (text: string): Promise<ArrayBuffer> => crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));

/** base64(sha256(base64(sha256(senha + salt)) + desafio)), como pede o obs-websocket v5. */
export async function authString(password: string, salt: string, challenge: string): Promise<string> {
    const secret = toBase64(await sha256(password + salt));

    return toBase64(await sha256(secret + challenge));
}

export async function buildIdentify(hello: Extract<ServerMessage, { kind: 'hello' }>, password: string): Promise<string> {
    const data: Record<string, unknown> = { rpcVersion: RPC_VERSION, eventSubscriptions: 0 };

    if (hello.authentication !== null) data.authentication = await authString(password, hello.authentication.salt, hello.authentication.challenge);

    return JSON.stringify({ op: 1, d: data });
}

export const buildRequest = (type: string, requestId: string, data?: Record<string, unknown>): string =>
    JSON.stringify({ op: 6, d: { requestType: type, requestId, ...(data ? { requestData: data } : {}) } });

export function parseServerMessage(raw: unknown): ServerMessage {
    try {
        const message = JSON.parse(String(raw)) as { op?: number; d?: Record<string, unknown> };
        const d = message.d ?? {};

        if (message.op === 0) {
            const auth = d.authentication as { challenge?: unknown; salt?: unknown } | undefined;

            return { kind: 'hello', authentication: auth && typeof auth.challenge === 'string' && typeof auth.salt === 'string' ? { challenge: auth.challenge, salt: auth.salt } : null };
        }

        if (message.op === 2) return { kind: 'identified' };

        if (message.op === 7) {
            const status = (d.requestStatus ?? {}) as { result?: boolean; code?: number; comment?: string };

            return { kind: 'response', requestId: String(d.requestId ?? ''), ok: status.result === true, code: Number(status.code ?? 0), comment: status.comment ?? null, data: (d.responseData ?? {}) as Record<string, unknown> };
        }
    } catch {
        /* mensagem que não é JSON: ignora */
    }

    return { kind: 'other' };
}

export function explainClose(code: number, wasConnected: boolean): string {
    if (CLOSE_MESSAGES[code]) return CLOSE_MESSAGES[code];

    return wasConnected
        ? 'A conexão com o OBS caiu. O OBS está aberto e com o servidor WebSocket ligado?'
        : 'Não foi possível falar com o OBS. Confira se ele está aberto, se o servidor WebSocket está ligado (Ferramentas > Configurações do servidor WebSocket) e se o endereço está certo.';
}

/** Aceita só `ws://` ou `wss://` com host; devolve o endereço limpo ou null. */
export function normalizeUrl(input: string): string | null {
    const text = input.trim();

    if (text === '') return DEFAULT_URL;

    if (/^[a-z][a-z0-9+.-]*:\/\//i.test(text) && !/^wss?:\/\//i.test(text)) return null;

    const withScheme = /^wss?:\/\//i.test(text) ? text : `ws://${text}`;

    try {
        const url = new URL(withScheme);

        return (url.protocol === 'ws:' || url.protocol === 'wss:') && url.hostname !== '' ? `${url.protocol}//${url.host}` : null;
    } catch {
        return null;
    }
}

/** Intervalo da captura em milissegundos, entre 500 e 5000. */
export const captureInterval = (seconds: number): number => Math.round(Math.max(0.5, Math.min(5, Number.isFinite(seconds) ? seconds : 1)) * 1000);

/** "00:12:34.567" -> "00:12:34". */
export const shortTimecode = (value: unknown): string | null => (typeof value === 'string' && value !== '' ? value.split('.')[0] : null);

type SocketLike = { onopen: ((event: unknown) => void) | null; onmessage: ((event: { data: unknown }) => void) | null; onclose: ((event: { code: number }) => void) | null; onerror: ((event: unknown) => void) | null; send: (data: string) => void; close: () => void };
export type SocketFactory = (url: string) => SocketLike;

type Pending = { resolve: (data: Record<string, unknown>) => void; reject: (error: Error) => void; timer: ReturnType<typeof setTimeout> };

/** Uma conexão: conecta, autentica e faz pedidos. Troque `factory` nos testes por um servidor de mentira. */
export class ObsConnection {
    private socket: SocketLike | null = null;
    private pending = new Map<string, Pending>();
    private counter = 0;
    private identified = false;

    constructor(
        private readonly url: string,
        private readonly password: string,
        private readonly factory: SocketFactory = (address) => new WebSocket(address) as unknown as SocketLike,
        private readonly timeoutMs = 5000,
    ) {}

    /** Resolve quando o OBS confirma a identificação; rejeita com uma mensagem em português. */
    connect(): Promise<void> {
        return new Promise((resolve, reject) => {
            let settled = false;
            const fail = (message: string) => {
                if (!settled) {
                    settled = true;
                    reject(new Error(message));
                }
            };

            try {
                this.socket = this.factory(this.url);
            } catch {
                fail(explainClose(0, false));

                return;
            }

            const timer = setTimeout(() => {
                fail(explainClose(0, false));
                this.close();
            }, this.timeoutMs);

            this.socket.onerror = () => {
                /* o motivo vem no onclose */
            };
            this.socket.onclose = (event) => {
                clearTimeout(timer);
                fail(explainClose(event.code, this.identified));
                this.rejectAll(explainClose(event.code, this.identified));
                this.identified = false;
            };
            this.socket.onmessage = async (event) => {
                const message = parseServerMessage(event.data);

                if (message.kind === 'hello') {
                    this.socket?.send(await buildIdentify(message, this.password));
                } else if (message.kind === 'identified') {
                    this.identified = true;
                    clearTimeout(timer);
                    if (!settled) {
                        settled = true;
                        resolve();
                    }
                } else if (message.kind === 'response') {
                    const waiting = this.pending.get(message.requestId);

                    if (waiting) {
                        clearTimeout(waiting.timer);
                        this.pending.delete(message.requestId);
                        if (message.ok) waiting.resolve(message.data);
                        else waiting.reject(new Error(message.comment ?? `O OBS recusou o pedido (código ${message.code}).`));
                    }
                }
            };
        });
    }

    request(type: string, data?: Record<string, unknown>): Promise<Record<string, unknown>> {
        return new Promise((resolve, reject) => {
            if (!this.socket || !this.identified) {
                reject(new Error('Sem conexão com o OBS.'));

                return;
            }

            const id = `r${++this.counter}`;
            const timer = setTimeout(() => {
                this.pending.delete(id);
                reject(new Error('O OBS não respondeu a tempo.'));
            }, this.timeoutMs);

            this.pending.set(id, { resolve, reject, timer });
            this.socket.send(buildRequest(type, id, data));
        });
    }

    close(): void {
        this.rejectAll('Conexão encerrada.');
        this.identified = false;
        this.socket?.close();
        this.socket = null;
    }

    private rejectAll(message: string): void {
        this.pending.forEach((entry) => {
            clearTimeout(entry.timer);
            entry.reject(new Error(message));
        });
        this.pending.clear();
    }
}

/** Foto da saída (JPEG pequeno) da cena no ar; devolve a cena e a imagem como data URI. */
export async function captureProgram(connection: ObsConnection, width = 640): Promise<{ scene: string; image: string | null }> {
    const current = await connection.request('GetCurrentProgramScene');
    const scene = String(current.currentProgramSceneName ?? current.sceneName ?? '');

    if (scene === '') return { scene: '', image: null };

    const shot = await connection.request('GetSourceScreenshot', { sourceName: scene, imageFormat: 'jpg', imageWidth: width, imageHeight: Math.round((width * 9) / 16), imageCompressionQuality: 60 });
    const image = typeof shot.imageData === 'string' && shot.imageData.startsWith('data:image/') ? shot.imageData : null;

    return { scene, image };
}

/** Estado leve (transmissão, gravação e desempenho); falha em um pedido não derruba os outros. */
export async function readStatus(connection: ObsConnection, scene: string | null): Promise<ObsStatus> {
    const status = emptyStatus();
    status.scene = scene;

    const [stream, record, stats] = await Promise.allSettled([connection.request('GetStreamStatus'), connection.request('GetRecordStatus'), connection.request('GetStats')]);

    if (stream.status === 'fulfilled') {
        status.streaming = stream.value.outputActive === true;
        status.streamTimecode = status.streaming ? shortTimecode(stream.value.outputTimecode) : null;
    }

    if (record.status === 'fulfilled') {
        status.recording = record.value.outputActive === true;
        status.recordTimecode = status.recording ? shortTimecode(record.value.outputTimecode) : null;
    }

    if (stats.status === 'fulfilled') {
        status.fps = typeof stats.value.activeFps === 'number' ? Math.round(stats.value.activeFps * 10) / 10 : null;
        status.cpu = typeof stats.value.cpuUsage === 'number' ? Math.round(stats.value.cpuUsage * 10) / 10 : null;
        status.skippedFrames = typeof stats.value.outputSkippedFrames === 'number' ? stats.value.outputSkippedFrames : null;
    }

    return status;
}
