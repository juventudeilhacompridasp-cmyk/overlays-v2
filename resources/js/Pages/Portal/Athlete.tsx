import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Link } from '@inertiajs/react';

interface Props {
    theme: Theme;
    seo: Seo;
    athlete: {
        name: string;
        participations: { team: string; team_url: string; competition: string; season: string; division: string; url: string; shirt: number | null; games: number; goals: number }[];
        totals: { games: number; goals: number };
    };
}

export default function Athlete({ theme, seo, athlete }: Props) {
    return (
        <PortalLayout theme={theme} seo={seo}>
            <h1 className="text-2xl font-extrabold">{athlete.name}</h1>
            <dl className="mt-4 grid grid-cols-2 gap-3 sm:max-w-sm" data-testid="athlete-totals">
                <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <dt className="text-xs text-gray-500">Jogos</dt>
                    <dd className="text-2xl font-bold tabular-nums">{athlete.totals.games}</dd>
                </div>
                <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <dt className="text-xs text-gray-500">Gols</dt>
                    <dd className="text-2xl font-bold tabular-nums">{athlete.totals.goals}</dd>
                </div>
            </dl>

            <section className="mt-6 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h2 className="mb-2 text-lg font-bold">Equipes e edições</h2>
                <ul className="divide-y divide-gray-100 text-sm" data-testid="athlete-participations">
                    {athlete.participations.map((item, index) => (
                        <li key={index} className="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span>
                                <Link href={item.team_url} className="font-medium underline">{item.team}</Link>
                                {item.shirt !== null && <span className="ml-2 text-gray-500">camisa {item.shirt}</span>}
                                <br />
                                <Link href={item.url} className="text-gray-600 hover:underline">{item.competition} · {item.season} · {item.division}</Link>
                            </span>
                            <span className="text-gray-600">{item.games} jogo(s) · {item.goals} gol(s)</span>
                        </li>
                    ))}
                </ul>
            </section>
        </PortalLayout>
    );
}
