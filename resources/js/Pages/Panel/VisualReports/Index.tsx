import { Card, Empty, Field, PageHeader, Select } from '@/Components/Panel/ui';
import ReportPaper from '@/Components/Report/ReportPaper';
import PanelLayout from '@/Layouts/PanelLayout';
import { useFeature } from '@/lib/can';
import { ReportDocument, sectionsQuery } from '@/lib/report';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Kind = 'game' | 'championship';

interface Props {
    kind: Kind;
    selected: { game: string | null; division: string | null; sections: string[] };
    sections: Record<Kind, { key: string; label: string }[]>;
    games: { id: string; label: string; score: string | null; competition: string; date: string | null; status: string }[];
    divisions: { id: string; label: string }[];
    document: ReportDocument | null;
}

const KINDS: { key: Kind; label: string; hint: string }[] = [
    { key: 'game', label: 'Partida', hint: 'Placar, comparativo, períodos, linha do tempo, escalações, tabela e confronto direto.' },
    { key: 'championship', label: 'Campeonato', hint: 'Andamento, classificação, mata-mata, artilharia, ataque e defesa, rodadas, destaques e disciplina.' },
];

/** Painel > Relatórios ilustrados: escolher partida ou divisão e as seções, ver a prévia e baixar o PDF. */
export default function Index({ kind, selected, sections, games, divisions, document }: Props) {
    const [loading, setLoading] = useState(false);
    const overlays = useFeature('overlays');
    const options = sections[kind];
    const chosen = selected.sections.length === options.length ? [] : selected.sections;

    const visit = (next: { kind?: Kind; game?: string | null; division?: string | null; sections?: string[] }) => {
        const nextKind = next.kind ?? kind;
        const nextSections = next.sections ?? (next.kind && next.kind !== kind ? [] : chosen);
        const data: Record<string, string> = { tipo: nextKind };
        const game = next.game !== undefined ? next.game : selected.game;
        const division = next.division !== undefined ? next.division : selected.division;

        if (nextKind === 'game' && game) data.jogo = game;
        if (nextKind === 'championship' && division) data.divisao = division;
        if (nextSections.length > 0) data.secoes = nextSections.join(',');

        router.get(route('panel.visual-reports.index'), data, { preserveState: true, preserveScroll: true, replace: true, only: ['kind', 'selected', 'document'], onStart: () => setLoading(true), onFinish: () => setLoading(false) });
    };

    const toggle = (key: string) => {
        const current = selected.sections;
        const next = current.includes(key) ? current.filter((item) => item !== key) : [...current, key];

        if (next.length > 0) visit({ sections: options.map((option) => option.key).filter((option) => next.includes(option)) });
    };

    const target = kind === 'game' ? selected.game : selected.division;
    const pdfUrl = target === null ? null : (kind === 'game' ? route('panel.visual-reports.game', target) : route('panel.visual-reports.championship', target)) + sectionsQuery(chosen);

    return (
        <PanelLayout>
            <Head title="Relatórios ilustrados" />
            <PageHeader
                title="Relatórios ilustrados"
                subtitle="Relatórios em PDF com gráficos, ícones e indicadores da partida ou do campeonato. As mesmas seções podem ir ao ar como overlay na transmissão."
                actions={pdfUrl && document ? <a href={pdfUrl} className="inline-flex min-h-11 items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 sm:min-h-0" data-testid="report-pdf">Baixar PDF</a> : undefined}
            />
            <div className="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
                <div className="space-y-4">
                    <Card title="Relatório">
                        <div className="grid grid-cols-2 gap-2" role="group" aria-label="Tipo de relatório">
                            {KINDS.map((item) => (
                                <button key={item.key} type="button" aria-pressed={kind === item.key} onClick={() => visit({ kind: item.key })}
                                    className={`min-h-11 rounded-lg border p-2 text-left text-sm ${kind === item.key ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-gray-200 text-gray-800 hover:bg-gray-50'}`}>
                                    <span className="font-semibold">{item.label}</span>
                                </button>
                            ))}
                        </div>
                        <p className="mt-2 text-xs text-gray-500">{KINDS.find((item) => item.key === kind)?.hint}</p>
                        <div className="mt-4">
                            {kind === 'game' ? (
                                <Field label="Partida" htmlFor="report-game">
                                    <Select id="report-game" value={selected.game ?? ''} onChange={(event) => visit({ game: event.target.value || null })}>
                                        <option value="">Escolha a partida…</option>
                                        {games.map((game) => <option key={game.id} value={game.id}>{`${game.label}${game.score ? ` (${game.score})` : ''} · ${game.date ?? 'sem data'} · ${game.status} · ${game.competition}`}</option>)}
                                    </Select>
                                </Field>
                            ) : (
                                <Field label="Divisão" htmlFor="report-division">
                                    <Select id="report-division" value={selected.division ?? ''} onChange={(event) => visit({ division: event.target.value || null })}>
                                        <option value="">Escolha a divisão…</option>
                                        {divisions.map((division) => <option key={division.id} value={division.id}>{division.label}</option>)}
                                    </Select>
                                </Field>
                            )}
                        </div>
                    </Card>
                    <Card title="Seções">
                        <fieldset>
                            <legend className="sr-only">Seções do relatório</legend>
                            <ul className="space-y-1">
                                {options.map((option) => (
                                    <li key={option.key}>
                                        <label className="flex min-h-11 items-center gap-2 text-sm text-gray-800 sm:min-h-0">
                                            <input type="checkbox" className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" checked={selected.sections.includes(option.key)} disabled={selected.sections.length === 1 && selected.sections.includes(option.key)} onChange={() => toggle(option.key)} />
                                            {option.label}
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        </fieldset>
                        <p className="mt-3 text-xs text-gray-500">O PDF é interno (nomes completos e cartões) e sai com "uso interno" no rodapé. O overlay usa a versão pública: nomes como no portal e cartões só se o portal os mostrar.</p>
                    </Card>
                    {overlays && (
                        <Card title="Na transmissão">
                            <p className="text-sm text-gray-600">Em <Link href={route('panel.broadcast.index')} className="text-emerald-700 underline">Transmissão</Link>, coluna <strong>Relatório</strong>: escolha as seções, o ritmo (manual ou automático) e coloque a tela <strong>relatorio</strong> no OBS. Cada seção vira um slide animado que o operador passa ao vivo.</p>
                        </Card>
                    )}
                </div>
                <div aria-live="polite" aria-busy={loading} className={loading ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                    {document ? <ReportPaper document={document} /> : <Empty title={kind === 'game' ? 'Escolha uma partida para ver a prévia.' : 'Escolha uma divisão para ver a prévia.'} />}
                </div>
            </div>
        </PanelLayout>
    );
}
