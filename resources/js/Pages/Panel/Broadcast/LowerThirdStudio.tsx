import { Button, Field, Input, Select } from '@/Components/Panel/ui';
import { LOWER_THIRD_ANIMATIONS, LOWER_THIRD_KINDS, LOWER_THIRD_STYLES, LowerThirdSettings } from '@/lib/lowerThird';
import { useEffect, useState } from 'react';

interface Person {
    id: string;
    name: string;
    shirt: number | null;
    team: string;
}

type Show = { kind: string; title?: string; subtitle?: string; person_id?: string };

export default function LowerThirdStudio({ channelId, settings: saved, people, hasGame, visible, canOperate, patch }: {
    channelId: string;
    settings: LowerThirdSettings;
    people: Person[];
    hasGame: boolean;
    visible: boolean;
    canOperate: boolean;
    patch: (data: { lower_third_settings?: Partial<LowerThirdSettings>; lower_third_show?: Show }) => void;
}) {
    const [settings, setSettings] = useState(saved);
    const [title, setTitle] = useState('');
    const [subtitle, setSubtitle] = useState('');
    const [person, setPerson] = useState('');

    useEffect(() => setSettings(saved), [saved]);

    const toggleKind = (key: string) => setSettings((current) => ({ ...current, kinds: current.kinds.includes(key) ? current.kinds.filter((item) => item !== key) : [...current.kinds, key] }));
    const blocked = !canOperate || !visible;

    return (
        <div className="space-y-4" data-testid="lower-third-studio">
            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-1 font-medium text-gray-900">Estilo da faixa</div>
                <p className="mb-3 text-xs text-gray-500">Aparece na tela <strong>Lances</strong> quando um gol, cartão ou substituição é lançado na súmula.</p>
                <div className="grid gap-2 sm:grid-cols-2" role="group" aria-label="Estilo da faixa">
                    {LOWER_THIRD_STYLES.map((style) => (
                        <button key={style.key} type="button" disabled={!canOperate} aria-pressed={settings.style === style.key} onClick={() => setSettings({ ...settings, style: style.key })}
                            className={`rounded-lg border p-3 text-left text-sm ${settings.style === style.key ? 'border-teal-600 bg-teal-50 text-teal-900' : 'border-gray-200 hover:bg-gray-50'}`}>
                            <div className="font-semibold">{style.label}</div>
                            <div className="text-xs text-gray-500">{style.hint}</div>
                        </button>
                    ))}
                </div>
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    <Field label="Animação" htmlFor={`lt-anim-${channelId}`}>
                        <Select id={`lt-anim-${channelId}`} disabled={!canOperate} value={settings.animation} onChange={(event) => setSettings({ ...settings, animation: event.target.value as LowerThirdSettings['animation'] })}>
                            {LOWER_THIRD_ANIMATIONS.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}
                        </Select>
                    </Field>
                    <Field label={`Tempo no ar · ${settings.seconds}s`} htmlFor={`lt-seconds-${channelId}`}>
                        <Input id={`lt-seconds-${channelId}`} type="range" min="3" max="15" step="1" disabled={!canOperate} value={settings.seconds} onChange={(event) => setSettings({ ...settings, seconds: Number(event.target.value) })} />
                    </Field>
                </div>
                <div className="mt-3">
                    <div className="mb-1 text-sm font-medium text-gray-700">Quais lances aparecem</div>
                    <div className="flex flex-wrap gap-x-4 gap-y-1">
                        {LOWER_THIRD_KINDS.map((kind) => (
                            <label key={kind.key} className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" className="rounded border-gray-300 text-teal-600 focus:ring-teal-500" disabled={!canOperate} checked={settings.kinds.includes(kind.key)} onChange={() => toggleKind(kind.key)} />
                                {kind.label}
                            </label>
                        ))}
                    </div>
                    <label className="mt-2 flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" className="rounded border-gray-300 text-teal-600 focus:ring-teal-500" disabled={!canOperate} checked={settings.score} onChange={(event) => setSettings({ ...settings, score: event.target.checked })} />
                        Mostrar o placar no gol
                    </label>
                </div>
                {canOperate && <Button small className="mt-3" onClick={() => patch({ lower_third_settings: settings })}>Aplicar estilo</Button>}
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-1 font-medium text-gray-900">Testar o estilo</div>
                <p className="mb-3 text-xs text-gray-500">{hasGame ? (visible ? 'Dispara uma faixa de exemplo com o jogo no ar, direto no OBS.' : 'Mostre a tela de lances (coluna Ao vivo) para o teste aparecer.') : 'Escolha um jogo no ar para testar.'}</p>
                <div className="flex flex-wrap gap-2">
                    {LOWER_THIRD_KINDS.map((kind) => (
                        <Button key={kind.key} small variant="secondary" disabled={blocked || !hasGame} onClick={() => patch({ lower_third_show: { kind: kind.key } })}>{kind.label}</Button>
                    ))}
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-1 font-medium text-gray-900">Jogador em destaque</div>
                <p className="mb-3 text-xs text-gray-500">Apresenta um atleta da escalação (nome público, camisa, posição e equipe).</p>
                <div className="flex flex-wrap items-end gap-2">
                    <Field label="Atleta" htmlFor={`lt-person-${channelId}`}>
                        <Select id={`lt-person-${channelId}`} disabled={blocked || !hasGame} value={person} onChange={(event) => setPerson(event.target.value)}>
                            <option value="">Escolha…</option>
                            {people.map((item) => <option key={item.id} value={item.id}>{`${item.shirt ?? '–'} · ${item.name} (${item.team})`}</option>)}
                        </Select>
                    </Field>
                    <Button small disabled={blocked || person === ''} onClick={() => patch({ lower_third_show: { kind: 'player', person_id: person } })}>Mostrar</Button>
                </div>
            </div>

            <div className="rounded-lg border border-gray-200 p-4">
                <div className="mb-1 font-medium text-gray-900">Aviso manual</div>
                <p className="mb-3 text-xs text-gray-500">Qualquer texto: narrador, comentarista, patrocinador, “Intervalo”…</p>
                <div className="grid gap-2 sm:grid-cols-2">
                    <Field label="Título" htmlFor={`lt-title-${channelId}`}><Input id={`lt-title-${channelId}`} maxLength={60} disabled={blocked} value={title} onChange={(event) => setTitle(event.target.value)} /></Field>
                    <Field label="Subtítulo (opcional)" htmlFor={`lt-subtitle-${channelId}`}><Input id={`lt-subtitle-${channelId}`} maxLength={80} disabled={blocked} value={subtitle} onChange={(event) => setSubtitle(event.target.value)} /></Field>
                </div>
                <Button small className="mt-2" disabled={blocked || title.trim() === ''} onClick={() => patch({ lower_third_show: { kind: 'custom', title: title.trim(), subtitle: subtitle.trim() || undefined } })}>Mostrar aviso</Button>
            </div>
        </div>
    );
}
