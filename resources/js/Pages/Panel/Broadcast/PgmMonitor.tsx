import { useEffect, useRef, useState } from 'react';

type Backdrop = 'dark' | 'checker';

const BACKDROPS: { key: Backdrop; label: string }[] = [
    { key: 'dark', label: 'Escuro' },
    { key: 'checker', label: 'Xadrez' },
];
const STORAGE_KEY = 'broadcast:pgm-backdrop';

const CHECKER = 'bg-white [background-image:linear-gradient(45deg,#d1d5db_25%,transparent_25%),linear-gradient(-45deg,#d1d5db_25%,transparent_25%),linear-gradient(45deg,transparent_75%,#d1d5db_75%),linear-gradient(-45deg,transparent_75%,#d1d5db_75%)] [background-position:0_0,0_8px,8px_-8px,-8px_0] [background-size:16px_16px]';

/** Escolha de fundo guardada neste navegador; sem armazenamento vale o escuro. */
const readBackdrop = (): Backdrop => {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === 'checker' ? 'checker' : 'dark';
    } catch {
        return 'dark';
    }
};

/** Controles do cabeçalho do bloco: fundo da prévia e congelar. */
export function PgmControls({ backdrop, onBackdrop, frozen, onFrozen }: { backdrop: Backdrop; onBackdrop: (next: Backdrop) => void; frozen: boolean; onFrozen: (next: boolean) => void }) {
    return (
        <span className="flex items-center gap-1">
            <button type="button" aria-pressed={frozen} onClick={() => onFrozen(!frozen)} title="Para a prévia para economizar o computador" className={`rounded border px-1.5 text-[10px] font-bold uppercase ${frozen ? 'border-amber-600 bg-amber-100 text-amber-800' : 'border-gray-300 text-gray-500 hover:bg-gray-50'}`}>{frozen ? 'Congelado' : 'Congelar'}</button>
            <select aria-label="Fundo do monitor" value={backdrop} onChange={(event) => onBackdrop(event.target.value as Backdrop)} className="h-5 rounded border border-gray-300 bg-white py-0 pl-1 pr-5 text-[10px] font-semibold text-gray-600">
                {BACKDROPS.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}
            </select>
        </span>
    );
}

/** Estado do monitor: fundo e congelar ficam no pai para o título do bloco poder mostrar os controles. */
export function usePgmState() {
    const [backdrop, setBackdrop] = useState<Backdrop>(readBackdrop);
    const [frozen, setFrozen] = useState(false);
    const choose = (next: Backdrop) => {
        setBackdrop(next);

        try {
            window.localStorage.setItem(STORAGE_KEY, next);
        } catch {
            /* sem armazenamento: vale só nesta visita */
        }
    };

    return { backdrop, setBackdrop: choose, frozen, setFrozen };
}

/**
 * Monitor PGM: a página `/overlay/{token}/pgm` (todas as telas ligadas do canal, uma só conexão) num canvas lógico de
 * 1920 x 1080 reduzido para caber no bloco. Só um iframe por canal; fica parado com a aba oculta ou congelado.
 */
export default function PgmMonitor({ url, hasGame, backdrop, frozen, preview }: { url: string; hasGame: boolean; backdrop: Backdrop; frozen: boolean; preview?: { key: string | null } }) {
    const box = useRef<HTMLDivElement>(null);
    const frame = useRef<HTMLIFrameElement>(null);
    const isPreview = preview !== undefined;
    const previewKey = preview?.key ?? '';
    // PVW: a página fica carregada e recebe a chave nova por mensagem (mesma origem), sem recarregar.
    const sendKey = () => frame.current?.contentWindow?.postMessage({ type: 'pvw', key: previewKey }, window.location.origin);
    const [size, setSize] = useState({ width: 0, height: 0 });
    const [visible, setVisible] = useState(() => typeof document === 'undefined' || !document.hidden);

    useEffect(() => {
        const element = box.current;

        if (!element) return;

        const measure = () => setSize({ width: element.clientWidth, height: element.clientHeight });
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(element);

        return () => observer.disconnect();
    }, []);
    useEffect(() => {
        const update = () => setVisible(!document.hidden);
        document.addEventListener('visibilitychange', update);

        return () => document.removeEventListener('visibilitychange', update);
    }, []);

    useEffect(sendKey, [previewKey]); // eslint-disable-line react-hooks/exhaustive-deps

    const scale = Math.min(size.width / 1920, size.height / 1080);
    const width = 1920 * scale;
    const height = 1080 * scale;
    const running = visible && !frozen && scale > 0;

    return (
        <div ref={box} className="relative flex h-full min-h-[3rem] items-center justify-center" data-testid={isPreview ? 'operator-pvw' : 'operator-pgm'}>
            {scale > 0 && (
                <div className={`relative overflow-hidden rounded ${backdrop === 'checker' ? CHECKER : 'bg-slate-950'}`} style={{ width, height }} aria-label={isPreview ? 'Monitor PVW: prévia do que iria ao ar' : 'Monitor PGM: o que está no ar'}>
                    {running && <iframe ref={frame} key={url} src={url} title={isPreview ? 'PVW' : 'PGM'} onLoad={sendKey} tabIndex={-1} width={1920} height={1080} className="pointer-events-none absolute left-0 top-0 border-0" style={{ transform: `scale(${scale})`, transformOrigin: 'top left' }} />}
                    {!running && <div className="absolute inset-0 grid place-items-center px-2 text-center text-[11px] font-semibold text-white/70">{frozen ? 'Prévia congelada' : 'Prévia em pausa (aba oculta)'}</div>}
                    {running && !hasGame && !isPreview && <div className="pointer-events-none absolute inset-x-0 bottom-1 text-center text-[10px] font-semibold text-white/50">Sem jogo no ar</div>}
                    {isPreview && running && preview.key === null && <div className="pointer-events-none absolute inset-x-0 top-1 text-center text-[10px] font-semibold text-white/60">Passe o mouse num botão de camada ou tela</div>}
                    <span className={`pointer-events-none absolute bottom-1.5 left-1.5 rounded px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-white ${isPreview ? 'bg-amber-600' : 'bg-red-600'}`}>{isPreview ? 'PVW' : 'PGM'}</span>
                </div>
            )}
        </div>
    );
}
