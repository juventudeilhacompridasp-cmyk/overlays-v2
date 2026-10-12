import { Button, Card, Field, ImageUpload, Input, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Team {
    id: string;
    name: string;
    short_name: string | null;
    city: string | null;
    primary_color: string | null;
    logo_url: string | null;
}

export default function Form({ team }: { team: Team | null }) {
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
