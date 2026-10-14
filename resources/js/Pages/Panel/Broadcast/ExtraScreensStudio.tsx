import { Button, Field, Input } from '@/Components/Panel/ui';
import { useEffect, useState } from 'react';

interface CartelaSettings {
    title: string;
    subtitle: string;
    until_ms: number | null;
}

type Patch = (data: { screens_live?: Record<string, boolean>; screen_settings?: { title?: string; subtitle?: string; minutes?: number } }) => void;

const SCREENS: { key: string; label: string; hint: string; needsGame: boolean }[] = [
    { key: 'pregame', label: 'Cartela pré-jogo', hint: 'Escudos, competição, local, horário e contagem regressiva para o início.', needsGame: true },
    { key: 'compare', label: 'Comparativo de equipes', hint: 'Posição na tabela, forma recente e confronto direto.', needsGame: true },
    { key: 'cartela', label: 'Cartela de transmissão', hint: 'Tela cheia com título, contagem e patrocinadores.', needsGame: false },
];

const PRESETS: { label: string; title: string; subtitle: string; minutes: number }[] = [
    { label: 'Começamos em breve', title: 'Começamos em breve', subtitle: 'Fique ligado na transmissão', minutes: 10 },
    { label: 'Intervalo', title: 'Intervalo', subtitle: 'Voltamos já', minutes: 15 },
    { label: 'Voltamos já', title: 'Voltamos já', subtitle: '', minutes: 0 },
    { label: 'Fim da transmissão', title: 'Obrigado por assistir', subtitle: 'Até o próximo jogo', minutes: 0 },
];

export default function ExtraScreensStudio({ channelId, screens, settings, hasGame, canOperate, patch }: { channelId: string; screens: Record<string, boolean>; settings: CartelaSettings; hasGame: boolean; canOperate: boolean; patch: Patch }) {
    const [title, setTitle] = useState(settings.title);
    const [subtitle, setSubtitle] = useState(settings.subtitle);
    const [minutes, setMinutes] = useState(0);

    useEffect(() => {
        setTitle(settings.title);
        setSubtitle(settings.subtitle);
    }, [settings.title, settings.subtitle]);

    return (
        <div className="space-y-4" data-testid="extra-screens-studio">
            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-1 font-medium text-gray-900">Telas extras no ar</div>
                <p className="mb-3 text-xs text-gray-500">Cada tela é uma Fonte de navegador própria no OBS (URLs na prévia). Ela só aparece quando você a mostra aqui; o clique vale na hora.</p>
                <div className="space-y-2">
                    {SCREENS.map((screen) => {
                        const on = screens[screen.key] === true;
                        const blocked = !canOperate || (screen.needsGame && !hasGame);

                        return (
                            <div key={screen.key} className="flex items-center justify-between gap-3">
                                <div>
                                    <div className="text-sm font-medium text-gray-800">{screen.label}</div>
                                    <div className="text-xs text-gray-500">{screen.hint}</div>
                                </div>
                                <Button small variant={on ? 'primary' : 'secondary'} disabled={blocked} aria-pressed={on} onClick={() => patch({ screens_live: { [screen.key]: !on } })}>{on ? 'Ocultar' : 'Mostrar'}</Button>
                            </div>
                        );
                    })}
                </div>
                {!hasGame && <p className="mt-2 text-xs text-gray-500">Escolha um jogo no ar para liberar o pré-jogo e o comparativo.</p>}
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-1 font-medium text-gray-900">Cartela de transmissão</div>
                <div className="mb-3 flex flex-wrap gap-2">
                    {PRESETS.map((preset) => (
                        <Button key={preset.label} small variant="secondary" disabled={!canOperate} onClick={() => { setTitle(preset.title); setSubtitle(preset.subtitle); setMinutes(preset.minutes); }}>{preset.label}</Button>
                    ))}
                </div>
                <div className="grid gap-2 sm:grid-cols-2">
                    <Field label="Título" htmlFor={`cartela-title-${channelId}`}><Input id={`cartela-title-${channelId}`} maxLength={60} disabled={!canOperate} value={title} onChange={(event) => setTitle(event.target.value)} /></Field>
                    <Field label="Subtítulo (opcional)" htmlFor={`cartela-subtitle-${channelId}`}><Input id={`cartela-subtitle-${channelId}`} maxLength={80} disabled={!canOperate} value={subtitle} onChange={(event) => setSubtitle(event.target.value)} /></Field>
                    <Field label="Contagem regressiva (min, 0 = sem)" htmlFor={`cartela-minutes-${channelId}`}><Input id={`cartela-minutes-${channelId}`} type="number" min="0" max="180" disabled={!canOperate} value={minutes} onChange={(event) => setMinutes(Math.min(180, Math.max(0, Number(event.target.value) || 0)))} /></Field>
                </div>
                <div className="mt-3 flex gap-2">
                    <Button small disabled={!canOperate || title.trim() === ''} onClick={() => patch({ screen_settings: { title: title.trim(), subtitle: subtitle.trim(), minutes }, screens_live: { cartela: true } })}>Aplicar e mostrar</Button>
                    <Button small variant="secondary" disabled={!canOperate || title.trim() === ''} onClick={() => patch({ screen_settings: { title: title.trim(), subtitle: subtitle.trim(), minutes } })}>Só aplicar</Button>
                </div>
            </div>
        </div>
    );
}
