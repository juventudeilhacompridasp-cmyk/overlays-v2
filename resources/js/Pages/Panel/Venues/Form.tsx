import { Button, Card, Field, Input, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Venue {
    id: string;
    name: string;
    city: string | null;
    address: string | null;
}

export default function Form({ venue }: { venue: Venue | null }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: venue?.name ?? '',
        city: venue?.city ?? '',
        address: venue?.address ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (venue) {
            put(route('panel.venues.update', venue.id));
        } else {
            post(route('panel.venues.store'));
        }
    };

    return (
        <PanelLayout>
            <Head title={venue ? 'Editar estádio' : 'Novo estádio'} />
            <PageHeader
                title={venue ? 'Editar estádio' : 'Novo estádio'}
                breadcrumbs={[{ label: 'Estádios', href: route('panel.venues.index') }, { label: venue ? venue.name : 'Novo' }]}
            />
            <Card className="max-w-2xl">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Nome" htmlFor="name" error={errors.name}>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus required />
                    </Field>
                    <Field label="Cidade" htmlFor="city" error={errors.city}>
                        <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                    </Field>
                    <Field label="Endereço" htmlFor="address" error={errors.address}>
                        <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    </Field>
                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>
                            Salvar
                        </Button>
                        <LinkButton href={route('panel.venues.index')} variant="secondary">
                            Cancelar
                        </LinkButton>
                    </div>
                </form>
            </Card>
        </PanelLayout>
    );
}
