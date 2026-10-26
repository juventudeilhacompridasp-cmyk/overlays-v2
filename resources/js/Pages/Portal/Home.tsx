import { GameList, PortalGame } from '@/Components/Portal/GameList';
import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Link } from '@inertiajs/react';

interface Props {
    theme: Theme;
    seo: Seo;
    competitions: { id: string; name: string; description: string | null; seasons: { id: string; name: string; status_label: string; url: string }[] }[];
    live: PortalGame[];
    upcoming: PortalGame[];
    recent: PortalGame[];
    sections: { live: boolean; upcoming: boolean; recent: boolean; competitions: boolean };
}

function Section({ id, title, children }: { id?: string; title: string; children: React.ReactNode }) {
    return (
        <section id={id} className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <h2 className="mb-2 text-lg font-bold">{title}</h2>
            {children}
        </section>
    );
}

export default function Home({ theme, seo, competitions, live, upcoming, recent, sections }: Props) {
    return (
        <PortalLayout theme={theme} seo={seo}>
            <h1 className="sr-only">{theme.name}</h1>
            <div className="space-y-6">
                <section className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" aria-label="Sugestões para visitantes">
                    <p className="text-xs font-bold uppercase tracking-wider text-emerald-700">O que você procura?</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-3">
                        <Link href={route('portal.search')} className="rounded-lg bg-gray-50 p-4 transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                            <span className="font-bold">Encontrar uma equipe</span>
                            <span className="mt-1 block text-sm text-gray-600">Busque competições e equipes publicadas.</span>
                        </Link>
                        <a href="#ao-vivo" className="rounded-lg bg-gray-50 p-4 transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                            <span className="font-bold">Acompanhar jogos</span>
                            <span className="mt-1 block text-sm text-gray-600">Veja partidas ao vivo, próximas e recentes.</span>
                        </a>
                        <a href={route('marketing.landing')} className="rounded-lg bg-gray-50 p-4 transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                            <span className="font-bold">Organizar um campeonato</span>
                            <span className="mt-1 block text-sm text-gray-600">Conheça a plataforma para gestão e transmissão.</span>
                        </a>
                    </div>
                </section>
                {sections.live && live.length > 0 && (
                    <Section title="Ao vivo agora" id="ao-vivo">
                        <GameList games={live} />
                    </Section>
                )}

                {(sections.upcoming || sections.recent) && (
                    <div className={`grid gap-6 ${sections.upcoming && sections.recent ? 'md:grid-cols-2' : ''}`}>
                        {sections.upcoming && (
                            <Section title="Próximos jogos" id="proximos">
                                <GameList games={upcoming} empty="Nenhum jogo agendado." />
                            </Section>
                        )}
                        {sections.recent && (
                            <Section title="Resultados recentes" id="recentes">
                                <GameList games={recent} empty="Nenhum resultado ainda." />
                            </Section>
                        )}
                    </div>
                )}

                {sections.competitions && (
                <Section title="Competições" id="competicoes">
                    {competitions.length === 0 ? (
                        <p className="py-2 text-sm text-gray-500">Nenhuma competição publicada ainda.</p>
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {competitions.map((competition) => (
                                <li key={competition.id} className="py-3">
                                    <div className="font-semibold">{competition.name}</div>
                                    {competition.description && <p className="text-sm text-gray-600">{competition.description}</p>}
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {competition.seasons.map((season) => (
                                            <Link key={season.id} href={season.url} className="rounded-full bg-gray-100 px-3 py-1 text-sm hover:bg-gray-200">
                                                {season.name} <span className="text-xs text-gray-500">· {season.status_label}</span>
                                            </Link>
                                        ))}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
                )}
            </div>
        </PortalLayout>
    );
}
