import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Link } from '@inertiajs/react';

interface Props {
    theme: Theme;
    seo: Seo;
    query: string;
    competitions: { name: string; url: string }[];
    teams: { name: string; city: string | null; logo_url: string | null; url: string }[];
    venues: { name: string; city: string | null; url: string }[];
}

/** Busca por nome de competições e equipes de edições publicadas (nunca atletas). */
export default function Search({ theme, seo, query, competitions, teams, venues }: Props) {
    const searched = query.trim().length >= 2;

    return (
        <PortalLayout theme={theme} seo={seo}>
            <h1 className="mb-4 text-2xl font-extrabold">Busca</h1>
            <form method="get" action={route('portal.search')} role="search" className="mb-6 flex gap-2">
                <input type="search" name="q" defaultValue={query} maxLength={60} placeholder="Competição ou equipe" aria-label="Buscar competição ou equipe" className="min-w-0 flex-1 rounded border border-gray-300 px-3 py-2" />
                <button type="submit" className="rounded px-4 py-2 font-semibold text-white" style={{ backgroundColor: theme.primary }}>Buscar</button>
            </form>

            {!searched && <p className="text-gray-500">Digite pelo menos 2 letras para buscar.</p>}
            {searched && competitions.length === 0 && teams.length === 0 && venues.length === 0 && <p className="text-gray-500" data-testid="search-empty">Nada encontrado para “{query}”.</p>}

            {competitions.length > 0 && (
                <section className="mb-6 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200" data-testid="search-competitions">
                    <h2 className="mb-2 text-lg font-bold">Competições</h2>
                    <ul className="divide-y divide-gray-100">
                        {competitions.map((competition) => (
                            <li key={competition.url}><Link href={competition.url} className="block py-2 font-medium hover:underline">{competition.name}</Link></li>
                        ))}
                    </ul>
                </section>
            )}

            {teams.length > 0 && (
                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200" data-testid="search-teams">
                    <h2 className="mb-2 text-lg font-bold">Equipes</h2>
                    <ul className="divide-y divide-gray-100">
                        {teams.map((team) => (
                            <li key={team.url}>
                                <Link href={team.url} className="flex items-center gap-3 py-2 hover:underline">
                                    {team.logo_url ? <img src={team.logo_url} alt="" className="h-8 w-8 object-contain" /> : <span className="h-8 w-8" />}
                                    <span className="font-medium">{team.name}</span>
                                    {team.city && <span className="text-sm text-gray-500">{team.city}</span>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {venues.length > 0 && (
                <section className="mt-6 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200" data-testid="search-venues">
                    <h2 className="mb-2 text-lg font-bold">Locais</h2>
                    <ul className="divide-y divide-gray-100">
                        {venues.map((venue) => (
                            <li key={venue.url}><Link href={venue.url} className="block py-2 hover:underline"><span className="font-medium">{venue.name}</span>{venue.city && <span className="ml-2 text-sm text-gray-500">{venue.city}</span>}</Link></li>
                        ))}
                    </ul>
                </section>
            )}
        </PortalLayout>
    );
}
