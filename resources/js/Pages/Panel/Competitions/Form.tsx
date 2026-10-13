import { Button, Card, Field, Input, LinkButton, PageHeader, Select, Textarea } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Competition {
    id: string;
    name: string;
    description: string | null;
    sport: string;
}

export default function Form({ competition, sports, sport_locked }: { competition: Competition | null; sports: { value: string; label: string }[]; sport_locked: boolean }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: competition?.name ?? '',
        description: competition?.description ?? '',
        sport: competition?.sport ?? 'football',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (competition) {
            put(route('panel.competitions.update', competition.id));
        } else {
            post(route('panel.competitions.store'));
        }
    };

    return (
        <PanelLayout>
            <Head title={competition ? 'Editar competição' : 'Nova competição'} />
            <PageHeader
                title={competition ? 'Editar competição' : 'Nova competição'}
                subtitle="O esporte define o cronômetro, as faltas e o regulamento padrão das edições."
                breadcrumbs={[
                    { label: 'Competições', href: route('panel.competitions.index') },
                    { label: competition ? competition.name : 'Nova' },
                ]}
            />
            <Card className="max-w-2xl">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Nome" htmlFor="name" error={errors.name} hint="Ex.: Campeonato Municipal de Futebol Amador">
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus required />
                    </Field>
                    <Field label="Esporte" htmlFor="sport" error={errors.sport} hint={sport_locked ? 'Não dá para trocar: a competição já tem edições.' : 'Escolha antes de criar as edições.'}>
                        <Select id="sport" value={data.sport} onChange={(e) => setData('sport', e.target.value)} disabled={sport_locked}>
                            {sports.map((sport) => (
                                <option key={sport.value} value={sport.value}>
                                    {sport.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Descrição" htmlFor="description" error={errors.description}>
                        <Textarea id="description" rows={4} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </Field>
                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>
                            Salvar
                        </Button>
                        <LinkButton href={competition ? route('panel.competitions.show', competition.id) : route('panel.competitions.index')} variant="secondary">
                            Cancelar
                        </LinkButton>
                    </div>
                </form>
            </Card>
        </PanelLayout>
    );
}
