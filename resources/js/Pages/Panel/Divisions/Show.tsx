import DataTable from '@/Components/DataTable';
import Modal from '@/Components/Modal';
import { Badge, Button, Card, DeleteButton, Empty, Field, Input, LinkButton, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Option } from '@/types/panel';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Stage {
    id: string;
    name: string;
    sequence: number;
    type: string;
    type_label: string;
}

interface Entry {
    id: string;
    seed: number | null;
    status: string;
    status_label: string;
    roster_count: number;
    team: { id: string; name: string; city: string | null };
}

interface Props {
    competition: { id: string; name: string };
    season: { id: string; name: string };
    division: { id: string; name: string; category: string | null; gender: string; gender_label: string };
    stages: Stage[];
    entries: Entry[];
    available_teams: { id: string; name: string }[];
    options: { stage_types: Option[]; entry_statuses: Option[]; next_sequence: number };
}

const entryTone = (status: string) => (status === 'confirmed' ? 'green' : status === 'pending' ? 'amber' : 'red');

function StageModal({ divisionId, stage, options, nextSequence, show, onClose }: {
    divisionId: string;
    stage: Stage | null;
    options: Option[];
    nextSequence: number;
    show: boolean;
    onClose: () => void;
}) {
    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: stage?.name ?? '',
        type: stage?.type ?? 'league',
        sequence: stage?.sequence ?? nextSequence,
    });

    const done = { onSuccess: () => { reset(); onClose(); } };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (stage) {
            put(route('panel.stages.update', stage.id), done);
        } else {
            post(route('panel.divisions.stages.store', divisionId), done);
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} className="space-y-4 p-6">
                <h2 className="text-lg font-semibold text-gray-900">{stage ? 'Editar fase' : 'Nova fase'}</h2>
                <Field label="Nome" htmlFor="stage-name" error={errors.name} hint="Ex.: Fase de grupos, Quartas de final">
                    <Input id="stage-name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus required />
                </Field>
                <Field label="Formato" htmlFor="stage-type" error={errors.type}>
                    <Select id="stage-type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        {options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Ordem" htmlFor="stage-sequence" error={errors.sequence} hint="Posição da fase no campeonato (1 = primeira).">
                    <Input id="stage-sequence" type="number" min={1} value={data.sequence} onChange={(e) => setData('sequence', Number(e.target.value || 1))} />
                </Field>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Salvar
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function EntryModal({ divisionId, entry, teams, statuses, show, onClose }: {
    divisionId: string;
    entry: Entry | null;
    teams: { id: string; name: string }[];
    statuses: Option[];
    show: boolean;
    onClose: () => void;
}) {
    const { data, setData, post, put, processing, errors, reset } = useForm({
        team_id: '',
        seed: entry?.seed ? String(entry.seed) : '',
        status: entry?.status ?? 'confirmed',
    });

    const done = { onSuccess: () => { reset(); onClose(); } };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (entry) {
            put(route('panel.entries.update', entry.id), done);
        } else {
            post(route('panel.divisions.entries.store', divisionId), done);
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} className="space-y-4 p-6">
                <h2 className="text-lg font-semibold text-gray-900">{entry ? `Inscrição de ${entry.team.name}` : 'Inscrever equipe'}</h2>
                {!entry && (
                    <Field label="Equipe" htmlFor="entry-team" error={errors.team_id}>
                        <Select id="entry-team" value={data.team_id} onChange={(e) => setData('team_id', e.target.value)} required>
                            <option value="">Selecione…</option>
                            {teams.map((team) => (
                                <option key={team.id} value={team.id}>
                                    {team.name}
                                </option>
                            ))}
                        </Select>
                        {teams.length === 0 && (
                            <p className="mt-1 text-xs text-gray-500">
                                Todas as equipes já estão inscritas. <Link href={route('panel.teams.create')} className="text-emerald-700 underline">Cadastrar nova equipe</Link>.
                            </p>
                        )}
                    </Field>
                )}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Cabeça de chave" htmlFor="entry-seed" error={errors.seed} hint="Ordem de sorteio (1 = primeiro). Opcional.">
                        <Input id="entry-seed" type="number" min={1} value={data.seed} onChange={(e) => setData('seed', e.target.value)} />
                    </Field>
                    <Field label="Situação" htmlFor="entry-status" error={errors.status}>
                        <Select id="entry-status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            {statuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {entry ? 'Salvar' : 'Inscrever'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Show({ competition, season, division, stages, entries, available_teams, options }: Props) {
    const [stageModal, setStageModal] = useState<{ open: boolean; stage: Stage | null }>({ open: false, stage: null });
    const [entryModal, setEntryModal] = useState<{ open: boolean; entry: Entry | null }>({ open: false, entry: null });

    return (
        <PanelLayout>
            <Head title={`${division.name} · ${season.name}`} />
            <PageHeader
                title={division.name}
                subtitle={[division.category, division.gender_label].filter(Boolean).join(' · ')}
                breadcrumbs={[
                    { label: 'Competições', href: route('panel.competitions.index') },
                    { label: competition.name, href: route('panel.competitions.show', competition.id) },
                    { label: season.name, href: route('panel.seasons.show', season.id) },
                    { label: division.name },
                ]}
                actions={
                    <DeleteButton
                        href={route('panel.divisions.destroy', division.id)}
                        message={`Excluir a divisão "${division.name}" com todas as fases e jogos? Esta ação não pode ser desfeita.`}
                        small={false}
                    />
                }
            />

            <div className="space-y-6">
                <Card title="Fases" actions={<Button small onClick={() => setStageModal({ open: true, stage: null })}>Nova fase</Button>}>
                    {stages.length === 0 ? (
                        <Empty title="Nenhuma fase cadastrada.">
                            Uma fase define o formato da disputa: pontos corridos, fase de grupos ou mata-mata.
                        </Empty>
                    ) : (
                        <ul className="-my-3 divide-y divide-gray-100">
                            {stages.map((stage) => (
                                <li key={stage.id} className="flex items-center justify-between gap-3 py-3">
                                    <div className="flex items-center gap-3">
                                        <span className="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-800">
                                            {stage.sequence}
                                        </span>
                                        <span className="font-medium text-gray-900">{stage.name}</span>
                                        <Badge tone="blue">{stage.type_label}</Badge>
                                    </div>
                                    <div className="flex gap-2">
                                        <LinkButton href={route('panel.stages.show', stage.id)} small>
                                            Jogos e classificação
                                        </LinkButton>
                                        <Button small variant="secondary" onClick={() => setStageModal({ open: true, stage })}>
                                            Editar
                                        </Button>
                                        <DeleteButton href={route('panel.stages.destroy', stage.id)} message={`Excluir a fase "${stage.name}"?`} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <DataTable
                    title={`Equipes inscritas (${entries.length})`}
                    caption="Equipes inscritas na divisão"
                    searchPlaceholder="Buscar equipe"
                    actions={<Button small onClick={() => setEntryModal({ open: true, entry: null })}>Inscrever equipe</Button>}
                    rows={entries}
                    rowKey={(entry) => entry.id}
                    persistKey="panel-division-entries"
                    empty={<Empty title="Nenhuma equipe inscrita.">Inscreva as equipes que vão disputar esta divisão.</Empty>}
                    columns={[
                        { key: 'seed', header: 'Cabeça', value: (entry) => entry.seed, hideBelow: 'lg', cell: (entry) => entry.seed ?? '—', nowrap: true },
                        {
                            key: 'team',
                            header: 'Equipe',
                            primary: true,
                            value: (entry) => `${entry.team.name} ${entry.team.city ?? ''}`.trim(),
                            cell: (entry) => (
                                <span className="font-medium text-gray-900">
                                    {entry.team.name}
                                    {entry.team.city && <span className="ml-2 font-normal text-gray-500">{entry.team.city}</span>}
                                </span>
                            ),
                        },
                        {
                            key: 'status',
                            header: 'Situação',
                            value: (entry) => entry.status_label,
                            filter: true,
                            cell: (entry) => <Badge tone={entryTone(entry.status)}>{entry.status_label}</Badge>,
                        },
                        {
                            key: 'roster',
                            header: 'Elenco',
                            value: (entry) => entry.roster_count,
                            cell: (entry) => `${entry.roster_count} ${entry.roster_count === 1 ? 'pessoa' : 'pessoas'}`,
                        },
                        {
                            key: 'actions',
                            header: 'Ações',
                            align: 'right',
                            mobileLabel: false,
                            cell: (entry) => (
                                <div className="flex flex-wrap justify-end gap-2">
                                    <LinkButton href={route('panel.entries.show', entry.id)} small>
                                        Elenco
                                    </LinkButton>
                                    <Button small variant="secondary" onClick={() => setEntryModal({ open: true, entry })}>
                                        Editar
                                    </Button>
                                    <DeleteButton href={route('panel.entries.destroy', entry.id)} message={`Remover a inscrição de "${entry.team.name}"?`} label="Remover" />
                                </div>
                            ),
                        },
                    ]}
                />

            </div>

            <StageModal
                key={`stage-${stageModal.stage?.id ?? 'new'}-${stageModal.open}`}
                divisionId={division.id}
                stage={stageModal.stage}
                options={options.stage_types}
                nextSequence={options.next_sequence}
                show={stageModal.open}
                onClose={() => setStageModal({ open: false, stage: null })}
            />
            <EntryModal
                key={`entry-${entryModal.entry?.id ?? 'new'}-${entryModal.open}`}
                divisionId={division.id}
                entry={entryModal.entry}
                teams={available_teams}
                statuses={options.entry_statuses}
                show={entryModal.open}
                onClose={() => setEntryModal({ open: false, entry: null })}
            />
        </PanelLayout>
    );
}
