import { Button, Card, Field, Input, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function Edit({ theme, portal_url }: { theme: { name: string; primary: string; tagline: string | null }; portal_url: string }) {
    const { data, setData, patch, processing, errors } = useForm({ name: theme.name, primary: theme.primary, tagline: theme.tagline ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('panel.organization.update'), { preserveScroll: true });
    };

    return (
        <PanelLayout>
            <Head title="Portal público" />
            <PageHeader
                title="Portal público"
                subtitle={
                    <>
                        Identidade do portal aberto ao público em{' '}
                        <a href={portal_url} target="_blank" rel="noreferrer" className="text-emerald-700 underline">
                            {portal_url}
                        </a>
                        . Só edições publicadas (fora de rascunho) aparecem lá.
                    </>
                }
            />
            <Card className="max-w-2xl">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Nome da organização" htmlFor="name" error={errors.name}>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Frase de apresentação" htmlFor="tagline" error={errors.tagline} hint="Opcional. Aparece abaixo do nome no topo do portal.">
                        <Input id="tagline" value={data.tagline} onChange={(e) => setData('tagline', e.target.value)} />
                    </Field>
                    <Field label="Cor principal" htmlFor="primary" error={errors.primary}>
                        <div className="flex items-center gap-3">
                            <input id="primary" type="color" value={data.primary} onChange={(e) => setData('primary', e.target.value)} className="h-10 w-14 cursor-pointer rounded border border-gray-300" />
                            <span className="text-sm text-gray-500">{data.primary}</span>
                        </div>
                    </Field>
                    <Button type="submit" disabled={processing}>
                        Salvar
                    </Button>
                </form>
            </Card>
        </PanelLayout>
    );
}
