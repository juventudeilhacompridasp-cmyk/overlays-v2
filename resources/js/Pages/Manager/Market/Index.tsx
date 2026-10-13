import { Badge, Button, Card, Empty, Field, Input, Logo, PageHeader, Pagination, Select, Textarea } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { PaginationLink } from '@/types/panel';
import { Head, router, useForm } from '@inertiajs/react';
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
    teams_available: { id: string; name: string }[];
    requests: RequestRow[];
}

interface RequestRow {
    id: string;
    team_id: string;
    team: string;
    status: string;
    status_label: string;
    expires_at: string;
    contact: { channel: string; value: string; link: string } | null;
    name?: string;
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
    daily_limit: number;
    my_requests: RequestRow[];
    options: { sports: (Option & { positions: Option[] })[]; days: Option[]; periods: Option[]; levels: Option[] };
}

const statusTone = (status: string) => (status === 'accepted' ? 'green' : status === 'pending' ? 'amber' : 'gray');

function ContactBox({ row }: { row: RequestRow }) {
    if (!row.contact) return null;

    return (
        <p className="mt-1 text-sm text-gray-800">
            {row.contact.channel}:{' '}
            <a href={row.contact.link} target="_blank" rel="noreferrer" className="font-medium text-emerald-700 underline">
                {row.contact.value}
            </a>
        </p>
    );
}

function RequestForm({ profile }: { profile: ProfileCard }) {
    const { data, setData, post, processing, errors, reset } = useForm({ team_id: profile.teams_available[0]?.id ?? '', message: '' });

    if (profile.teams_available.length === 0) return null;

    return (
        <form
            className="mt-3 space-y-2 border-t border-gray-100 pt-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(route('manager.market.requests.store', profile.id), { preserveScroll: true, onSuccess: () => reset('message') });
            }}
        >
            {profile.teams_available.length > 1 && (
                <Select aria-label="Equipe" value={data.team_id} onChange={(event) => setData('team_id', event.target.value)}>
                    {profile.teams_available.map((team) => (
                        <option key={team.id} value={team.id}>
                            {team.name}
                        </option>
                    ))}
                </Select>
            )}
            <Textarea
                aria-label="Mensagem"
                rows={2}
                maxLength={300}
                placeholder={`Apresente a ${profile.teams_available.find((team) => team.id === data.team_id)?.name ?? 'equipe'} em poucas palavras`}
                value={data.message}
                onChange={(event) => setData('message', event.target.value)}
            />
            {(errors.message || errors.team_id) && <p className="text-sm text-red-600">{errors.message ?? errors.team_id}</p>}
            <Button type="submit" small disabled={processing || data.message.trim().length < 5}>
                Pedir contato
            </Button>
        </form>
    );
}

export default function Index({ profiles, filters, daily_limit, my_requests, options }: Props) {
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

            {my_requests.length > 0 && (
                <Card title="Meus pedidos" className="mb-6">
                    <ul className="divide-y divide-gray-100">
                        {my_requests.map((row) => (
                            <li key={row.id} className="py-2">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="text-sm text-gray-900">
                                        <span className="font-medium">{row.name}</span> · {row.team}{' '}
                                        <Badge tone={statusTone(row.status)}>{row.status_label}</Badge>
                                    </span>
                                    {(row.status === 'pending' || row.status === 'accepted') && (
                                        <Button
                                            small
                                            variant="ghost"
                                            onClick={() => {
                                                if (window.confirm('Cancelar este pedido?')) router.delete(route('manager.market.requests.destroy', row.id), { preserveScroll: true });
                                            }}
                                        >
                                            Cancelar
                                        </Button>
                                    )}
                                </div>
                                <ContactBox row={row} />
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

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
                    <p className="mb-3 text-sm text-gray-500">
                        {profiles.total} jogador(es) · cada equipe pode enviar até {daily_limit} pedidos por dia
                    </p>
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
                                {profile.requests.map((row) => (
                                    <div key={row.id} className="mt-3 border-t border-gray-100 pt-3 text-sm">
                                        <span className="text-gray-700">{row.team}</span> <Badge tone={statusTone(row.status)}>{row.status_label}</Badge>
                                        <ContactBox row={row} />
                                    </div>
                                ))}
                                <RequestForm profile={profile} />
                            </Card>
                        ))}
                    </div>
                    <Pagination links={profiles.links} />
                </>
            )}
        </PanelLayout>
    );
}
