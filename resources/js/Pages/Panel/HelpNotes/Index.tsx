import { Badge, Button, Card, DeleteButton, Empty, Field, Input, PageHeader, Select, Textarea } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Note {
    id: string;
    audience: string;
    title: string;
    body: string;
}

interface Props {
    notes: Note[];
    audiences: { value: string; label: string }[];
    limit: number;
}

/** Notas próprias da organização na central de ajuda: criar, editar e excluir (só administrador). */
export default function Index({ notes, audiences, limit }: Props) {
    const [editing, setEditing] = useState<Note | null>(null);
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm({ audience: audiences[0]?.value ?? 'panel', title: '', body: '' });

    const label = (value: string) => audiences.find((audience) => audience.value === value)?.label ?? value;
    const edit = (note: Note) => {
        setEditing(note);
        setData({ audience: note.audience, title: note.title, body: note.body });
        clearErrors();
    };
    const cancel = () => {
        setEditing(null);
        reset();
        clearErrors();
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: cancel };

        if (editing) patch(route('panel.help-notes.update', editing.id), options);
        else post(route('panel.help-notes.store'), options);
    };

    return (
        <PanelLayout>
            <Head title="Notas da ajuda" />
            <PageHeader title="Notas da ajuda" subtitle={`Procedimentos internos da organização, na central de ajuda (${notes.length} de ${limit}). Só a sua organização vê.`} />

            <div className="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
                <Card title={editing ? 'Editar nota' : 'Nova nota'}>
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Quem vê" htmlFor="audience" error={errors.audience}>
                            <Select id="audience" value={data.audience} onChange={(event) => setData('audience', event.target.value)}>
                                {audiences.map((audience) => (
                                    <option key={audience.value} value={audience.value}>
                                        {audience.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Título" htmlFor="title" error={errors.title}>
                            <Input id="title" value={data.title} maxLength={120} onChange={(event) => setData('title', event.target.value)} />
                        </Field>
                        <Field label="Texto" htmlFor="body" error={errors.body} hint="Parágrafos separados por linha em branco; ## título, - lista, 1. passos, **negrito**. Links: [texto](help:artigo) ou [texto](route:nome). Nunca HTML.">
                            <Textarea id="body" rows={10} maxLength={5000} value={data.body} onChange={(event) => setData('body', event.target.value)} />
                        </Field>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={processing}>
                                {editing ? 'Salvar nota' : 'Criar nota'}
                            </Button>
                            {editing && (
                                <Button type="button" variant="secondary" onClick={cancel}>
                                    Cancelar
                                </Button>
                            )}
                        </div>
                    </form>
                </Card>

                <Card title="Notas da organização">
                    {notes.length === 0 ? (
                        <Empty title="Nenhuma nota ainda">Escreva o primeiro procedimento (ex.: como pedir a camisa nova, quem chamar no dia do jogo).</Empty>
                    ) : (
                        <ul className="divide-y divide-gray-100" data-testid="notes">
                            {notes.map((note) => (
                                <li key={note.id} className="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                    <div className="min-w-0">
                                        <p className="font-medium text-gray-900">{note.title}</p>
                                        <div className="mt-1">
                                            <Badge tone="blue">{label(note.audience)}</Badge>
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        {note.audience === 'panel' && (
                                            <Link href={route('panel.help.show', `nota-${note.id.toLowerCase()}`)} className="text-sm text-emerald-700 hover:underline">
                                                Ver
                                            </Link>
                                        )}
                                        <Button type="button" small variant="secondary" onClick={() => edit(note)}>
                                            Editar
                                        </Button>
                                        <DeleteButton href={route('panel.help-notes.destroy', note.id)} message={`Excluir a nota "${note.title}"?`} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </PanelLayout>
    );
}
