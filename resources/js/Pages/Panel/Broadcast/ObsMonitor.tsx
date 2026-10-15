import { captureInterval, captureProgram, DEFAULT_URL, emptyStatus, normalizeUrl, ObsConnection, ObsStatus, readStatus } from '@/lib/obsWebsocket';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

type Phase = 'idle' | 'connecting' | 'connected' | 'error';

const URL_KEY = 'broadcast:obs-url';
const INTERVAL_KEY = 'broadcast:obs-interval';

const read = (key: string, fallback: string): string => {
    try {
        return window.localStorage.getItem(key) ?? fallback;
    } catch {
        return fallback;
    }
};
const write = (key: string, value: string): void => {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        /* sem armazenamento: vale só nesta visita */
    }
};

/**
 * Estado do monitor "OBS (saída real)": conecta o NAVEGADOR do operador ao obs-websocket da própria máquina e mostra uma
 * foto da cena no ar a cada poucos segundos, mais transmissão, gravação e desempenho. O endereço fica neste navegador
 * (`localStorage`); a senha só na memória desta aba; nada passa pelo servidor da plataforma.
 */
export function useObsMonitor() {
    const [phase, setPhase] = useState<Phase>('idle');
    const [error, setError] = useState<string | null>(null);
    const [image, setImage] = useState<string | null>(null);
    const [status, setStatus] = useState<ObsStatus>(emptyStatus);
    const [url, setUrl] = useState(() => read(URL_KEY, DEFAULT_URL));
    const [seconds, setSeconds] = useState(() => Number(read(INTERVAL_KEY, '1')) || 1);
    const [frozen, setFrozen] = useState(false);
    const connection = useRef<ObsConnection | null>(null);
    const timer = useRef<number>();
    const run = useRef(0);
    const options = useRef({ seconds, frozen });
    options.current = { seconds, frozen };

    const stop = useCallback(() => {
        run.current += 1;
        window.clearTimeout(timer.current);
        connection.current?.close();
        connection.current = null;
    }, []);

    useEffect(() => stop, [stop]);

    const loop = useCallback((id: number, active: ObsConnection, tick: number) => {
        const step = async () => {
            if (run.current !== id) return;

            if (!document.hidden && !options.current.frozen) {
                try {
                    const shot = await captureProgram(active);
                    if (run.current !== id) return;
                    setImage(shot.image);
                    // Estado de transmissão/gravação/desempenho a cada 5 capturas (não precisa de tempo real).
                    if (tick % 5 === 0) setStatus(await readStatus(active, shot.scene));
                    else setStatus((previous) => ({ ...previous, scene: shot.scene }));
                    tick += 1;
                } catch (failure) {
                    if (run.current !== id) return;
                    stop();
                    setPhase('error');
                    setError(failure instanceof Error ? failure.message : 'Falha ao falar com o OBS.');

                    return;
                }
            }

            timer.current = window.setTimeout(step, captureInterval(options.current.seconds));
        };

        void step();
    }, [stop]);

    const connect = useCallback(async (password: string) => {
        const address = normalizeUrl(url);

        if (address === null) {
            setPhase('error');
            setError('Endereço inválido. Use algo como ws://localhost:4455.');

            return;
        }

        stop();
        const id = run.current;
        const active = new ObsConnection(address, password);
        connection.current = active;
        setPhase('connecting');
        setError(null);
        write(URL_KEY, address);

        try {
            await active.connect();
        } catch (failure) {
            if (run.current !== id) return;
            stop();
            setPhase('error');
            setError(failure instanceof Error ? failure.message : 'Não foi possível conectar ao OBS.');

            return;
        }

        if (run.current !== id) return;
        setPhase('connected');
        loop(id, active, 0);
    }, [url, stop, loop]);

    const disconnect = useCallback(() => {
        stop();
        setPhase('idle');
        setImage(null);
        setStatus(emptyStatus());
    }, [stop]);

    const changeSeconds = (next: number) => {
        setSeconds(next);
        write(INTERVAL_KEY, String(next));
    };

    return { phase, error, image, status, url, setUrl, seconds, setSeconds: changeSeconds, frozen, setFrozen, connect, disconnect };
}

type Monitor = ReturnType<typeof useObsMonitor>;

/** Controles do cabeçalho do bloco: estado da conexão, congelar e desconectar. */
export function ObsControls({ monitor }: { monitor: Monitor }) {
    if (monitor.phase !== 'connected') {
        return <span className="text-[10px] font-bold uppercase text-gray-400">{monitor.phase === 'connecting' ? 'Conectando…' : 'Desconectado'}</span>;
    }

    return (
        <span className="flex items-center gap-1">
            <button type="button" aria-pressed={monitor.frozen} onClick={() => monitor.setFrozen(!monitor.frozen)} className={`rounded border px-1.5 text-[10px] font-bold uppercase ${monitor.frozen ? 'border-amber-600 bg-amber-100 text-amber-800' : 'border-gray-300 text-gray-500 hover:bg-gray-50'}`}>{monitor.frozen ? 'Congelado' : 'Congelar'}</button>
            <button type="button" onClick={monitor.disconnect} className="rounded border border-gray-300 px-1.5 text-[10px] font-bold uppercase text-gray-500 hover:bg-gray-50">Desconectar</button>
        </span>
    );
}

const Chip = ({ tone, children }: { tone: 'red' | 'gray' | 'green'; children: React.ReactNode }) => (
    <span className={`rounded px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-white ${tone === 'red' ? 'bg-red-600' : tone === 'green' ? 'bg-emerald-600' : 'bg-neutral-700'}`}>{children}</span>
);

/** Corpo do bloco: formulário de conexão ou a foto da saída com o estado do OBS. */
export default function ObsMonitor({ monitor }: { monitor: Monitor }) {
    const [password, setPassword] = useState('');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        void monitor.connect(password);
    };

    if (monitor.phase !== 'connected') {
        return (
            <form onSubmit={submit} className="space-y-1.5 text-xs" data-testid="operator-obs-form">
                <p className="text-[11px] leading-snug text-gray-500">Mostra a saída real do OBS desta máquina. No OBS: Ferramentas &gt; Configurações do servidor WebSocket. A conexão sai direto do seu navegador; a senha não é guardada nem enviada à plataforma.</p>
                <label className="block font-semibold text-gray-600">Endereço
                    <input value={monitor.url} onChange={(event) => monitor.setUrl(event.target.value)} className="mt-0.5 h-7 w-full rounded border border-gray-300 px-2 text-xs" placeholder="ws://localhost:4455" autoComplete="off" />
                </label>
                <label className="block font-semibold text-gray-600">Senha do OBS
                    <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} className="mt-0.5 h-7 w-full rounded border border-gray-300 px-2 text-xs" autoComplete="off" />
                </label>
                <label className="block font-semibold text-gray-600">Atualizar a cada
                    <select value={monitor.seconds} onChange={(event) => monitor.setSeconds(Number(event.target.value))} className="mt-0.5 h-7 w-full rounded border border-gray-300 bg-white px-1 text-xs">
                        {[0.5, 1, 2, 5].map((value) => <option key={value} value={value}>{value} s</option>)}
                    </select>
                </label>
                {monitor.error && <p role="alert" className="rounded bg-red-50 px-2 py-1 text-[11px] font-semibold text-red-700" data-testid="operator-obs-error">{monitor.error}</p>}
                <button type="submit" disabled={monitor.phase === 'connecting'} className="h-8 w-full rounded-md border border-teal-700 bg-teal-600 text-xs font-bold text-white hover:bg-teal-700 disabled:opacity-50">{monitor.phase === 'connecting' ? 'Conectando…' : 'Conectar ao OBS'}</button>
            </form>
        );
    }

    const { status } = monitor;

    return (
        <div className="flex h-full min-h-0 flex-col gap-1" data-testid="operator-obs">
            <div className="relative min-h-0 flex-1 overflow-hidden rounded bg-slate-950">
                {monitor.image ? <img src={monitor.image} alt="Saída real do OBS (cena no ar)" className="h-full w-full object-contain" /> : <div className="grid h-full place-items-center text-[11px] font-semibold text-white/60">Aguardando a primeira imagem…</div>}
                <span className="pointer-events-none absolute bottom-1.5 left-1.5 rounded bg-sky-600 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-white">OBS</span>
                {monitor.frozen && <span className="pointer-events-none absolute right-1.5 top-1.5 rounded bg-amber-600 px-1.5 py-0.5 text-[9px] font-black uppercase text-white">Congelado</span>}
            </div>
            <div className="flex flex-wrap items-center gap-1">
                <Chip tone="gray">{status.scene ?? 'sem cena'}</Chip>
                <Chip tone={status.streaming ? 'red' : 'gray'}>{status.streaming ? `Ao vivo ${status.streamTimecode ?? ''}` : 'Fora do ar'}</Chip>
                <Chip tone={status.recording ? 'green' : 'gray'}>{status.recording ? `Gravando ${status.recordTimecode ?? ''}` : 'Sem gravação'}</Chip>
                {status.fps !== null && <span className="text-[10px] font-semibold text-gray-500">{status.fps} fps · CPU {status.cpu ?? '–'}%{status.skippedFrames ? ` · ${status.skippedFrames} quadros perdidos` : ''}</span>}
            </div>
        </div>
    );
}
