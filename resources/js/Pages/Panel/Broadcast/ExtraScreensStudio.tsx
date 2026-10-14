import { Button, Field, Input, Select } from '@/Components/Panel/ui';
import { useEffect, useState } from 'react';

interface CartelaSettings {
    title: string;
    subtitle: string;
    until_ms: number | null;
    social?: { items: { label: string; value: string }[]; url: string | null };
}

interface Person {
    id: string;
    name: string;
    shirt: number | null;
    team: string;
}

type Patch = (data: { screens_live?: Record<string, boolean>; screen_settings?: { title?: string; subtitle?: string; minutes?: number; mvp_person_id?: string | null; social?: { items: { label: string; value: string }[]; url: string | null } } }) => void;

const SCREENS: { key: string; label: string; hint: string; needsGame: boolean }[] = [
    { key: 'pregame', label: 'Cartela pré-jogo', hint: 'Escudos, competição, local, horário e contagem regressiva para o início.', needsGame: true },
    { key: 'compare', label: 'Comparativo de equipes', hint: 'Posição na tabela, forma recente e confronto direto.', needsGame: true },
    { key: 'summary', label: 'Resumo do jogo', hint: 'Intervalo ou fim: placar, gols, cartões (se liberados) e craque do jogo.', needsGame: true },
    { key: 'bracket', label: 'Chaveamento do mata-mata', hint: 'Confrontos por rodada, placares, vencedor e campeão.', needsGame: true },
    { key: 'social', label: 'Chamada para redes sociais', hint: 'Faixa com @, site e QR code do link.', needsGame: false },
    { key: 'sponsor_bar', label: 'Barra de patrocinadores 1500 x 200', hint: 'Banner largo dos patrocinadores em rotação; configure em Patrocinadores.', needsGame: false },
    { key: 'cartela', label: 'Cartela de transmissão', hint: 'Tela cheia com título, contagem e patrocinadores.', needsGame: false },
];

const PRESETS: { label: string; title: string; subtitle: string; minutes: number }[] = [
    { label: 'Começamos em breve', title: 'Começamos em breve', subtitle: 'Fique ligado na transmissão', minutes: 10 },
    { label: 'Intervalo', title: 'Intervalo', subtitle: 'Voltamos já', minutes: 15 },
    { label: 'Voltamos já', title: 'Voltamos já', subtitle: '', minutes: 0 },
    { label: 'Fim da transmissão', title: 'Obrigado por assistir', subtitle: 'Até o próximo jogo', minutes: 0 },
];

export default function ExtraScreensStudio({ channelId, screens, settings, people, hasGame, canOperate, patch }: { channelId: string; screens: Record<string, boolean>; settings: CartelaSettings; people: Person[]; hasGame: boolean; canOperate: boolean; patch: Patch }) {
    const [title, setTitle] = useState(settings.title);
    const [subtitle, setSubtitle] = useState(settings.subtitle);
    const [minutes, setMinutes] = useState(0);
    const [mvp, setMvp] = useState('');
    const blank = { label: '', value: '' };
    const [lines, setLines] = useState(() => [...(settings.social?.items ?? []), blank, blank, blank, blank].slice(0, 4));
    const [link, setLink] = useState(settings.social?.url ?? '');

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
                <div className="mb-1 font-medium text-gray-900">Chamada para redes sociais</div>
                <p className="mb-3 text-xs text-gray-500">Até 4 linhas que se alternam (ex.: Instagram · @juventude) e, se quiser, um QR code para um link https.</p>
                <div className="space-y-2">
                    {lines.map((line, position) => (
                        <div key={position} className="grid grid-cols-[7rem_1fr] gap-2">
                            <Input aria-label={`Rótulo da linha ${position + 1}`} maxLength={20} placeholder="Instagram" disabled={!canOperate} value={line.label} onChange={(event) => setLines(lines.map((item, at) => (at === position ? { ...item, label: event.target.value } : item)))} />
                            <Input aria-label={`Texto da linha ${position + 1}`} maxLength={60} placeholder="@seuperfil" disabled={!canOperate} value={line.value} onChange={(event) => setLines(lines.map((item, at) => (at === position ? { ...item, value: event.target.value } : item)))} />
                        </div>
                    ))}
                    <Field label="Link do QR code (https, opcional)" htmlFor={`social-url-${channelId}`}><Input id={`social-url-${channelId}`} maxLength={200} placeholder="https://" disabled={!canOperate} value={link} onChange={(event) => setLink(event.target.value)} /></Field>
                </div>
                <Button small className="mt-3" disabled={!canOperate} onClick={() => patch({ screen_settings: { social: { items: lines.filter((line) => line.value.trim() !== ''), url: link.trim() || null } } })}>Salvar chamada</Button>
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-1 font-medium text-gray-900">Craque do jogo</div>
                <p className="mb-3 text-xs text-gray-500">Aparece no resumo do jogo. Vale só para o jogo no ar; trocar o jogo limpa a escolha.</p>
                <div className="flex flex-wrap items-end gap-2">
                    <Field label="Atleta" htmlFor={`mvp-${channelId}`}>
                        <Select id={`mvp-${channelId}`} disabled={!canOperate || !hasGame} value={mvp} onChange={(event) => setMvp(event.target.value)}>
                            <option value="">Nenhum</option>
                            {people.map((item) => <option key={item.id} value={item.id}>{`${item.shirt ?? '–'} · ${item.name} (${item.team})`}</option>)}
                        </Select>
                    </Field>
                    <Button small disabled={!canOperate || !hasGame} onClick={() => patch({ screen_settings: { mvp_person_id: mvp || null } })}>Definir</Button>
                </div>
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
