import { Button, Card, Field, Input, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Official {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    active: boolean;
}

export default function Form({ official }: { official: Official | null }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: official?.name ?? '',
        email: official?.email ?? '',
        phone: official?.phone ?? '',
        active: official?.active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (official) put(route('panel.officials.update', official.id));
        else post(route('panel.officials.store'));
    };

    return (
        <PanelLayout>
            <Head title={official ? 'Editar árbitro ou mesário' : 'Novo árbitro ou mesário'} />
            <PageHeader
                title={official ? 'Editar árbitro ou mesário' : 'Novo árbitro ou mesário'}
                subtitle="Só o necessário para a escala: nome e contato. Sem CPF nem documentos."
                breadcrumbs={[{ label: 'Árbitros e mesários', href: route('panel.officials.index') }, { label: official ? official.name : 'Novo' }]}
            />
            <Card className="max-w-2xl">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Nome" htmlFor="name" error={errors.name}>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus required />
                    </Field>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="E-mail" htmlFor="email" error={errors.email} hint="Recebe o aviso quando for escalado. Sem e-mail, nenhum aviso é enviado.">
                            <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        </Field>
                        <Field label="Telefone / WhatsApp" htmlFor="phone" error={errors.phone}>
                            <Input id="phone" type="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        </Field>
                    </div>
                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" className="rounded border-gray-300 text-emerald-600" checked={data.active} onChange={(e) => setData('active', e.target.checked)} />
                        Ativo (aparece para escalar nos jogos)
                    </label>
                    {errors.active && <p className="text-sm text-red-600">{errors.active}</p>}
                    <div className="flex flex-col gap-2 pt-2 sm:flex-row">
                        <Button type="submit" disabled={processing}>
                            Salvar
                        </Button>
                        <LinkButton href={route('panel.officials.index')} variant="secondary">
                            Cancelar
                        </LinkButton>
                    </div>
                </form>
            </Card>
        </PanelLayout>
    );
}
