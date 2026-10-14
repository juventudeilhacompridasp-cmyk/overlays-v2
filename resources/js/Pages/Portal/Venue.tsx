import { GameList, PortalGame } from '@/Components/Portal/GameList';
import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';

interface Props {
    theme: Theme;
    seo: Seo;
    venue: { name: string; city: string | null; address: string | null };
    upcoming: PortalGame[];
    recent: PortalGame[];
}

/** Local de jogo: endereço e os jogos de edições publicadas. */
export default function Venue({ theme, seo, venue, upcoming, recent }: Props) {
    return (
        <PortalLayout theme={theme} seo={seo}>
            <h1 className="text-2xl font-extrabold">{venue.name}</h1>
            <p className="mb-6 text-gray-600" data-testid="venue-address">{[venue.address, venue.city].filter(Boolean).join(' · ') || 'Endereço não informado'}</p>
            <div className="grid gap-6 md:grid-cols-2">
                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-2 text-lg font-bold">Próximos jogos</h2>
                    <GameList games={upcoming} empty="Nenhum jogo agendado." />
                </section>
                <section className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-2 text-lg font-bold">Jogos realizados</h2>
                    <GameList games={recent} empty="Nenhum jogo realizado ainda." />
                </section>
            </div>
        </PortalLayout>
    );
}
