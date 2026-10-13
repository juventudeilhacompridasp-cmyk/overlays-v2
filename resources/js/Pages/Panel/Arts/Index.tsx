import { Badge, Card, Empty, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link } from '@inertiajs/react';

type Option = { value: string; label: string };

interface GameCard {
    id: string;
    home: string;
    away: string;
    home_score: number | null;
    away_score: number | null;
    status: string;
    status_label: string;
    decided: boolean;
    scheduled_at: string | null;
    competition: string;
    phase: string;
}

interface Props {
    upcoming: GameCard[];
    recent: GameCard[];
    templates: { id: string; name: string; format: string; width: number; height: number }[];
    presets: { key: string; name: string; format: string }[];
    formats: (Option & { width: number; height: number })[];
    kinds: Option[];
}

function GameList({ games, kinds }: { games: GameCard[]; kinds: Option[] }) {
    if (games.length === 0) return <p className="text-sm text-gray-500">Nenhuma partida por aqui ainda.</p>;

    return (
        <ul className="divide-y divide-gray-100">
            {games.map((game) => (
                <li key={game.id} className="flex flex-wrap items-center justify-between gap-2 py-3">
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-gray-900">
                            {game.home} {game.decided ? `${game.home_score ?? 0} × ${game.away_score ?? 0}` : '×'} {game.away}
                        </p>
                        <p className="text-xs text-gray-500">
                            {game.competition} · {game.phase}
                            {game.scheduled_at ? ` · ${game.scheduled_at}` : ''} <Badge tone={game.decided ? 'gray' : 'blue'}>{game.status_label}</Badge>
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {kinds.map((kind) => (
                            <LinkButton key={kind.value} small variant="secondary" href={route('panel.arts.editor', { game: game.id, kind: kind.value })}>
                                {kind.label}
                            </LinkButton>
                        ))}
                    </div>
                </li>
            ))}
        </ul>
    );
}

export default function Index({ upcoming, recent, templates, presets, formats, kinds }: Props) {
    const formatLabel = (key: string, width: number, height: number) => (formats.find((item) => item.value === key)?.label.split(' (')[0] ?? 'Personalizado') + ` · ${width}×${height}`;

    return (
        <PanelLayout>
            <Head title="Artes" />
            <PageHeader title="Artes das partidas" subtitle="Escolha uma partida e o tipo de arte. Dá para mudar proporção, cores, fontes, layout e o que aparece, e baixar o PNG pronto." />

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="Próximos jogos">
                    <GameList games={upcoming} kinds={kinds} />
                </Card>
                <Card title="Resultados recentes">
                    <GameList games={recent} kinds={kinds} />
                </Card>
            </div>

            <Card title="Modelos" className="mt-6">
                <p className="mb-3 text-sm text-gray-600">Os modelos guardam formato e estilo. Abra o editor numa partida para aplicar um modelo pronto ou salvar o seu.</p>
                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-gray-900">Modelos prontos</h3>
                        <ul className="space-y-1 text-sm text-gray-700">
                            {presets.map((preset) => (
                                <li key={preset.key}>
                                    {preset.name} <span className="text-xs text-gray-500">· {formats.find((item) => item.value === preset.format)?.label.split(' (')[0]}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-gray-900">Modelos da organização</h3>
                        {templates.length === 0 ? (
                            <Empty title="Nenhum modelo salvo">No editor, ajuste a arte e use “Salvar como novo”.</Empty>
                        ) : (
                            <ul className="space-y-1 text-sm text-gray-700">
                                {templates.map((template) => (
                                    <li key={template.id}>
                                        {template.name} <span className="text-xs text-gray-500">· {formatLabel(template.format, template.width, template.height)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </Card>
            <p className="mt-4 text-xs text-gray-500">
                Dúvidas? <Link href={route('panel.help.index')} className="text-emerald-700 hover:underline">Central de ajuda</Link>
            </p>
        </PanelLayout>
    );
}
