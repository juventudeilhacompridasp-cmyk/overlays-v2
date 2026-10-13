import { Button, Card, DeleteButton, Field, ImageUpload, Input, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Team {
    id: string;
    name: string;
    short_name: string | null;
    city: string | null;
    primary_color: string | null;
    logo_url: string | null;
}

interface Manager {
    id: string;
    name: string;
    email: string;
}

export default function Form({ team, managers }: { team: Team | null; managers: Manager[] | null }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: team?.name ?? '',
        short_name: team?.short_name ?? '',
        city: team?.city ?? '',
        primary_color: team?.primary_color ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (team) {
            put(route('panel.teams.update', team.id));
        } else {
            post(route('panel.teams.store'));
        }
    };

    return (
        <PanelLayout>
            <Head title={team ? 'Editar equipe' : 'Nova equipe'} />
            <PageHeader
                title={team ? 'Editar equipe' : 'Nova equipe'}
                breadcrumbs={[{ label: 'Equipes', href: route('panel.teams.index') }, { label: team ? team.name : 'Nova' }]}
            />

            {team ? (
                <Card title="Escudo" className="mb-6 max-w-2xl">
                    <ImageUpload
                        label="Escudo da equipe"
                        field="logo"
                        name={team.name}
                        currentUrl={team.logo_url}
                        uploadUrl={route('panel.teams.logo.store', team.id)}
                        removeUrl={route('panel.teams.logo.destroy', team.id)}
                        hint="JPG, PNG ou WebP, até 3 MB. PNG com fundo transparente é o ideal para o placar e os overlays."
                    />
                </Card>
            ) : (
                <p className="mb-4 max-w-2xl text-sm text-gray-500">Salve a equipe para poder enviar o escudo.</p>
            )}

            {team && managers !== null && <ManagersCard team={team} managers={managers} />}

            <Card className="max-w-2xl">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Nome" htmlFor="name" error={errors.name}>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus required />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Sigla" htmlFor="short_name" error={errors.short_name} hint="Até 10 caracteres (placar e overlays).">
                            <Input id="short_name" value={data.short_name} onChange={(e) => setData('short_name', e.target.value)} />
                        </Field>
                        <Field label="Cidade" htmlFor="city" error={errors.city}>
                            <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                        </Field>
                    </div>
                    <Field label="Cor principal" htmlFor="primary_color" error={errors.primary_color}>
                        <div className="flex items-center gap-3">
                            <input
                                id="primary_color"
                                type="color"
                                value={data.primary_color || '#10b981'}
                                onChange={(e) => setData('primary_color', e.target.value)}
                                className="h-10 w-14 cursor-pointer rounded border border-gray-300"
                            />
                            <span className="text-sm text-gray-500">{data.primary_color || 'não definida'}</span>
                            {data.primary_color && (
                                <Button small variant="ghost" onClick={() => setData('primary_color', '')}>
                                    Limpar
                                </Button>
                            )}
                        </div>
                    </Field>
                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>
                            Salvar
                        </Button>
                        <LinkButton href={route('panel.teams.index')} variant="secondary">
                            Cancelar
                        </LinkButton>
                    </div>
                </form>
            </Card>
        </PanelLayout>
    );
}

function ManagersCard({ team, managers }: { team: Team; managers: Manager[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', email: '' });

    return (
        <Card title="Gestores da equipe" className="mb-6 max-w-2xl">
            <p className="mb-4 text-sm text-gray-600">
                O gestor entra com o próprio login na área do gestor e define a escalação e o esquema tático dos jogos desta equipe. Ele recebe um e-mail para criar a senha.
            </p>

            {managers.length > 0 && (
                <ul className="mb-4 divide-y divide-gray-100 rounded-md ring-1 ring-gray-200">
                    {managers.map((manager) => (
                        <li key={manager.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                            <div>
                                <div className="text-sm font-medium text-gray-900">{manager.name}</div>
                                <div className="text-xs text-gray-500">{manager.email}</div>
                            </div>
                            <div className="flex gap-2">
                                <Button small variant="secondary" onClick={() => router.post(route('panel.teams.managers.resend', manager.id))}>
                                    Reenviar convite
                                </Button>
                                <DeleteButton href={route('panel.teams.managers.destroy', manager.id)} message={`Remover ${manager.name} da gestão desta equipe?`} label="Remover" />
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(route('panel.teams.managers.store', team.id), { preserveScroll: true, onSuccess: () => reset() });
                }}
                className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
            >
                <Field label="Nome do gestor" htmlFor="manager_name" error={errors.name}>
                    <Input id="manager_name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                </Field>
                <Field label="E-mail" htmlFor="manager_email" error={errors.email}>
                    <Input id="manager_email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                </Field>
                <Button type="submit" disabled={processing}>
                    Convidar
                </Button>
            </form>
        </Card>
    );
}
