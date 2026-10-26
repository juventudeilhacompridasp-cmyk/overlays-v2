import DataTable from '@/Components/DataTable';
import { GameList, PortalGame } from '@/Components/Portal/GameList';
import PushButton from '@/Components/Portal/PushButton';
import ShareButtons from '@/Components/Portal/ShareButtons';
import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Link } from '@inertiajs/react';

interface Extras {
    campaigns: { competition: string; season: string; division: string; status: string; url: string; played: number; wins: number; draws: number; losses: number; for: number; against: number; position: { title: string; position: number; of: number; points: number } | null }[] | null;
    form: { result: 'V' | 'E' | 'D'; score: string; opponent: string | null; home: boolean; url: string }[] | null;
    scorers: { player: string; goals: number }[] | null;
    calendar_url: string | null;
    roster?: { name: string | null; shirt: number | null; role: string; profile_url?: string | null }[];
}

const pill = { V: 'bg-emerald-600', E: 'bg-gray-500', D: 'bg-red-600' } as const;

interface Props {
    theme: Theme;
    seo: Seo;
    team: { id: string; name: string; city: string | null; logo_url: string | null; color: string | null };
    participations: { competition: string; season: string; division: string; url: string }[];
    games: PortalGame[];
    push_key: string | null;
    extras: Extras;
}

export default function Team({ theme, seo, team, participations, games, push_key, extras }: Props) {
    const upcoming = games.filter((game) => !game.decided);
    const results = games.filter((game) => game.decided);

    return (
        <PortalLayout theme={theme} seo={seo}>
            <div className="mb-6 flex items-center gap-4">
                {team.logo_url ? <img src={team.logo_url} alt={`Escudo do ${team.name}`} className="h-20 w-20 object-contain" /> : <span className="h-20 w-20 rounded-lg" style={{ backgroundColor: team.color ?? '#d1d5db' }} />}
                <div>
                    <h1 className="text-2xl font-extrabold">{team.name}</h1>
                    {team.city && <p className="text-gray-600">{team.city}</p>}
                    <div className="mt-2 flex flex-wrap gap-2">
                        <PushButton kind="team" id={team.id} publicKey={push_key} label="Seguir esta equipe" />
                        <ShareButtons text={`${team.name} — jogos e resultados:`} url={route('portal.team', team.id)} />
                        {extras.calendar_url && (
                            <a href={extras.calendar_url} className="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-50" data-testid="team-calendar">
                                📅 Adicionar à agenda
                            </a>
                        )}
                    </div>
                    {extras.form && extras.form.length > 0 && (
                        <div className="mt-3 flex items-center gap-1" data-testid="team-form" aria-label="Forma recente">
                            <span className="mr-1 text-xs text-gray-500">Forma:</span>
                            {extras.form.map((item, index) => (
                                <a key={index} href={item.url} title={`${item.home ? 'em casa' : 'fora'} contra ${item.opponent ?? '—'}: ${item.score}`} className={`flex h-6 w-6 items-center justify-center rounded text-xs font-bold text-white ${pill[item.result]}`}>
                                    {item.result}
                                </a>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <div className="space-y-6">
                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-2 text-lg font-bold">Competições</h2>
                    <ul className="space-y-1 text-sm">
                        {participations.map((item) => (
                            <li key={item.url + item.division}>
                                <Link href={item.url} className="font-medium hover:underline">{item.competition} {item.season}</Link>
                                <span className="text-gray-500"> · {item.division}</span>
                            </li>
                        ))}
                    </ul>
                </section>
                {extras.campaigns && extras.campaigns.length > 0 && (
                    <div data-testid="team-campaigns">
                        <DataTable
                            title="Campanhas"
                            caption="Campanhas da equipe por edição"
                            layout="table"
                            paginated={false}
                            searchable={false}
                            rows={extras.campaigns}
                            rowKey={(item) => item.url + item.division}
                            columns={[
                                {
                                    key: 'season',
                                    header: 'Edição',
                                    sticky: true,
                                    value: (item) => `${item.competition} ${item.season}`,
                                    cell: (item) => (
                                        <>
                                            <Link href={item.url} className="font-medium hover:underline">{item.competition} {item.season}</Link>
                                            <span className="block text-xs text-gray-500">{item.division} · {item.status}</span>
                                        </>
                                    ),
                                },
                                { key: 'position', header: 'Pos.', align: 'center', value: (item) => item.position?.position, cell: (item) => <span className="tabular-nums">{item.position ? `${item.position.position}º/${item.position.of}` : '—'}</span> },
                                { key: 'played', header: 'J', align: 'center', value: (item) => item.played, cell: (item) => <span className="tabular-nums">{item.played}</span> },
                                { key: 'wins', header: 'V', align: 'center', value: (item) => item.wins, cell: (item) => <span className="tabular-nums">{item.wins}</span> },
                                { key: 'draws', header: 'E', align: 'center', value: (item) => item.draws, cell: (item) => <span className="tabular-nums">{item.draws}</span> },
                                { key: 'losses', header: 'D', align: 'center', value: (item) => item.losses, cell: (item) => <span className="tabular-nums">{item.losses}</span> },
                                { key: 'goals', header: 'Gols', align: 'center', value: (item) => item.for - item.against, cell: (item) => <span className="tabular-nums">{item.for}:{item.against}</span> },
                            ]}
                        />
                    </div>
                )}
                {extras.scorers && extras.scorers.length > 0 && (
                    <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200" data-testid="team-scorers">
                        <h2 className="mb-2 text-lg font-bold">Artilheiros</h2>
                        <ol className="space-y-1 text-sm">
                            {extras.scorers.map((item, index) => (
                                <li key={index} className="flex justify-between">
                                    <span>{index + 1}. {item.player}</span>
                                    <span className="font-semibold tabular-nums">{item.goals}</span>
                                </li>
                            ))}
                        </ol>
                    </section>
                )}
                {extras.roster && (
                    <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200" data-testid="team-roster">
                        <h2 className="mb-2 text-lg font-bold">Elenco</h2>
                        {extras.roster.length === 0 ? (
                            <p className="text-sm text-gray-500">Elenco ainda não divulgado.</p>
                        ) : (
                            <ul className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                                {extras.roster.map((item, index) => (
                                    <li key={index} className="flex gap-2">
                                        <span className="w-7 text-right tabular-nums text-gray-500">{item.shirt ?? ''}</span>
                                        {item.profile_url ? <Link href={item.profile_url} className="underline">{item.name}</Link> : <span>{item.name}</span>}
                                        {item.shirt === null && <span className="text-xs text-gray-500">{item.role}</span>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}
                <div className="grid gap-6 md:grid-cols-2">
                    <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <h2 className="mb-2 text-lg font-bold">Próximos jogos</h2>
                        <GameList games={upcoming} empty="Nenhum jogo agendado." />
                    </section>
                    <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <h2 className="mb-2 text-lg font-bold">Resultados</h2>
                        <GameList games={results} empty="Nenhum resultado ainda." />
                    </section>
                </div>
            </div>
        </PortalLayout>
    );
}
