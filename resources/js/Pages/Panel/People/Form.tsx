import { maskCpf } from '@/Components/Panel/cpf';
import { Badge, Button, Card, DeleteButton, Field, ImageUpload, Input, LinkButton, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Person {
    id: string;
    name: string;
    nickname: string | null;
    birth_date: string | null;
    has_document: boolean;
    photo_url: string | null;
}

interface PersonDocument {
    id: string;
    type_label: string;
    original_name: string;
    expires_on: string;
    days_left: number;
    status: 'valid' | 'expiring' | 'expired';
    status_label: string;
}

interface DocumentType {
    value: string;
    label: string;
}

const tone = { valid: 'green', expiring: 'amber', expired: 'red' } as const;
const brDate = (iso: string) => iso.split('-').reverse().join('/');

function Documents({ person, documents, types }: { person: Person; documents: PersonDocument[]; types: DocumentType[] }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ type: string; expires_on: string; file: File | null }>({ type: types[0]?.value ?? '', expires_on: '', file: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('panel.people.documents.store', person.id), { forceFormData: true, preserveScroll: true, onSuccess: () => reset('file', 'expires_on') });
    };

    return (
        <Card title="Documentos com validade" className="mb-6 max-w-2xl">
            <p className="mb-3 text-sm text-gray-600">Atestado médico, seguro e outros documentos. O arquivo fica em armazenamento privado (LGPD) e só abre por link temporário. O gestor da equipe é avisado antes de vencer e, se a edição exigir, atleta sem atestado válido não pode ser escalado.</p>
            {documents.length === 0 ? (
                <p className="mb-4 rounded-md border border-dashed border-gray-300 px-3 py-4 text-center text-sm text-gray-500">Nenhum documento cadastrado.</p>
            ) : (
                <ul className="mb-4 divide-y divide-gray-100 rounded-md ring-1 ring-gray-200" data-testid="person-documents">
                    {documents.map((document) => (
                        <li key={document.id} className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium text-gray-900">{document.type_label}</span>
                                    <Badge tone={tone[document.status]}>{document.status_label}</Badge>
                                </div>
                                <div className="truncate text-sm text-gray-500">
                                    {document.original_name} · validade {brDate(document.expires_on)}
                                    {document.days_left >= 0 ? ` (${document.days_left === 0 ? 'vence hoje' : `faltam ${document.days_left} dia(s)`})` : ` (venceu há ${-document.days_left} dia(s))`}
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <a href={route('panel.people.documents.show', document.id)} className="inline-flex min-h-11 items-center justify-center rounded-md px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50 sm:min-h-0">
                                    Abrir
                                </a>
                                <DeleteButton href={route('panel.people.documents.destroy', document.id)} message="Excluir este documento? O arquivo é apagado." />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            <form onSubmit={submit} className="space-y-3" aria-label="Novo documento">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Field label="Tipo" htmlFor="doc-type" error={errors.type}>
                        <Select id="doc-type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                            {types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Validade" htmlFor="doc-expires" error={errors.expires_on}>
                        <Input id="doc-expires" type="date" value={data.expires_on} onChange={(e) => setData('expires_on', e.target.value)} required />
                    </Field>
                </div>
                <Field label="Arquivo (PDF ou imagem, até 5 MB)" htmlFor="doc-file" error={errors.file}>
                    <Input id="doc-file" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} required />
                </Field>
                <Button type="submit" disabled={processing}>
                    Guardar documento
                </Button>
            </form>
        </Card>
    );
}

export default function Form({ person, documents, document_types }: { person: Person | null; documents: PersonDocument[]; document_types: DocumentType[] }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: person?.name ?? '',
        nickname: person?.nickname ?? '',
        birth_date: person?.birth_date ?? '',
        document: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (person) {
            put(route('panel.people.update', person.id));
        } else {
            post(route('panel.people.store'));
        }
    };

    return (
        <PanelLayout>
            <Head title={person ? 'Editar pessoa' : 'Nova pessoa'} />
            <PageHeader
                title={person ? 'Editar pessoa' : 'Nova pessoa'}
                breadcrumbs={[{ label: 'Atletas e comissão', href: route('panel.people.index') }, { label: person ? person.name : 'Nova' }]}
            />
            {person ? (
                <Card title="Foto" className="mb-6 max-w-2xl">
                    <ImageUpload
                        label="Foto"
                        field="photo"
                        name={person.name}
                        round
                        currentUrl={person.photo_url}
                        uploadUrl={route('panel.people.photo.store', person.id)}
                        removeUrl={route('panel.people.photo.destroy', person.id)}
                        hint="JPG, PNG ou WebP, até 5 MB; vira um quadrado centralizado. Por LGPD a foto fica em armazenamento privado e só aparece aqui no painel, nunca no portal público."
                    />
                </Card>
            ) : (
                <p className="mb-4 max-w-2xl text-sm text-gray-500">Salve o cadastro para poder enviar a foto e os documentos.</p>
            )}
            {person && <Documents person={person} documents={documents} types={document_types} />}

            <Card className="max-w-2xl">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Nome completo" htmlFor="name" error={errors.name}>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus required />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Apelido" htmlFor="nickname" error={errors.nickname} hint="Como aparece na súmula e nos overlays.">
                            <Input id="nickname" value={data.nickname} onChange={(e) => setData('nickname', e.target.value)} />
                        </Field>
                        <Field label="Data de nascimento" htmlFor="birth_date" error={errors.birth_date}>
                            <Input id="birth_date" type="date" value={data.birth_date} onChange={(e) => setData('birth_date', e.target.value)} />
                        </Field>
                    </div>
                    <Field
                        label="CPF"
                        htmlFor="document"
                        error={errors.document}
                        hint={
                            person?.has_document
                                ? 'Já há um CPF cadastrado (por segurança ele não é exibido). Preencha só para substituí-lo.'
                                : 'Opcional. Por segurança (LGPD) o CPF não é guardado: só um código de verificação, usado para evitar cadastro duplicado.'
                        }
                    >
                        <Input
                            id="document"
                            inputMode="numeric"
                            autoComplete="off"
                            placeholder="000.000.000-00"
                            value={data.document}
                            onChange={(e) => setData('document', maskCpf(e.target.value))}
                        />
                    </Field>
                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>
                            Salvar
                        </Button>
                        <LinkButton href={route('panel.people.index')} variant="secondary">
                            Cancelar
                        </LinkButton>
                    </div>
                </form>
            </Card>
        </PanelLayout>
    );
}
