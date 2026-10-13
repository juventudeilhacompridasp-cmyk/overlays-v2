import { Badge, Button, Card, Field, Input, PageHeader, Textarea } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Doc {
    type: string;
    title: string;
    body: string;
    version: number | null;
    published_at: string | null;
    is_default: boolean;
    default_body: string;
    public_url: string;
    accepted: number | null;
    members: number;
    history: { version: number; published_at: string; accepted: number }[];
}

function DocumentCard({ doc }: { doc: Doc }) {
    const [body, setBody] = useState(doc.body);
    const [processing, setProcessing] = useState(false);

    return (
        <Card title={doc.title} actions={<a href={doc.public_url} target="_blank" rel="noreferrer" className="text-sm text-emerald-700 hover:underline">Ver página pública</a>}>
            <p className="mb-3 text-sm text-gray-600">
                {doc.is_default ? (
                    <>
                        <Badge tone="amber">Texto-modelo</Badge> Ainda não há versão publicada. Revise o texto (de preferência com assessoria jurídica) e publique: a partir daí os usuários do painel precisam aceitar.
                    </>
                ) : (
                    <>
                        <Badge tone="green">Versão {doc.version}</Badge> publicada em {doc.published_at} · aceita por {doc.accepted} de {doc.members} usuário(s). Editar publica uma nova versão e pede novo aceite.
                    </>
                )}
            </p>
            <Textarea rows={14} value={body} onChange={(e) => setBody(e.target.value)} className="font-mono text-xs" />
            <p className="mt-1 text-xs text-gray-500">Use “# Título” para subtítulos e “- item” para listas. Linhas em branco separam parágrafos.</p>
            <div className="mt-3 flex flex-wrap gap-2">
                <Button
                    disabled={processing}
                    onClick={() => {
                        if (!window.confirm(`Publicar ${doc.title}? Todos os usuários do painel precisarão aceitar a nova versão.`)) return;
                        router.post(route('panel.legal.publish'), { type: doc.type, body }, { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });
                    }}
                >
                    Publicar nova versão
                </Button>
                <Button variant="secondary" onClick={() => setBody(doc.default_body)}>
                    Restaurar texto-modelo
                </Button>
            </div>
            {doc.history.length > 0 && (
                <ul className="mt-4 space-y-1 text-sm text-gray-600">
                    {doc.history.map((item) => (
                        <li key={item.version}>
                            Versão {item.version} · {item.published_at} · {item.accepted} aceite(s)
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

export default function Edit({ documents, settings }: { documents: Doc[]; settings: { controller_name: string | null; contact_email: string | null } }) {
    const { data, setData, patch, processing, errors } = useForm({ controller_name: settings.controller_name ?? '', contact_email: settings.contact_email ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('panel.legal.settings'), { preserveScroll: true });
    };

    return (
        <PanelLayout>
            <Head title="Termos e privacidade" />
            <PageHeader title="Termos e privacidade" subtitle="Textos públicos (/termos e /privacidade), versionados, com aceite registrado dos usuários do painel." />
            <div className="space-y-6">
                <Card title="Quem responde pelos dados (LGPD)">
                    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                        <Field label="Razão social / responsável" htmlFor="controller" error={errors.controller_name} hint="Aparece nos textos-modelo.">
                            <Input id="controller" value={data.controller_name} onChange={(e) => setData('controller_name', e.target.value)} />
                        </Field>
                        <Field label="E-mail para pedidos de titulares" htmlFor="contact" error={errors.contact_email} hint="Também aparece no rodapé da política pública.">
                            <Input id="contact" type="email" value={data.contact_email} onChange={(e) => setData('contact_email', e.target.value)} />
                        </Field>
                        <div className="sm:col-span-2">
                            <Button type="submit" disabled={processing}>
                                Salvar
                            </Button>
                        </div>
                    </form>
                </Card>
                {documents.map((doc) => (
                    <DocumentCard key={`${doc.type}-${doc.version ?? 0}`} doc={doc} />
                ))}
            </div>
        </PanelLayout>
    );
}
