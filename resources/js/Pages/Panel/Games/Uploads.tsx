import { Badge, Button, Card, DeleteButton, Empty, Field, Input, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

interface Upload {
    id: string;
    name: string;
    kind: 'image' | 'pdf' | 'structured';
    size: number;
    status: string;
    status_label: string;
    error: string | null;
    uploaded_by: string | null;
    created_at: string;
    summary: {
        home_team: string | null;
        away_team: string | null;
        home_score: number | null;
        away_score: number | null;
        lineups: number;
        events: number;
        notes: string | null;
        confidence: number | null;
    } | null;
}

interface Ai {
    enabled: boolean;
    available: boolean;
    reads_left: number;
    max_reads_per_game: number;
    ceiling: number;
    can_configure: boolean;
}

interface Props {
    game: { id: string; home: string | null; away: string | null; division: string; season: string; competition: string; scheduled_at: string | null };
    max_mb: number;
    ai: Ai;
    uploads: Upload[];
}

const kindLabel: Record<Upload['kind'], string> = { image: 'Foto', pdf: 'PDF', structured: 'Planilha' };

const tone = (status: string): 'gray' | 'blue' | 'green' | 'amber' | 'red' => (status === 'failed' ? 'red' : status === 'reviewed' ? 'green' : status === 'read' ? 'amber' : status === 'reading' ? 'blue' : 'gray');

function size(bytes: number): string {
    return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export default function Uploads({ game, max_mb, ai, uploads }: Props) {
    const canOperate = useCan('operate-games');
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null }>({ file: null });

    const settings = useForm({ ai_enabled: ai.enabled, max_reads_per_game: ai.max_reads_per_game });
    const reading = uploads.some((upload) => upload.status === 'reading');

    // Enquanto alguma súmula está sendo lida, confere o andamento de tempos em tempos.
    useEffect(() => {
        if (!reading) return;
        const timer = window.setInterval(() => router.reload({ only: ['uploads', 'ai'] }), 5000);
        return () => window.clearInterval(timer);
    }, [reading]);

    const read = (id: string) => router.post(route('panel.games.uploads.read', id), {}, { preserveScroll: true });

    const saveSettings = (event: FormEvent) => {
        event.preventDefault();
        settings.patch(route('panel.sheet-import.settings'), { preserveScroll: true });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('panel.games.uploads.store', game.id), { forceFormData: true, preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <PanelLayout>
            <Head title="Súmulas enviadas" />
            <PageHeader
                title="Súmulas enviadas"
                subtitle={`${game.home ?? 'A definir'} x ${game.away ?? 'A definir'} · ${game.division} · ${game.season}`}
                breadcrumbs={[
                    { label: 'Súmula', href: route('panel.games.sheet', game.id) },
                    { label: 'Súmulas enviadas' },
                ]}
            />

            {canOperate && (
                <Card title="Enviar súmula" className="mb-6 max-w-3xl">
                    <form onSubmit={submit} className="flex flex-wrap items-center gap-3">
                        <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.csv,.xlsx" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} className="text-sm" />
                        <Button type="submit" disabled={processing || !data.file}>
                            Enviar
                        </Button>
                    </form>
                    {errors.file && <p className="mt-2 text-sm text-red-600">{errors.file}</p>}
                    <p className="mt-3 text-xs text-gray-500">
                        Foto (JPG, PNG, WebP), PDF ou planilha (CSV, XLSX), até {max_mb} MB. O arquivo tem nomes de atletas, inclusive menores: fica em área privada e só abre por link temporário.
                    </p>
                    <p className="mt-2 text-xs text-gray-500">
                        Planilha digital: use o <a href={route('panel.sheet-import.template')} className="text-emerald-700 underline">modelo CSV</a> (colunas tipo, equipe, nome, camisa, minuto, valor). É lida na hora, sem IA e sem serviço externo.
                    </p>
                    {ai.enabled && ai.available ? (
                        <p className="mt-2 rounded-md bg-amber-50 p-2 text-xs text-amber-800" data-testid="ai-notice">
                            Aviso de privacidade: para ler foto ou PDF, a imagem da súmula é enviada a um serviço externo de IA (Claude, da Anthropic), só para extrair o texto — sem dados da plataforma além do esporte e dos nomes das equipes. Documentos pessoais (CPF, RG) são ignorados. Toda leitura passa por revisão humana. Restam {ai.reads_left} leitura(s) por IA neste jogo.
                        </p>
                    ) : (
                        <p className="mt-2 text-xs text-gray-500" data-testid="ai-off">
                            {ai.enabled ? 'A leitura de foto/PDF por IA ainda não foi configurada neste ambiente.' : 'A leitura de foto/PDF por IA está desligada nesta organização.'} Planilhas CSV/XLSX continuam sendo lidas normalmente.
                        </p>
                    )}
                </Card>
            )}

            {ai.can_configure && (
                <Card title="Leitura por IA (administrador)" className="mb-6 max-w-3xl">
                    <form onSubmit={saveSettings} className="space-y-3">
                        <label className="flex items-center gap-2 text-sm text-gray-800">
                            <input type="checkbox" checked={settings.data.ai_enabled} onChange={(e) => settings.setData('ai_enabled', e.target.checked)} />
                            Permitir enviar fotos e PDFs a um serviço externo de IA para leitura
                        </label>
                        <Field label={`Leituras por IA por jogo (1 a ${ai.ceiling})`} error={settings.errors.max_reads_per_game}>
                            <Input type="number" min={1} max={ai.ceiling} value={settings.data.max_reads_per_game} onChange={(e) => settings.setData('max_reads_per_game', Number(e.target.value))} />
                        </Field>
                        <p className="text-xs text-gray-500">Desligado, só planilhas CSV/XLSX são lidas. Lembre de citar o uso de IA nos Termos e na Privacidade publicados pela organização.</p>
                        <Button type="submit" disabled={settings.processing}>
                            Salvar
                        </Button>
                    </form>
                </Card>
            )}

            {uploads.length === 0 ? (
                <Empty title="Nenhuma súmula enviada">Quando a súmula de papel for enviada depois do jogo, ela aparece aqui.</Empty>
            ) : (
                <Card className="max-w-3xl">
                    <ul className="divide-y divide-gray-100" data-testid="uploads">
                        {uploads.map((upload) => (
                            <li key={upload.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                <div className="min-w-0">
                                    <div className="truncate font-medium text-gray-900">{upload.name}</div>
                                    <div className="text-xs text-gray-500">
                                        {kindLabel[upload.kind]} · {size(upload.size)} · {new Date(upload.created_at).toLocaleString('pt-BR')}
                                        {upload.uploaded_by ? ` · ${upload.uploaded_by}` : ''}
                                    </div>
                                    {upload.error && <div className="text-xs text-red-700">{upload.error}</div>}
                                    {upload.summary && (
                                        <div className="mt-1 text-xs text-gray-700" data-testid="summary">
                                            Lido: {upload.summary.home_team ?? '?'} {upload.summary.home_score ?? '?'} x {upload.summary.away_score ?? '?'} {upload.summary.away_team ?? '?'} · {upload.summary.lineups} na escalação · {upload.summary.events} lance(s)
                                            {upload.summary.confidence !== null ? ` · confiança ${Math.round(upload.summary.confidence * 100)}%` : ''}
                                            {upload.summary.notes ? ` · ${upload.summary.notes}` : ''}
                                        </div>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge tone={tone(upload.status)}>{upload.status_label}</Badge>
                                    {canOperate && ['uploaded', 'failed', 'read'].includes(upload.status) && (
                                        <Button type="button" small variant="secondary" onClick={() => read(upload.id)}>
                                            {upload.status === 'uploaded' ? 'Ler súmula' : 'Ler de novo'}
                                        </Button>
                                    )}
                                    {['read', 'reviewed'].includes(upload.status) && (
                                        <Link href={route('panel.games.uploads.review', upload.id)} className="text-emerald-700 underline">
                                            {upload.status === 'read' ? 'Revisar' : 'Rever'}
                                        </Link>
                                    )}
                                    {upload.status === 'reviewed' && (
                                        <Link href={route('panel.games.uploads.report', upload.id)} className="text-emerald-700 underline">
                                            Conferência
                                        </Link>
                                    )}
                                    <a href={route('panel.games.uploads.show', upload.id)} className="text-emerald-700 underline">
                                        Abrir
                                    </a>
                                    {canOperate && <DeleteButton href={route('panel.games.uploads.destroy', upload.id)} message="Excluir esta súmula enviada?" label="Excluir" />}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </PanelLayout>
    );
}
