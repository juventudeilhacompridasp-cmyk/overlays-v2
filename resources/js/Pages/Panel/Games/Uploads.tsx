import { Badge, Button, Card, DeleteButton, Empty, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

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
}

interface Props {
    game: { id: string; home: string | null; away: string | null; division: string; season: string; competition: string; scheduled_at: string | null };
    max_mb: number;
    uploads: Upload[];
}

const kindLabel: Record<Upload['kind'], string> = { image: 'Foto', pdf: 'PDF', structured: 'Planilha' };

const tone = (status: string): 'gray' | 'blue' | 'green' | 'amber' | 'red' => (status === 'failed' ? 'red' : status === 'reviewed' ? 'green' : status === 'read' ? 'amber' : status === 'reading' ? 'blue' : 'gray');

function size(bytes: number): string {
    return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export default function Uploads({ game, max_mb, uploads }: Props) {
    const canOperate = useCan('operate-games');
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null }>({ file: null });

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
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge tone={tone(upload.status)}>{upload.status_label}</Badge>
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
