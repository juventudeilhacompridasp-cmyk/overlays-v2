import { Button, Card, Empty, Field, PageHeader, Select, Textarea } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Season {
    id: string;
    name: string;
    published: boolean;
    divisions: { id: string; name: string }[];
}

interface GameOption {
    id: string;
    name: string;
    live: boolean;
    scheduled_at: string | null;
    published: boolean;
}

type Kind = 'jogo' | 'classificacao' | 'jogos';

const RESIZE_SCRIPT = `<script>window.addEventListener('message',function(e){if(e.data&&e.data.type==='overlays-embed-height'){var f=document.querySelectorAll('iframe[data-overlays-embed]');for(var i=0;i<f.length;i++){if(f[i].contentWindow===e.source){f[i].style.height=e.data.height+'px';}}}});</script>`;

export default function Index({ base_url, seasons, games }: { base_url: string; seasons: Season[]; games: GameOption[] }) {
    const [kind, setKind] = useState<Kind>('jogos');
    const [seasonId, setSeasonId] = useState(seasons[0]?.id ?? '');
    const [divisionId, setDivisionId] = useState('');
    const [gameId, setGameId] = useState(games[0]?.id ?? '');
    const [dark, setDark] = useState(false);
    const [accent, setAccent] = useState('#059669');
    const [rows, setRows] = useState('10');
    const [copied, setCopied] = useState(false);

    const season = seasons.find((item) => item.id === seasonId);
    const division = season?.divisions.find((item) => item.id === divisionId) ?? season?.divisions[0];

    const url = useMemo(() => {
        const params = new URLSearchParams();
        if (dark) params.set('tema', 'escuro');
        if (accent !== '#059669') params.set('cor', accent);
        if (kind === 'classificacao' && rows !== '10') params.set('linhas', rows);
        const query = params.toString() ? `?${params.toString()}` : '';
        const target = kind === 'jogo' ? (gameId ? `jogo/${gameId}` : '') : kind === 'classificacao' ? (division ? `classificacao/${division.id}` : '') : seasonId ? `jogos/${seasonId}` : '';

        return target ? `${base_url}/${target}${query}` : '';
    }, [kind, gameId, division, seasonId, dark, accent, rows, base_url]);

    const code = url ? `<iframe src="${url}" data-overlays-embed style="width:100%;border:0;height:${kind === 'jogo' ? 120 : 420}px" loading="lazy" title="Widget"></iframe>\n${RESIZE_SCRIPT}` : '';
    const published = kind === 'jogo' ? games.find((game) => game.id === gameId)?.published : season?.published;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            // sem permissão da área de transferência: o texto continua selecionável
        }
    };

    return (
        <PanelLayout>
            <Head title="Widgets" />
            <PageHeader title="Widgets para outros sites" subtitle="Cole o código no site de um patrocinador, de uma prefeitura ou do clube. Mostra só dados públicos e se atualiza sozinho." />
            {seasons.length === 0 ? (
                <Empty title="Nenhuma edição cadastrada.">Crie uma edição para gerar widgets.</Empty>
            ) : (
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card title="Configurar">
                        <div className="space-y-4">
                            <Field label="Widget" htmlFor="kind">
                                <Select id="kind" value={kind} onChange={(e) => setKind(e.target.value as Kind)}>
                                    <option value="jogos">Jogos da edição (ao vivo, próximos e resultados)</option>
                                    <option value="classificacao">Classificação de uma divisão</option>
                                    <option value="jogo">Placar ao vivo de um jogo</option>
                                </Select>
                            </Field>
                            {kind === 'jogo' ? (
                                <Field label="Jogo" htmlFor="game">
                                    <Select id="game" value={gameId} onChange={(e) => setGameId(e.target.value)}>
                                        {games.map((game) => (
                                            <option key={game.id} value={game.id}>
                                                {game.live ? '● ' : ''}
                                                {game.name} {game.scheduled_at ? `(${game.scheduled_at})` : ''}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            ) : (
                                <Field label="Edição" htmlFor="season">
                                    <Select id="season" value={seasonId} onChange={(e) => setSeasonId(e.target.value)}>
                                        {seasons.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            )}
                            {kind === 'classificacao' && (
                                <>
                                    <Field label="Divisão" htmlFor="division">
                                        <Select id="division" value={division?.id ?? ''} onChange={(e) => setDivisionId(e.target.value)}>
                                            {season?.divisions.map((item) => (
                                                <option key={item.id} value={item.id}>
                                                    {item.name}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <Field label="Linhas por tabela" htmlFor="rows">
                                        <Select id="rows" value={rows} onChange={(e) => setRows(e.target.value)}>
                                            {['5', '8', '10', '16', '20', '50'].map((value) => (
                                                <option key={value} value={value}>
                                                    {value}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                </>
                            )}
                            <div className="flex flex-wrap items-center gap-4">
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" className="rounded border-gray-300 text-emerald-600" checked={dark} onChange={(e) => setDark(e.target.checked)} />
                                    Tema escuro
                                </label>
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    Cor de destaque
                                    <input type="color" value={accent} onChange={(e) => setAccent(e.target.value)} className="h-8 w-12 cursor-pointer rounded border border-gray-300" />
                                </label>
                            </div>
                            {published === false && <p className="rounded bg-amber-50 px-3 py-2 text-sm text-amber-800">Esta edição ainda é rascunho: o widget só aparece depois de publicá-la.</p>}
                        </div>
                    </Card>
                    <Card title="Código para colar">
                        <Textarea readOnly rows={7} value={code} className="font-mono text-xs" onFocus={(e) => e.currentTarget.select()} />
                        <div className="mt-3 flex items-center gap-3">
                            <Button onClick={copy} disabled={!code}>
                                {copied ? 'Copiado!' : 'Copiar código'}
                            </Button>
                            {url && (
                                <a href={url} target="_blank" rel="noreferrer" className="text-sm text-emerald-700 underline">
                                    Abrir prévia
                                </a>
                            )}
                        </div>
                        <p className="mt-3 text-xs text-gray-500">O trecho com &lt;script&gt; é opcional: ele ajusta a altura do quadro ao conteúdo. Sem ele, use a altura fixa do iframe.</p>
                    </Card>
                </div>
            )}
        </PanelLayout>
    );
}
