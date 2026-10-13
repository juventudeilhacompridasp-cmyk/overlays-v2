import Frame, { EmbedOptions, line, muted } from '@/Components/Embed/Frame';
import { PortalGame, formatWhen } from '@/Components/Portal/GameList';
import { Head } from '@inertiajs/react';

function Section({ title, games, options }: { title: string; games: PortalGame[]; options: EmbedOptions }) {
    if (games.length === 0) return null;

    return (
        <section className="px-3 pb-2">
            <h2 className="pt-3 text-xs font-semibold uppercase" style={{ color: 'var(--accent)' }}>{title}</h2>
            <ul className={`divide-y ${line(options)}`}>
                {games.map((game) => (
                    <li key={game.id} className="py-2">
                        <a href={game.url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 hover:underline">
                            <span className="flex-1 truncate text-right font-medium">{game.home?.name ?? 'A definir'}</span>
                            <span className="min-w-14 rounded px-2 py-0.5 text-center font-bold tabular-nums text-white" style={{ backgroundColor: 'var(--accent)' }}>
                                {game.decided || game.live ? `${game.home_score} x ${game.away_score}` : 'x'}
                            </span>
                            <span className="flex-1 truncate font-medium">{game.away?.name ?? 'A definir'}</span>
                        </a>
                        <div className={`text-center text-xs ${muted(options)}`}>{game.live ? 'Ao vivo' : game.decided ? game.status_label : formatWhen(game.scheduled_at)}{game.venue ? ` · ${game.venue}` : ''}</div>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Games({ options, title, live, upcoming, recent, portal_url }: { options: EmbedOptions; title: string; live: PortalGame[]; upcoming: PortalGame[]; recent: PortalGame[]; portal_url: string }) {
    const empty = live.length + upcoming.length + recent.length === 0;

    return (
        <Frame options={options} footer={{ label: 'Todos os jogos', href: portal_url }}>
            <Head title={title} />
            <h1 className="px-3 pt-3 text-base font-bold">{title}</h1>
            {empty && <p className={`px-3 py-4 ${muted(options)}`}>Nenhum jogo ainda.</p>}
            <Section title="Ao vivo" games={live} options={options} />
            <Section title="Próximos jogos" games={upcoming} options={options} />
            <Section title="Resultados" games={recent} options={options} />
        </Frame>
    );
}
