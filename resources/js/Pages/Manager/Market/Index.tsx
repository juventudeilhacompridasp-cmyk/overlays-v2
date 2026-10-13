import { Badge, Card, Empty, Field, Input, Logo, PageHeader, Pagination, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { PaginationLink } from '@/types/panel';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Option = { value: string; label: string };

interface ProfileCard {
    id: string;
    name: string;
    photo_url: string | null;
    sport: string | null;
    positions: string[];
    age_range: string;
    dominant_foot: string | null;
    height_cm: number | null;
    level: string | null;
    bio: string | null;
    city: string | null;
    neighborhood: string | null;
    availability: { day: string; periods: string[] }[];
    published_at: string | null;
}

interface Filters {
    sport: string | null;
    position: string | null;
    city: string | null;
    neighborhood: string | null;
    day: string | null;
    period: string | null;
    level: string | null;
    age_min: number | null;
    age_max: number | null;
}

interface Props {
    profiles: { data: ProfileCard[]; links: PaginationLink[]; total: number };
    filters: Filters;
    options: { sports: (Option & { positions: Option[] })[]; days: Option[]; periods: Option[]; levels: Option[] };
}

export default function Index({ profiles, filters, options }: Props) {
    const [form, setForm] = useState({
        sport: filters.sport ?? '',
        position: filters.position ?? '',
        city: filters.city ?? '',
        neighborhood: filters.neighborhood ?? '',
        day: filters.day ?? '',
        period: filters.period ?? '',
        level: filters.level ?? '',
        age_min: filters.age_min?.toString() ?? '',
        age_max: filters.age_max?.toString() ?? '',
    });
    const set = (key: keyof typeof form, value: string) => setForm((previous) => ({ ...previous, [key]: value }));
    const positions = options.sports.find((sport) => sport.value === form.sport)?.positions ?? [];

    const search = (event?: FormEvent) => {
        event?.preventDefault();
        const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value !== ''));
        router.get(route('manager.market.index'), query, { preserveState: true, preserveScroll: true });
    };

    const clear = () => {
        setForm({ sport: '', position: '', city: '', neighborhood: '', day: '', period: '', level: '', age_min: '', age_max: '' });
        router.get(route('manager.market.index'));
    };

    const choose = (items: Option[], placeholder: string) => (
        <>
            <option value="">{placeholder}</option>
            {items.map((item) => (
                <option key={item.value} value={item.value}>
                    {item.label}
                </option>
            ))}
        </>
    );

    return (
        <PanelLayout>
            <Head title="Mercado de jogadores" />
            <PageHeader title="Mercado de jogadores" subtitle="Jogadores livres da organização que querem entrar numa equipe. O contato só é revelado depois que o atleta aceitar um pedido." />

            <form onSubmit={search} className="mb-6 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Esporte" htmlFor="sport">
                        <Select
                            id="sport"
                            value={form.sport}
                            onChange={(event) => setForm((previous) => ({ ...previous, sport: event.target.value, position: '' }))}
                        >
                            {choose(options.sports, 'Todos')}
                        </Select>
                    </Field>
                    <Field label="Posição" htmlFor="position">
                        <Select id="position" value={form.position} onChange={(event) => set('position', event.target.value)} disabled={positions.length === 0}>
                            {choose(positions, positions.length === 0 ? 'Escolha o esporte' : 'Todas')}
                        </Select>
                    </Field>
                    <Field label="Cidade" htmlFor="city">
                        <Input id="city" value={form.city} onChange={(event) => set('city', event.target.value)} />
                    </Field>
                    <Field label="Bairro" htmlFor="neighborhood">
                        <Input id="neighborhood" value={form.neighborhood} onChange={(event) => set('neighborhood', event.target.value)} />
                    </Field>
                    <Field label="Dia disponível" htmlFor="day">
                        <Select id="day" value={form.day} onChange={(event) => set('day', event.target.value)}>
                            {choose(options.days, 'Qualquer')}
                        </Select>
                    </Field>
                    <Field label="Período" htmlFor="period">
                        <Select id="period" value={form.period} onChange={(event) => set('period', event.target.value)}>
                            {choose(options.periods, 'Qualquer')}
                        </Select>
                    </Field>
                    <Field label="Nível" htmlFor="level">
                        <Select id="level" value={form.level} onChange={(event) => set('level', event.target.value)}>
                            {choose(options.levels, 'Qualquer')}
                        </Select>
                    </Field>
                    <div className="grid grid-cols-2 gap-2">
                        <Field label="Idade de" htmlFor="age_min">
                            <Input id="age_min" type="number" min={18} max={80} value={form.age_min} onChange={(event) => set('age_min', event.target.value)} />
                        </Field>
                        <Field label="até" htmlFor="age_max">
                            <Input id="age_max" type="number" min={18} max={80} value={form.age_max} onChange={(event) => set('age_max', event.target.value)} />
                        </Field>
                    </div>
                </div>
                <div className="mt-4 flex gap-2">
                    <button type="submit" className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        Buscar
                    </button>
                    <button type="button" onClick={clear} className="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                        Limpar
                    </button>
                </div>
            </form>

            {profiles.data.length === 0 ? (
                <Empty title="Nenhum jogador encontrado">Ajuste os filtros ou volte mais tarde: novos perfis aparecem quando os atletas publicam.</Empty>
            ) : (
                <>
                    <p className="mb-3 text-sm text-gray-500">{profiles.total} jogador(es)</p>
                    <div className="grid gap-4 md:grid-cols-2">
                        {profiles.data.map((profile) => (
                            <Card key={profile.id}>
                                <div className="flex gap-4">
                                    <Logo src={profile.photo_url} name={profile.name} size={64} round />
                                    <div className="min-w-0 flex-1">
                                        <h3 className="truncate text-base font-semibold text-gray-900">{profile.name}</h3>
                                        <p className="text-sm text-gray-600">
                                            {profile.sport}
                                            {profile.positions.length > 0 && ` · ${profile.positions.join(', ')}`}
                                        </p>
                                        <div className="mt-1 flex flex-wrap gap-1.5">
                                            <Badge>{profile.age_range}</Badge>
                                            {profile.level && <Badge tone="blue">{profile.level}</Badge>}
                                            {profile.dominant_foot && <Badge>Pé {profile.dominant_foot.toLowerCase()}</Badge>}
                                            {profile.height_cm && <Badge>{profile.height_cm} cm</Badge>}
                                        </div>
                                    </div>
                                </div>
                                {(profile.city || profile.neighborhood) && (
                                    <p className="mt-3 text-sm text-gray-700">{[profile.neighborhood, profile.city].filter(Boolean).join(', ')}</p>
                                )}
                                {profile.bio && <p className="mt-2 whitespace-pre-line text-sm text-gray-600">{profile.bio}</p>}
                                {profile.availability.length > 0 && (
                                    <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
                                        {profile.availability.map((row) => (
                                            <li key={row.day}>
                                                <span className="font-medium text-gray-700">{row.day}:</span> {row.periods.join(', ').toLowerCase()}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Card>
                        ))}
                    </div>
                    <Pagination links={profiles.links} />
                </>
            )}
        </PanelLayout>
    );
}
