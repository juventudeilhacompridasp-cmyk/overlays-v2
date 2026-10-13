import { Button, Card, Field, Input, PageHeader, Select } from '@/Components/Panel/ui';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    organization: {
        id: string;
        name: string;
        slug: string;
        plan: string;
        custom_features: boolean;
        features: string[];
        max_users: number | null;
        max_competitions: number | null;
    };
    usage: Record<string, number | string | null>;
    plans: { value: string; label: string; features: string[] }[];
    all_features: { value: string; label: string }[];
}

export default function Edit({ organization, usage, plans, all_features }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        plan: organization.plan,
        custom_features: organization.custom_features,
        features: organization.features,
        max_users: organization.max_users?.toString() ?? '',
        max_competitions: organization.max_competitions?.toString() ?? '',
    });

    const preset = plans.find((plan) => plan.value === data.plan)?.features ?? [];
    const effective = data.custom_features ? data.features : preset;

    const toggle = (feature: string) => {
        setData('features', data.features.includes(feature) ? data.features.filter((item) => item !== feature) : [...data.features, feature]);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('platform.organizations.update', organization.id), { preserveScroll: true });
    };

    return (
        <PlatformLayout>
            <Head title={`Plataforma · ${organization.name}`} />
            <PageHeader
                title={organization.name}
                subtitle={organization.slug}
                breadcrumbs={[{ label: 'Organizações', href: route('platform.organizations.index') }, { label: organization.name }]}
            />
            <div className="space-y-6">
                <form onSubmit={submit} className="space-y-6">
                    <Card title="Pacote e recursos">
                        <div className="space-y-4">
                            <Field label="Pacote" htmlFor="plan" error={errors.plan}>
                                <Select id="plan" value={data.plan} onChange={(e) => setData('plan', e.target.value)} className="sm:w-60">
                                    {plans.map((plan) => (
                                        <option key={plan.value} value={plan.value}>
                                            {plan.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    className="rounded border-gray-300 text-emerald-600"
                                    checked={data.custom_features}
                                    onChange={(e) => {
                                        setData((current) => ({ ...current, custom_features: e.target.checked, features: e.target.checked ? preset : current.features }));
                                    }}
                                />
                                Liberar recursos à mão (ignora a lista do pacote)
                            </label>
                            <ul className="grid gap-2 sm:grid-cols-2">
                                {all_features.map((feature) => (
                                    <li key={feature.value}>
                                        <label className="flex items-center gap-2 text-sm text-gray-800">
                                            <input
                                                type="checkbox"
                                                className="rounded border-gray-300 text-emerald-600"
                                                checked={effective.includes(feature.value)}
                                                disabled={!data.custom_features}
                                                onChange={() => toggle(feature.value)}
                                            />
                                            {feature.label}
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </Card>
                    <Card title="Limites">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Máximo de usuários" htmlFor="max-users" error={errors.max_users} hint="Em branco = sem limite.">
                                <Input id="max-users" type="number" min={1} value={data.max_users} onChange={(e) => setData('max_users', e.target.value)} />
                            </Field>
                            <Field label="Máximo de competições" htmlFor="max-competitions" error={errors.max_competitions} hint="Em branco = sem limite.">
                                <Input id="max-competitions" type="number" min={1} value={data.max_competitions} onChange={(e) => setData('max_competitions', e.target.value)} />
                            </Field>
                        </div>
                    </Card>
                    <Button type="submit" disabled={processing}>
                        Salvar
                    </Button>
                </form>

                <Card title="Uso">
                    <dl className="grid gap-3 text-sm sm:grid-cols-4">
                        {[
                            ['Usuários', usage.users],
                            ['Competições', usage.competitions],
                            ['Edições', usage.seasons],
                            ['Equipes', usage.teams],
                            ['Pessoas', usage.people],
                            ['Jogos', usage.games],
                            ['Jogos encerrados', usage.finished_games],
                            ['Inscrições', usage.registrations],
                        ].map(([label, value]) => (
                            <div key={label as string}>
                                <dt className="text-gray-500">{label}</dt>
                                <dd className="text-lg font-semibold text-gray-900">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </Card>
            </div>
        </PlatformLayout>
    );
}
