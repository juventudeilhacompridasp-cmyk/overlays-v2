import { Select } from '@/Components/Panel/ui';
import { useCan } from '@/lib/can';
import { OPERATOR_CARTELAS, OPERATOR_LAYERS, OPERATOR_NOTICES, OPERATOR_SCREENS, OperatorLayerKey, stepLabel } from '@/lib/operatorConsole';
import { useOnline } from '@/lib/pwa';
import { firstError, nextStoppage, pickChannel, tileHint } from '@/lib/remoteControl';
import { Head, Link, router } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import type { Channel, GameOption, Scene } from './Index';

const CHANNEL_KEY = 'broadcast:remote-channel';
const AWAKE_KEY = 'broadcast:remote-awake';

/** Um toque curto confirma o comando no aparelho (quando o navegador deixa). */
const buzz = () => {
    try {
        navigator.vibrate?.(12);
    } catch {
        /* sem vibração: tudo bem */
    }
};

/** Mantém a tela do aparelho ligada enquanto o controle está aberto (Wake Lock; some sozinho se o navegador não suporta). */
function useWakeLock(enabled: boolean) {
    useEffect(() => {
        const api = (navigator as Navigator & { wakeLock?: { request: (type: 'screen') => Promise<{ release: () => Promise<void> }> } }).wakeLock;

        if (!enabled || !api) return;

        let lock: { release: () => Promise<void> } | null = null;
        let active = true;
        const acquire = () =>
            api
                .request('screen')
                .then((value) => {
                    if (active) lock = value;
                    else void value.release();
                })
                .catch(() => undefined);
        const onVisible = () => document.visibilityState === 'visible' && acquire();

        acquire();
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            active = false;
            document.removeEventListener('visibilitychange', onVisible);
            lock?.release().catch(() => undefined);
        };
    }, [enabled]);
}

function Section({ title, hint, children }: { title: string; hint?: ReactNode; children: ReactNode }) {
    return (
        <section className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-200">
            <div className="mb-2 flex items-baseline justify-between gap-2">
                <h2 className="text-sm font-bold uppercase tracking-wide text-gray-700">{title}</h2>
                {hint && <span className="text-xs font-semibold text-gray-500">{hint}</span>}
            </div>
            {children}
        </section>
    );
}

/** Botão grande liga/desliga: verde e "NO AR" quando ligado. Alvo de toque de 64 px. */
function BigTile({ label, on, hint, disabled, onClick }: { label: string; on: boolean; hint: string; disabled?: boolean; onClick: () => void }) {
    return (
        <button
            type="button"
            aria-pressed={on}
            disabled={disabled}
            onClick={() => {
                buzz();
                onClick();
            }}
            className={`flex min-h-16 w-full touch-manipulation flex-col items-start justify-center rounded-xl border-2 px-3 py-2 text-left transition-colors active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40 ${on ? 'border-emerald-700 bg-emerald-600 text-white' : 'border-gray-300 bg-white text-gray-800'}`}
        >
            <span className="text-base font-bold leading-tight">{label}</span>
            <span className={`text-[11px] font-bold uppercase ${on ? 'text-emerald-100' : 'text-gray-500'}`}>{hint}</span>
        </button>
    );
}

/** Botão de ação (não guarda estado): avisos, cartelas, comandos da escalação. */
function ActionButton({ children, disabled, onClick, tone = 'neutral', className = '' }: { children: ReactNode; disabled?: boolean; onClick: () => void; tone?: 'neutral' | 'primary'; className?: string }) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={() => {
                buzz();
                onClick();
            }}
            className={`min-h-14 touch-manipulation rounded-xl border-2 px-3 text-base font-bold leading-tight active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40 ${tone === 'primary' ? 'border-teal-700 bg-teal-600 text-white' : 'border-gray-300 bg-white text-gray-800'} ${className}`}
        >
            {children}
        </button>
    );
}

function Chip({ active, children, disabled, onClick }: { active?: boolean; children: ReactNode; disabled?: boolean; onClick: () => void }) {
    return (
        <button
            type="button"
            aria-pressed={active}
            disabled={disabled}
            onClick={() => {
                buzz();
                onClick();
            }}
            className={`min-h-12 touch-manipulation rounded-xl border-2 px-4 text-base font-bold disabled:cursor-not-allowed disabled:opacity-40 ${active ? 'border-teal-700 bg-teal-600 text-white' : 'border-gray-300 bg-white text-gray-800'}`}
        >
            {children}
        </button>
    );
}

function Controls({ channel, games, scenes, canOperate, onError }: { channel: Channel; games: GameOption[]; scenes: Scene[]; canOperate: boolean; onError: (message: string | null) => void }) {
    const hasGame = channel.game_id !== null;
    const send = (data: object, rollback?: () => void) =>
        router.patch(route('panel.broadcast.update', channel.id), data as never, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onError(null),
            onError: (errors) => {
                rollback?.();
                onError(firstError(errors) ?? 'O servidor recusou o comando.');
            },
        });

    // Clique vale na hora (otimista) e volta ao estado real se o servidor recusar, como no Modo operador.
    const [layers, setLayers] = useState(channel.layers);
    const [screens, setScreens] = useState(channel.screens_live);
    const [custom, setCustom] = useState(Object.fromEntries(channel.custom_overlays.map((item) => [item.id, item.live])));
    const [sponsorsOn, setSponsorsOn] = useState(channel.sponsor_settings.enabled);
    const [title, setTitle] = useState('');
    const [person, setPerson] = useState('');
    useEffect(() => setLayers(channel.layers), [channel.layers]);
    useEffect(() => setScreens(channel.screens_live), [channel.screens_live]);
    useEffect(() => setCustom(Object.fromEntries(channel.custom_overlays.map((item) => [item.id, item.live]))), [channel.custom_overlays]);
    useEffect(() => setSponsorsOn(channel.sponsor_settings.enabled), [channel.sponsor_settings.enabled]);

    const layerOn = (key: OperatorLayerKey) => (key === 'sponsors' ? sponsorsOn : layers[key]);
    const toggleLayer = (key: OperatorLayerKey) => {
        const next = !layerOn(key);

        if (key === 'sponsors') {
            setSponsorsOn(next);
            send({ sponsor_settings: { enabled: next } }, () => setSponsorsOn(channel.sponsor_settings.enabled));
        } else {
            setLayers({ ...layers, [key]: next });
            send({ layers: { [key]: next } }, () => setLayers(channel.layers));
        }
    };
    const toggleScreen = (key: string) => {
        const next = !screens[key];
        setScreens({ ...screens, [key]: next });
        send({ screens_live: { [key]: next } }, () => setScreens(channel.screens_live));
    };
    const lineup = (command: 'next' | 'previous' | 'reset') => canOperate && hasGame && send({ lineup_command: command });

    const options = hasGame && !games.some((game) => game.id === channel.game_id) ? [{ id: channel.game_id as string, label: channel.game_label ?? 'Jogo atual' }, ...games] : games;
    const settings = channel.lineup_settings;
    const team = ['cards', 'individual', 'show', 'full'].includes(settings.presentation);
    const players = channel.lineup_people;
    const noticesOn = layers.lower_third;

    return (
        <div className="space-y-3">
            <Section title="Jogo no ar">
                <Select
                    aria-label="Jogo no ar"
                    disabled={!canOperate}
                    value={channel.game_id ?? ''}
                    onChange={(event) => send({ game_id: event.target.value || null })}
                >
                    <option value="">Nenhum (telas vazias)</option>
                    {options.map((game) => (
                        <option key={game.id} value={game.id}>
                            {game.label}
                        </option>
                    ))}
                </Select>
                {scenes.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2" aria-label="Cenas">
                        {scenes.map((scene) => (
                            <Chip key={scene.id} disabled={!canOperate} onClick={() => router.patch(route('panel.broadcast.scenes.apply', { channel: channel.id, scene: scene.id }), {}, { preserveScroll: true, preserveState: true })}>
                                Cena: {scene.name}
                            </Chip>
                        ))}
                    </div>
                )}
            </Section>

            <Section title="No ar agora">
                <div className="grid grid-cols-2 gap-2">
                    {OPERATOR_LAYERS.map((layer, index) => (
                        <div key={layer.key} className={index === OPERATOR_LAYERS.length - 1 && OPERATOR_LAYERS.length % 2 === 1 ? 'col-span-2' : ''}>
                            <BigTile label={layer.label} on={layerOn(layer.key)} hint={tileHint(layerOn(layer.key), layer.needsGame, hasGame)} disabled={!canOperate || (layer.needsGame && !hasGame)} onClick={() => toggleLayer(layer.key)} />
                        </div>
                    ))}
                </div>
            </Section>

            <Section title="Escalação" hint={team ? stepLabel(settings.individual_mode, settings.step, channel.lineup_steps) : 'Apresentação sem etapas'}>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <Select aria-label="Apresentação da escalação" disabled={!canOperate} value={settings.presentation} onChange={(event) => send({ lineup_settings: { presentation: event.target.value } })}>
                        <option value="sequence">Lista → esquema</option>
                        <option value="list">Só lista</option>
                        <option value="formation">Só esquema</option>
                        <option value="cards">Cartões</option>
                        <option value="individual">Jogador a jogador</option>
                        <option value="show">Cartões + jogadores</option>
                        <option value="full">Completa</option>
                    </Select>
                    <div className="grid grid-cols-2 gap-2">
                        <Chip active={settings.team === 'home'} disabled={!canOperate || !team} onClick={() => send({ lineup_settings: { team: 'home' } })}>
                            Mandante
                        </Chip>
                        <Chip active={settings.team === 'away'} disabled={!canOperate || !team} onClick={() => send({ lineup_settings: { team: 'away' } })}>
                            Visitante
                        </Chip>
                    </div>
                </div>
                <div className="mt-2 grid grid-cols-2 gap-2">
                    <Chip active={settings.individual_mode === 'manual'} disabled={!canOperate || !team} onClick={() => send({ lineup_settings: { individual_mode: 'manual' } })}>
                        Manual
                    </Chip>
                    <Chip active={settings.individual_mode === 'auto'} disabled={!canOperate || !team} onClick={() => send({ lineup_settings: { individual_mode: 'auto' } })}>
                        Automático
                    </Chip>
                </div>
                {!hasGame && <p className="mt-2 text-sm font-semibold text-amber-700">Escolha o jogo no ar para usar a escalação.</p>}
            </Section>

            <Section title="Avisos na tela" hint={noticesOn ? 'lances no ar' : 'lances OCULTOS: ligue "Lances"'}>
                <div className="grid grid-cols-2 gap-2">
                    {OPERATOR_NOTICES.map((notice) => (
                        <ActionButton key={notice.title} disabled={!canOperate || !noticesOn} onClick={() => send({ lower_third_show: { kind: 'custom', title: notice.title, subtitle: notice.subtitle } })}>
                            {notice.title}
                        </ActionButton>
                    ))}
                </div>
                <form
                    className="mt-2 flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (title.trim() !== '' && canOperate && noticesOn) {
                            send({ lower_third_show: { kind: 'custom', title: title.trim() } });
                            setTitle('');
                        }
                    }}
                >
                    <input
                        aria-label="Aviso livre"
                        value={title}
                        onChange={(event) => setTitle(event.target.value)}
                        maxLength={80}
                        placeholder="Aviso livre"
                        className="min-h-12 min-w-0 flex-1 rounded-xl border-2 border-gray-300 px-3 text-base"
                    />
                    <button
                        type="submit"
                        disabled={!canOperate || !noticesOn || title.trim() === ''}
                        className="min-h-12 touch-manipulation rounded-xl border-2 border-teal-700 bg-teal-600 px-4 text-base font-bold text-white active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Mostrar
                    </button>
                </form>
                <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto]">
                    <Select aria-label="Jogador em destaque" disabled={!canOperate || players.length === 0} value={person} onChange={(event) => setPerson(event.target.value)}>
                        <option value="">{players.length === 0 ? 'Sem escalação lançada' : 'Jogador em destaque…'}</option>
                        {players.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.shirt ? `${item.shirt} · ` : ''}
                                {item.name} ({item.team})
                            </option>
                        ))}
                    </Select>
                    <ActionButton tone="primary" disabled={!canOperate || !noticesOn || person === ''} onClick={() => send({ lower_third_show: { kind: 'player', person_id: person } })}>
                        Destacar jogador
                    </ActionButton>
                </div>
            </Section>

            <Section title="Telas extras">
                <div className="grid grid-cols-2 gap-2">
                    {OPERATOR_SCREENS.map((screen) => (
                        <BigTile key={screen.key} label={screen.label} on={screens[screen.key] === true} hint={tileHint(screens[screen.key] === true, screen.needsGame, hasGame)} disabled={!canOperate || (screen.needsGame && !hasGame)} onClick={() => toggleScreen(screen.key)} />
                    ))}
                </div>
            </Section>

            <Section title="Cartelas prontas">
                <div className="grid grid-cols-2 gap-2">
                    {OPERATOR_CARTELAS.map((item) => (
                        <ActionButton
                            key={item.label}
                            disabled={!canOperate}
                            onClick={() => {
                                setScreens({ ...screens, cartela: true });
                                send({ screen_settings: { title: item.title, subtitle: item.subtitle, minutes: item.minutes }, screens_live: { cartela: true } }, () => setScreens(channel.screens_live));
                            }}
                        >
                            {item.label}
                        </ActionButton>
                    ))}
                </div>
            </Section>

            <Section title="Relógio e saque">
                <div className="flex items-center justify-between gap-3">
                    <span className="text-base font-bold text-gray-800">Acréscimos</span>
                    <div className="flex items-center gap-2">
                        <ActionButton disabled={!canOperate || !hasGame || channel.stoppage <= 0} onClick={() => send({ stoppage_minutes: nextStoppage(channel.stoppage, -1) })} className="w-14 text-2xl">
                            −
                        </ActionButton>
                        <span className="w-12 text-center text-2xl font-black tabular-nums text-gray-900" aria-label="Minutos de acréscimo">
                            {channel.stoppage === 0 ? '—' : `+${channel.stoppage}`}
                        </span>
                        <ActionButton disabled={!canOperate || !hasGame || channel.stoppage >= 15} onClick={() => send({ stoppage_minutes: nextStoppage(channel.stoppage, 1) })} className="w-14 text-2xl">
                            +
                        </ActionButton>
                    </div>
                </div>
                <div className="mt-3 grid grid-cols-3 gap-2">
                    {([[null, 'Sem saque'], ['home', 'Mandante'], ['away', 'Visitante']] as const).map(([side, label]) => (
                        <Chip key={label} active={channel.screen_settings.serve === side} disabled={!canOperate || !hasGame} onClick={() => send({ screen_settings: { serve: side } })}>
                            {label}
                        </Chip>
                    ))}
                </div>
            </Section>

            {channel.custom_overlays.length > 0 && (
                <Section title="Overlays livres">
                    <div className="grid grid-cols-2 gap-2">
                        {channel.custom_overlays.map((item) => (
                            <BigTile
                                key={item.id}
                                label={item.name}
                                on={custom[item.id] === true}
                                hint={custom[item.id] === true ? 'NO AR' : 'Oculto'}
                                disabled={!canOperate}
                                onClick={() => {
                                    const next = !custom[item.id];
                                    setCustom({ ...custom, [item.id]: next });
                                    send({ custom_live: { [item.id]: next } }, () => setCustom(Object.fromEntries(channel.custom_overlays.map((overlay) => [overlay.id, overlay.live]))));
                                }}
                            />
                        ))}
                    </div>
                </Section>
            )}

            {/* Barra fixa: avançar e voltar a escalação é o comando mais repetido ao vivo. */}
            <div className="fixed inset-x-0 bottom-0 z-20 border-t border-gray-300 bg-white/95 px-3 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 shadow-[0_-4px_12px_rgba(0,0,0,0.08)] backdrop-blur" data-testid="remote-lineup-bar">
                <div className="mx-auto grid max-w-3xl grid-cols-[1fr_2fr_1fr] gap-2">
                    <ActionButton disabled={!canOperate || !hasGame || !team} onClick={() => lineup('previous')}>
                        ◀ Voltar
                    </ActionButton>
                    <ActionButton tone="primary" disabled={!canOperate || !hasGame || !team} onClick={() => lineup('next')} className="text-lg">
                        Próximo ▶
                    </ActionButton>
                    <ActionButton disabled={!canOperate || !hasGame || !team} onClick={() => lineup('reset')}>
                        ↺
                    </ActionButton>
                </div>
            </div>
        </div>
    );
}

/** Controle remoto da transmissão: botões grandes para o celular ou tablet na mão do operador, ao lado do campo ou da mesa. */
export default function Remote({ channels, games, scenes }: { channels: Channel[]; games: GameOption[]; scenes: Scene[] }) {
    const canOperate = useCan('operate-games');
    const online = useOnline();
    const [storedId, setStoredId] = useState<string | null>(() => {
        try {
            return window.localStorage.getItem(CHANNEL_KEY);
        } catch {
            return null;
        }
    });
    const [awake, setAwake] = useState<boolean>(() => {
        try {
            return window.localStorage.getItem(AWAKE_KEY) !== 'off';
        } catch {
            return true;
        }
    });
    const [error, setError] = useState<string | null>(null);
    const channel = pickChannel(channels, storedId);
    useWakeLock(awake);

    const choose = (id: string) => {
        setStoredId(id);

        try {
            window.localStorage.setItem(CHANNEL_KEY, id);
        } catch {
            /* sem armazenamento: vale só nesta visita */
        }
    };
    const toggleAwake = () => {
        setAwake(!awake);

        try {
            window.localStorage.setItem(AWAKE_KEY, awake ? 'off' : 'on');
        } catch {
            /* idem */
        }
    };

    return (
        <div className="min-h-screen bg-gray-100 pb-28 text-gray-900" data-testid="remote-control">
            <Head title="Controle remoto" />
            <header className="sticky top-0 z-30 border-b border-gray-300 bg-white/95 px-3 py-2 backdrop-blur">
                <div className="mx-auto flex max-w-3xl flex-wrap items-center gap-2">
                    <Link href={route('panel.broadcast.index')} className="flex min-h-11 items-center rounded-lg border border-gray-300 px-3 text-sm font-bold text-gray-700">
                        ← Transmissão
                    </Link>
                    <h1 className="text-base font-black uppercase tracking-wide">Controle remoto</h1>
                    <span className={`ml-auto inline-flex items-center gap-1 text-xs font-bold ${online ? 'text-emerald-700' : 'text-red-600'}`}>
                        <span className={`h-2.5 w-2.5 rounded-full ${online ? 'bg-emerald-500' : 'bg-red-500'}`} />
                        {online ? 'Conectado' : 'Sem conexão'}
                    </span>
                    <button type="button" aria-pressed={awake} onClick={toggleAwake} className={`min-h-11 rounded-lg border px-3 text-xs font-bold ${awake ? 'border-teal-700 bg-teal-50 text-teal-800' : 'border-gray-300 text-gray-600'}`}>
                        {awake ? 'Tela sempre ligada' : 'Tela pode apagar'}
                    </button>
                </div>
                {channels.length > 1 && (
                    <div className="mx-auto mt-2 flex max-w-3xl flex-wrap gap-2" role="tablist" aria-label="Canal">
                        {channels.map((item) => (
                            <Chip key={item.id} active={item.id === channel?.id} onClick={() => choose(item.id)}>
                                {item.name}
                            </Chip>
                        ))}
                    </div>
                )}
            </header>

            <main className="mx-auto max-w-3xl space-y-3 px-3 py-3">
                {!canOperate && <p className="rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-900 ring-1 ring-amber-200">Você pode ver o estado, mas só quem opera jogos pode mandar comandos.</p>}
                {!online && <p className="rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-800 ring-1 ring-red-200">Sem conexão: os comandos não chegam à transmissão até a internet voltar.</p>}
                {error && (
                    <p role="alert" className="rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-800 ring-1 ring-red-200">
                        {error}
                    </p>
                )}
                {channel ? (
                    <Controls key={channel.id} channel={channel} games={games} scenes={scenes} canOperate={canOperate} onError={setError} />
                ) : (
                    <p className="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-600">Nenhum canal de transmissão criado ainda. Crie um em <Link href={route('panel.broadcast.index')} className="text-emerald-700 underline">Transmissão</Link>.</p>
                )}
            </main>
        </div>
    );
}
