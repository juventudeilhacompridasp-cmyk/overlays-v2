import { maskCpf } from '@/Components/Panel/cpf';
import { Button, Card, Field, Input, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Person {
    id: string;
    name: string;
    nickname: string | null;
    birth_date: string | null;
    has_document: boolean;
}

export default function Form({ person }: { person: Person | null }) {
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
