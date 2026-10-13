import { Button, Card, Field, Input, PageHeader, Select } from '@/Components/Panel/ui';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

const slugify = (value: string) =>
    value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 40);

export default function Create({ plans, base_domain }: { plans: { value: string; label: string }[]; base_domain: string | null }) {
    const { data, setData, post, processing, errors } = useForm({ name: '', slug: '', plan: 'basic', admin_name: '', admin_email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('platform.organizations.store'));
    };

    return (
        <PlatformLayout>
            <Head title="Plataforma · nova organização" />
            <PageHeader
                title="Nova organização"
                breadcrumbs={[{ label: 'Organizações', href: route('platform.organizations.index') }, { label: 'Nova' }]}
                subtitle="Cria a organização no pacote escolhido e o primeiro administrador. Se o e-mail já tem usuário, ele só ganha o acesso."
            />
            <Card className="max-w-2xl">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Nome" htmlFor="name" error={errors.name}>
                        <Input id="name" value={data.name} onChange={(e) => setData((current) => ({ ...current, name: e.target.value, slug: current.slug === slugify(current.name) ? slugify(e.target.value) : current.slug }))} required autoFocus />
                    </Field>
                    <Field label="Endereço (slug)" htmlFor="slug" error={errors.slug} hint={base_domain ? `Abre em ${data.slug || 'slug'}.${base_domain}` : 'Sem domínio base configurado: o subdomínio fica desligado.'}>
                        <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value.toLowerCase())} required />
                    </Field>
                    <Field label="Pacote" htmlFor="plan" error={errors.plan}>
                        <Select id="plan" value={data.plan} onChange={(e) => setData('plan', e.target.value)} className="sm:w-60">
                            {plans.map((plan) => (
                                <option key={plan.value} value={plan.value}>
                                    {plan.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Administrador" htmlFor="admin_name" error={errors.admin_name}>
                            <Input id="admin_name" value={data.admin_name} onChange={(e) => setData('admin_name', e.target.value)} required />
                        </Field>
                        <Field label="E-mail do administrador" htmlFor="admin_email" error={errors.admin_email}>
                            <Input id="admin_email" type="email" value={data.admin_email} onChange={(e) => setData('admin_email', e.target.value)} required />
                        </Field>
                    </div>
                    <Button type="submit" disabled={processing}>
                        Criar organização
                    </Button>
                </form>
            </Card>
        </PlatformLayout>
    );
}
