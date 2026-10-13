import { GameList, PortalGame, PortalTeam } from '@/Components/Portal/GameList';
import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Link } from '@inertiajs/react';

interface Podium {
    name: string;
    champion: PortalTeam | null;
    runner_up: PortalTeam | null;
}

interface SeasonItem {
    id: string;
    name: string;
    year: number | null;
    status_label: string;
    url: string;
    divisions: Podium[];
}

interface Title {
    team: PortalTeam;
    titles: number;
    seasons: string[];
}

interface Records {
    unit: string;
    unit_singular: string;
    games: number;
    total: number;
    average: number;
    biggest_wins: (PortalGame & { season: string })[];
    highest_scoring: (PortalGame & { season: string })[];
}

interface Props {
    theme: Theme;
    seo: Seo;
    competition: { name: string; description: string | null; sport: string };
    latest_url: string;
    seasons: SeasonItem[];
    titles: Title[];
    records: Records | null;
}

function TeamName({ team }: { team: PortalTeam | null }) {
    if (!team) return <span className="text-gray-400">—</span>;

    return (
        <Link href={team.url} className="inline-flex items-center gap-1.5 hover:underline">
            {team.logo_url && <img src={team.logo_url} alt="" width={20} height={20} loading="lazy" className="h-5 w-5 object-contain" />}
            {team.name}
        </Link>
    );
}

export default function Competition({ theme, seo, competition, latest_url, seasons, titles, records }: Props) {
    return (
        <PortalLayout theme={theme} seo={seo}>
            <h1 className="text-2xl font-extrabold">{competition.name}</h1>
            <p className="text-sm text-gray-500">{competition.sport}</p>
            {competition.description && <p className="mt-2 max-w-3xl text-gray-700">{competition.description}</p>}
            <p className="mt-3">
                <Link href={latest_url} className="font-semibold text-emerald-700 hover:underline">
                    Ver a edição mais recente →
                </Link>
            </p>

            <section className="mt-8">
                <h2 className="mb-3 text-lg font-bold">Edições</h2>
                <ul className="divide-y divide-gray-200 rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    {seasons.map((season) => (
                        <li key={season.id} className="px-4 py-3">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <Link href={season.url} className="font-semibold hover:underline">
                                    {season.name}
                                </Link>
                                <span className="text-xs text-gray-500">{season.status_label}</span>
                            </div>
                            {season.divisions.map((division) => (
                                <div key={division.name} className="mt-1 text-sm text-gray-700">
                                    {season.divisions.length > 1 && <span className="mr-2 text-gray-500">{division.name}:</span>}
                                    {division.champion ? (
                                        <>
                                            <span className="mr-1" aria-hidden>
                                                🏆
                                            </span>
                                            <span className="sr-only">Campeã:</span>
                                            <TeamName team={division.champion} />
                                            {division.runner_up && (
                                                <span className="ml-3 text-gray-600">
                                                    Vice: <TeamName team={division.runner_up} />
                                                </span>
                                            )}
                                        </>
                                    ) : (
                                        <span className="text-gray-400">Campeão ainda não definido</span>
                                    )}
                                </div>
                            ))}
                        </li>
                    ))}
                </ul>
            </section>

            {titles.length > 0 && (
                <section className="mt-8">
                    <h2 className="mb-3 text-lg font-bold">Títulos</h2>
                    <ul className="divide-y divide-gray-200 rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                        {titles.map((item) => (
                            <li key={item.team.id} className="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                                <span>
                                    <TeamName team={item.team} />
                                    <span className="ml-2 text-xs text-gray-500">{item.seasons.join(', ')}</span>
                                </span>
                                <span className="font-bold">{item.titles}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {records && records.games > 0 && (
                <section className="mt-8">
                    <h2 className="mb-1 text-lg font-bold">Recordes</h2>
                    <p className="mb-3 text-sm text-gray-600">
                        {records.games} jogos encerrados em campo · {records.total} {records.unit} · média de {records.average} por jogo
                    </p>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div>
                            <h3 className="mb-2 font-semibold">Maiores vitórias</h3>
                            <GameList games={records.biggest_wins} />
                        </div>
                        <div>
                            <h3 className="mb-2 font-semibold">Jogos com mais {records.unit}</h3>
                            <GameList games={records.highest_scoring} />
                        </div>
                    </div>
                </section>
            )}
        </PortalLayout>
    );
}
