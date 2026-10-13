import { GameList, PortalGame } from '@/Components/Portal/GameList';
import PushButton from '@/Components/Portal/PushButton';
import ShareButtons from '@/Components/Portal/ShareButtons';
import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Link } from '@inertiajs/react';

interface Props {
    theme: Theme;
    seo: Seo;
    team: { id: string; name: string; city: string | null; logo_url: string | null; color: string | null };
    participations: { competition: string; season: string; division: string; url: string }[];
    games: PortalGame[];
    push_key: string | null;
}

export default function Team({ theme, seo, team, participations, games, push_key }: Props) {
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
                    </div>
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
