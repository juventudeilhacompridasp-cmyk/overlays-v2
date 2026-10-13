import { Button, Card, Field, Input, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    profile: { id: string; name: string; description: string | null; permissions: string[] } | null;
    catalog: { value: string; label: string; group: string }[];
}

export default function Form({ profile, catalog }: Props) {
    const { data, setData, post, patch, processing, errors } = useForm({ name: profile?.name ?? '', description: profile?.description ?? '', permissions: profile?.permissions ?? [] });
    const groups = Array.from(new Set(catalog.map((item) => item.group)));

    const toggle = (value: string) => setData('permissions', data.permissions.includes(value) ? data.permissions.filter((item) => item !== value) : [...data.permissions, value]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (profile) patch(route('panel.profiles.update', profile.id));
        else post(route('panel.profiles.store'));
    };

    return (
        <PanelLayout>
            <Head title={profile ? 'Editar perfil' : 'Novo perfil'} />
            <PageHeader
                title={profile ? 'Editar perfil' : 'Novo perfil'}
                breadcrumbs={[{ label: 'Perfis de acesso', href: route('panel.profiles.index') }, { label: profile ? 'Editar' : 'Novo' }]}
            />

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <Card>
                    <div className="space-y-4">
                        <Field label="Nome" htmlFor="name" error={errors.name}>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required maxLength={60} />
                        </Field>
                        <Field label="Descrição" htmlFor="description" error={errors.description} hint="Opcional. Ajuda a lembrar para quem é este perfil.">
                            <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} maxLength={200} />
                        </Field>
                    </div>
                </Card>

                <Card title="Permissões">
                    <p className="mb-3 text-sm text-gray-600">Qualquer usuário pode consultar o painel. Marque o que este perfil também pode alterar.</p>
                    <div className="space-y-4">
                        {groups.map((group) => (
                            <fieldset key={group}>
                                <legend className="text-sm font-medium text-gray-900">{group}</legend>
                                <div className="mt-1 space-y-1">
                                    {catalog
                                        .filter((item) => item.group === group)
                                        .map((item) => (
                                            <label key={item.value} className="flex items-start gap-2 text-sm text-gray-800">
                                                <input type="checkbox" className="mt-1" checked={data.permissions.includes(item.value)} onChange={() => toggle(item.value)} />
                                                {item.label}
                                            </label>
                                        ))}
                                </div>
                            </fieldset>
                        ))}
                    </div>
                    <p className="mt-4 text-xs text-gray-500">Recursos fora do pacote da organização continuam bloqueados mesmo com a permissão marcada.</p>
                </Card>

                <Button type="submit" disabled={processing}>
                    {profile ? 'Salvar' : 'Criar perfil'}
                </Button>
            </form>
        </PanelLayout>
    );
}
