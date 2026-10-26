import { Badge, Button, Card, Empty, Field, FilterBar, LinkButton, PageHeader, Select, StatCard } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Selection, duplicated, openGames, sameSelection } from '@/lib/officials';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Role {
    value: string;
    label: string;
}

interface Official {
    id: string;
    name: string;
    active: boolean;
}

interface GameItem {
    id: string;
    number: number | null;
    round: number | null;
    group: string | null;
    when: string | null;
    home: string | null;
    away: string | null;
    venue: string | null;
    locked: boolean;
    status_label: string;
    assignments: Record<string, string>;
    conflicts: string[];
}

interface Props {
    competition: { id: string; name: string };
    season: { id: string; name: string };
    division: { id: string; name: string };
    stage: { id: string; name: string };
    roles: Role[];
    officials: Official[];
    games: GameItem[];
}

const title = (game: GameItem) => `${game.home ?? 'A definir'} x ${game.away ?? 'A definir'}`;

function GameCard({ game, roles, officials }: { game: GameItem; roles: Role[]; officials: Official[] }) {
    const [selection, setSelection] = useState<Selection>(game.assignments);
    const [saving, setSaving] = useState(false);
    const dirty = !sameSelection(selection, game.assignments, roles);
    const repeated = duplicated(selection);
    const missing = !selection.referee;

    const save = () => {
        if (!dirty || repeated.length > 0 || saving) return;

        setSaving(true);
        router.put(route('panel.games.officials.update', game.id), { assignments: Object.fromEntries(roles.map((role) => [role.value, selection[role.value] ?? ''])) }, { preserveScroll: true, onFinish: () => setSaving(false) });
    };

    return (
        <Card
            title={title(game)}
            actions={game.locked ? <Badge tone="gray">{game.status_label}</Badge> : missing ? <Badge tone="amber">Sem árbitro</Badge> : <Badge tone="green">Árbitro definido</Badge>}
        >
            <p className="-mt-1 mb-3 text-sm text-gray-500" data-testid="official-game-info">
                {game.when ?? 'Sem data'}
                {game.venue ? ` · ${game.venue}` : ''}
                {game.round ? ` · rodada ${game.round}` : ''}
                {game.group ? ` · ${game.group}` : ''}
            </p>

            {game.conflicts.length > 0 && (
                <ul className="mb-3 space-y-1 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200" data-testid="official-conflicts">
                    {game.conflicts.map((conflict) => (
                        <li key={conflict}>{conflict}</li>
                    ))}
                </ul>
            )}

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {roles.map((role) => (
                    <Field key={role.value} label={role.label} htmlFor={`${game.id}-${role.value}`}>
                        <Select
                            id={`${game.id}-${role.value}`}
                            value={selection[role.value] ?? ''}
                            disabled={game.locked}
                            onChange={(event) => setSelection((current) => ({ ...current, [role.value]: event.target.value }))}
                        >
                            <option value="">— vaga livre —</option>
                            {officials
                                .filter((official) => official.active || official.id === game.assignments[role.value])
                                .map((official) => (
                                    <option key={official.id} value={official.id}>
                                        {official.name}
                                        {official.active ? '' : ' (inativo)'}
                                    </option>
                                ))}
                        </Select>
                    </Field>
                ))}
            </div>

            {repeated.length > 0 && <p className="mt-3 text-sm text-red-600">A mesma pessoa não pode ter duas funções no mesmo jogo.</p>}

            {!game.locked && (
                <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Button onClick={save} disabled={!dirty || repeated.length > 0 || saving}>
                        {saving ? 'Salvando…' : 'Salvar escala'}
                    </Button>
                    {dirty && (
                        <Button variant="ghost" onClick={() => setSelection(game.assignments)}>
                            Desfazer
                        </Button>
                    )}
                </div>
            )}
        </Card>
    );
}

export default function Officials({ competition, season, division, stage, roles, officials, games }: Props) {
    const [onlyOpen, setOnlyOpen] = useState(false);
    const withReferee = games.filter((game) => game.assignments.referee).length;
    const conflicts = games.filter((game) => game.conflicts.length > 0).length;
    const shown = useMemo(() => (onlyOpen ? openGames(games) : games), [games, onlyOpen]);

    return (
        <PanelLayout>
            <Head title={`Escala de árbitros · ${stage.name}`} />
            <PageHeader
                title={`Escala de árbitros e mesários · ${stage.name}`}
                subtitle={`${division.name} · ${season.name}`}
                breadcrumbs={[
                    { label: 'Competições', href: route('panel.competitions.index') },
                    { label: competition.name, href: route('panel.competitions.show', competition.id) },
                    { label: season.name, href: route('panel.seasons.show', season.id) },
                    { label: division.name, href: route('panel.divisions.show', division.id) },
                    { label: stage.name, href: route('panel.stages.show', stage.id) },
                    { label: 'Escala' },
                ]}
                actions={
                    <LinkButton href={route('panel.officials.index')} variant="secondary">
                        Cadastro de árbitros e mesários
                    </LinkButton>
                }
            />

            {games.length === 0 ? (
                <Empty title="Esta fase ainda não tem jogos.">Gere a tabela da fase para poder escalar árbitros e mesários.</Empty>
            ) : officials.length === 0 ? (
                <Empty title="Nenhum árbitro ou mesário cadastrado.">
                    Cadastre quem pode ser escalado em <a className="text-emerald-700 underline" href={route('panel.officials.create')}>Novo cadastro</a>.
                </Empty>
            ) : (
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
                        <StatCard label="Jogos da fase" value={games.length} />
                        <StatCard label="Com árbitro definido" value={`${withReferee}/${games.length}`} tone={withReferee === games.length ? 'green' : 'amber'} />
                        <StatCard label="Jogos com aviso de conflito" value={conflicts} tone={conflicts > 0 ? 'amber' : 'green'} hint="A mesma pessoa em jogos muito próximos" />
                    </div>

                    <FilterBar label="Filtro da escala">
                        <label className="flex min-h-11 items-center gap-2 text-sm text-gray-700 sm:min-h-0">
                            <input type="checkbox" className="rounded border-gray-300 text-emerald-600" checked={onlyOpen} onChange={(event) => setOnlyOpen(event.target.checked)} />
                            Mostrar só jogos sem árbitro
                        </label>
                    </FilterBar>

                    {shown.length === 0 ? (
                        <Empty title="Todos os jogos já têm árbitro." />
                    ) : (
                        <div className="grid grid-cols-1 gap-4 2xl:grid-cols-2">
                            {shown.map((game) => (
                                <GameCard key={`${game.id}:${JSON.stringify(game.assignments)}`} game={game} roles={roles} officials={officials} />
                            ))}
                        </div>
                    )}
                </div>
            )}
        </PanelLayout>
    );
}
