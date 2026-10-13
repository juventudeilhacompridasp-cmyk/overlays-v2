import { Badge, Button, Card, Field, Input, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Side = 'home' | 'away';

type Lineup = {
    team: Side;
    name: string | null;
    shirt: number | null;
    starter: boolean | null;
    confidence: number | null;
};

type Lance = {
    type: string;
    team: Side;
    name: string | null;
    shirt: number | null;
    minute: string | null;
    detail: string | null;
    confidence: number | null;
};

type Sheet = {
    home_team: string | null;
    away_team: string | null;
    home_score: number | null;
    away_score: number | null;
    lineups: Lineup[];
    events: Lance[];
    notes: string | null;
    confidence: number | null;
};

interface Props {
    upload: { id: string; name: string; game_id: string; home: string | null; away: string | null; division: string; season: string };
    sheet: Sheet;
    event_types: Record<string, string>;
    reviewed: boolean;
    changes: string[];
}

const sideLabel = (side: Side, sheet: Sheet) => (side === 'home' ? sheet.home_team || 'Casa' : sheet.away_team || 'Visitante');
const num = (value: string): number | null => (value === '' ? null : Number(value));

function Doubt({ confidence }: { confidence: number | null }) {
    return confidence !== null && confidence < 0.6 ? <Badge tone="amber">confiança {Math.round(confidence * 100)}%</Badge> : null;
}

export default function SheetReview({ upload, sheet, event_types, reviewed, changes }: Props) {
    const canOperate = useCan('operate-games');
    const form = useForm<Sheet>(sheet);
    const data = form.data;

    const setLineup = (index: number, patch: Partial<Lineup>) => form.setData('lineups', data.lineups.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    const setEvent = (index: number, patch: Partial<Lance>) => form.setData('events', data.events.map((row, i) => (i === index ? { ...row, ...patch } : row)));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('panel.games.uploads.review.confirm', upload.id), { preserveScroll: true });
    };

    const discard = () => {
        if (window.confirm('Descartar esta súmula? Ela não poderá mais ser lida nem conferida.')) router.post(route('panel.games.uploads.discard', upload.id));
    };

    return (
        <PanelLayout>
            <Head title="Revisar leitura da súmula" />
            <PageHeader
                title="Revisar leitura da súmula"
                subtitle={`${upload.home ?? 'A definir'} x ${upload.away ?? 'A definir'} · ${upload.division} · ${upload.season}`}
                breadcrumbs={[
                    { label: 'Súmulas enviadas', href: route('panel.games.uploads', upload.game_id) },
                    { label: 'Revisar leitura' },
                ]}
            />

            <p className="mb-4 max-w-3xl text-sm text-gray-600">
                Confira o que foi lido em <strong>{upload.name}</strong> e corrija o que estiver errado. Nada disto altera o jogo: depois de confirmar, o relatório compara a súmula com o que já está lançado.
            </p>

            {reviewed && changes.length > 0 && (
                <Card title="Correções já registradas" className="mb-4 max-w-4xl">
                    <ul className="list-disc pl-5 text-sm text-gray-700">
                        {changes.map((change) => (
                            <li key={change}>{change}</li>
                        ))}
                    </ul>
                </Card>
            )}

            <form onSubmit={submit} className="max-w-4xl space-y-6">
                <Card title="Placar e equipes">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {(['home', 'away'] as Side[]).map((side) => (
                            <div key={side} className="space-y-3">
                                <Field label={side === 'home' ? 'Equipe da casa' : 'Equipe visitante'} error={form.errors[`${side}_team`]}>
                                    <Input value={data[`${side}_team`] ?? ''} disabled={!canOperate} onChange={(e) => form.setData(`${side}_team`, e.target.value || null)} />
                                </Field>
                                <Field label="Gols / pontos / sets" error={form.errors[`${side}_score`]}>
                                    <Input type="number" min={0} value={data[`${side}_score`] ?? ''} disabled={!canOperate} onChange={(e) => form.setData(`${side}_score`, num(e.target.value))} />
                                </Field>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card title={`Escalação lida (${data.lineups.length})`}>
                    {data.lineups.length === 0 && <p className="text-sm text-gray-500">Nenhum atleta foi lido.</p>}
                    <div className="space-y-2" data-testid="lineups">
                        {data.lineups.map((row, index) => (
                            <div key={index} className="flex flex-wrap items-center gap-2">
                                <Select value={row.team} disabled={!canOperate} onChange={(e) => setLineup(index, { team: e.target.value as Side })} className="w-40">
                                    <option value="home">{sideLabel('home', data)}</option>
                                    <option value="away">{sideLabel('away', data)}</option>
                                </Select>
                                <Input className="w-20" type="number" min={0} placeholder="Nº" value={row.shirt ?? ''} disabled={!canOperate} onChange={(e) => setLineup(index, { shirt: num(e.target.value) })} />
                                <Input className="min-w-48 flex-1" placeholder="Nome" value={row.name ?? ''} disabled={!canOperate} onChange={(e) => setLineup(index, { name: e.target.value || null })} />
                                <Select
                                    className="w-32"
                                    value={row.starter === null ? '' : row.starter ? 'starter' : 'bench'}
                                    disabled={!canOperate}
                                    onChange={(e) => setLineup(index, { starter: e.target.value === '' ? null : e.target.value === 'starter' })}
                                >
                                    <option value="">—</option>
                                    <option value="starter">Titular</option>
                                    <option value="bench">Reserva</option>
                                </Select>
                                <Doubt confidence={row.confidence} />
                                {canOperate && (
                                    <Button type="button" small variant="ghost" onClick={() => form.setData('lineups', data.lineups.filter((_, i) => i !== index))}>
                                        Remover
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                    {canOperate && (
                        <Button type="button" small variant="secondary" className="mt-3" onClick={() => form.setData('lineups', [...data.lineups, { team: 'home', name: null, shirt: null, starter: null, confidence: null }])}>
                            Acrescentar atleta
                        </Button>
                    )}
                </Card>

                <Card title={`Lances lidos (${data.events.length})`}>
                    {data.events.length === 0 && <p className="text-sm text-gray-500">Nenhum lance foi lido.</p>}
                    <div className="space-y-2" data-testid="events">
                        {data.events.map((row, index) => (
                            <div key={index} className="flex flex-wrap items-center gap-2">
                                <Select className="w-40" value={row.type} disabled={!canOperate} onChange={(e) => setEvent(index, { type: e.target.value })}>
                                    {Object.entries(event_types).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </Select>
                                <Select className="w-40" value={row.team} disabled={!canOperate} onChange={(e) => setEvent(index, { team: e.target.value as Side })}>
                                    <option value="home">{sideLabel('home', data)}</option>
                                    <option value="away">{sideLabel('away', data)}</option>
                                </Select>
                                <Input className="w-20" type="number" min={0} placeholder="Nº" value={row.shirt ?? ''} disabled={!canOperate} onChange={(e) => setEvent(index, { shirt: num(e.target.value) })} />
                                <Input className="min-w-40 flex-1" placeholder={row.type === 'substitution' ? 'Quem entrou' : 'Nome'} value={row.name ?? ''} disabled={!canOperate} onChange={(e) => setEvent(index, { name: e.target.value || null })} />
                                {row.type === 'substitution' && <Input className="min-w-40 flex-1" placeholder="Quem saiu" value={row.detail ?? ''} disabled={!canOperate} onChange={(e) => setEvent(index, { detail: e.target.value || null })} />}
                                <Input className="w-20" placeholder="Min." value={row.minute ?? ''} disabled={!canOperate} onChange={(e) => setEvent(index, { minute: e.target.value || null })} />
                                <Doubt confidence={row.confidence} />
                                {canOperate && (
                                    <Button type="button" small variant="ghost" onClick={() => form.setData('events', data.events.filter((_, i) => i !== index))}>
                                        Remover
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                    {canOperate && (
                        <Button type="button" small variant="secondary" className="mt-3" onClick={() => form.setData('events', [...data.events, { type: 'goal', team: 'home', name: null, shirt: null, minute: null, detail: null, confidence: null }])}>
                            Acrescentar lance
                        </Button>
                    )}
                </Card>

                <Card title="Observações">
                    <textarea
                        className="w-full rounded-md border border-gray-300 p-2 text-sm"
                        rows={3}
                        value={data.notes ?? ''}
                        disabled={!canOperate}
                        onChange={(e) => form.setData('notes', e.target.value || null)}
                    />
                </Card>

                {Object.keys(form.errors).length > 0 && <p className="text-sm text-red-600">Há campos com erro. Confira os números e os tamanhos dos textos.</p>}

                <div className="flex flex-wrap items-center gap-3">
                    {canOperate && (
                        <>
                            <Button type="submit" disabled={form.processing}>
                                {reviewed ? 'Confirmar de novo e ver relatório' : 'Confirmar revisão e ver relatório'}
                            </Button>
                            <Button type="button" variant="danger" onClick={discard}>
                                Descartar súmula
                            </Button>
                        </>
                    )}
                    {reviewed && (
                        <Link href={route('panel.games.uploads.report', upload.id)} className="text-sm text-emerald-700 underline">
                            Ver relatório
                        </Link>
                    )}
                </div>
            </form>
        </PanelLayout>
    );
}
