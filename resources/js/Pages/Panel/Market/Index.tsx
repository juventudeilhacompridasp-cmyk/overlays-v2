import { Badge, Button, Card, Empty, Field, Input, PageHeader, Pagination } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { PaginationLink } from '@/types/panel';
import { Head, Link, router, useForm } from '@inertiajs/react';

interface Report {
    id: string;
    kind: 'manager' | 'athlete';
    reporter: string;
    about: string;
    profile_id: string;
    profile_status: string;
    reason: string;
    status: string;
    status_label: string;
    note: string | null;
    created_at: string;
}

interface ProfileRow {
    id: string;
    name: string;
    sport: string | null;
    city: string | null;
    status: string;
    status_label: string;
    suspended_reason: string | null;
    open_reports: number;
    published_at: string | null;
}

interface Props {
    reports: Report[];
    profiles: { data: ProfileRow[]; links: PaginationLink[] };
    status: string | null;
    statuses: { value: string; label: string }[];
    totals: { published: number; open_reports: number; pending_requests: number };
    settings: { daily_requests: number };
}

const tone = (status: string) => (status === 'published' ? 'green' : status === 'suspended' ? 'red' : status === 'signed' ? 'blue' : 'gray');

export default function Index({ reports, profiles, status, statuses, totals, settings }: Props) {
    const form = useForm({ daily_requests: settings.daily_requests.toString() });

    const suspend = (profile: { id: string; name: string }) => {
        const reason = window.prompt(`Suspender o perfil de ${profile.name}? Informe o motivo (o atleta verá):`);
        if (reason && reason.trim().length >= 5) router.post(route('panel.market.suspend', profile.id), { reason }, { preserveScroll: true });
    };

    const resolve = (report: Report, outcome: 'actioned' | 'dismissed') => {
        const note = window.prompt(outcome === 'actioned' ? 'Denúncia procedente. Observação (opcional):' : 'Arquivar a denúncia. Observação (opcional):') ?? undefined;
        router.patch(route('panel.market.reports.resolve', report.id), { outcome, note }, { preserveScroll: true });
    };

    return (
        <PanelLayout>
            <Head title="Mercado de jogadores" />
            <PageHeader title="Mercado de jogadores" subtitle="Moderação: denúncias, perfis e cota de pedidos. Você não vê o contato nem a data de nascimento dos atletas." />

            <div className="mb-6 grid gap-3 sm:grid-cols-3">
                <Card>
                    <p className="text-2xl font-semibold text-gray-900">{totals.published}</p>
                    <p className="text-sm text-gray-500">perfis publicados</p>
                </Card>
                <Card>
                    <p className="text-2xl font-semibold text-gray-900">{totals.open_reports}</p>
                    <p className="text-sm text-gray-500">denúncias abertas</p>
                </Card>
                <Card>
                    <p className="text-2xl font-semibold text-gray-900">{totals.pending_requests}</p>
                    <p className="text-sm text-gray-500">pedidos de contato aguardando resposta</p>
                </Card>
            </div>

            <div className="space-y-6">
                <Card title="Denúncias">
                    {reports.length === 0 ? (
                        <Empty title="Nenhuma denúncia">Quando um gestor ou atleta denunciar, ela aparece aqui.</Empty>
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {reports.map((report) => (
                                <li key={report.id} className="py-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-sm text-gray-900">
                                            <span className="font-medium">{report.reporter}</span> denunciou <span className="font-medium">{report.about}</span>{' '}
                                            <Badge tone={report.status === 'open' ? 'amber' : report.status === 'actioned' ? 'green' : 'gray'}>{report.status_label}</Badge>
                                            <span className="ml-2 text-xs text-gray-500">{report.created_at}</span>
                                        </span>
                                        {report.status === 'open' && (
                                            <span className="flex gap-2">
                                                <Button small onClick={() => resolve(report, 'actioned')}>
                                                    Procedente
                                                </Button>
                                                <Button small variant="secondary" onClick={() => resolve(report, 'dismissed')}>
                                                    Arquivar
                                                </Button>
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-1 whitespace-pre-line text-sm text-gray-600">“{report.reason}”</p>
                                    {report.note && <p className="mt-1 text-xs text-gray-500">Observação: {report.note}</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="Perfis">
                    <div className="mb-3 flex flex-wrap gap-2">
                        <Link href={route('panel.market.index')} className={`rounded-full px-3 py-1 text-sm ${status === null ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'}`}>
                            Todos
                        </Link>
                        {statuses.map((item) => (
                            <Link
                                key={item.value}
                                href={route('panel.market.index', { status: item.value })}
                                className={`rounded-full px-3 py-1 text-sm ${status === item.value ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'}`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </div>
                    {profiles.data.length === 0 ? (
                        <p className="text-sm text-gray-500">Nenhum perfil nesta situação.</p>
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {profiles.data.map((profile) => (
                                <li key={profile.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <span className="text-sm text-gray-900">
                                        <span className="font-medium">{profile.name}</span>{' '}
                                        <span className="text-gray-500">
                                            {[profile.sport, profile.city].filter(Boolean).join(' · ')}
                                        </span>{' '}
                                        <Badge tone={tone(profile.status)}>{profile.status_label}</Badge>
                                        {profile.open_reports > 0 && <Badge tone="amber">{profile.open_reports} denúncia(s)</Badge>}
                                        {profile.suspended_reason && <span className="ml-2 text-xs text-gray-500">Motivo: {profile.suspended_reason}</span>}
                                    </span>
                                    {profile.status === 'suspended' ? (
                                        <Button small variant="secondary" onClick={() => router.post(route('panel.market.restore', profile.id), {}, { preserveScroll: true })}>
                                            Reativar
                                        </Button>
                                    ) : (
                                        <Button small variant="danger" onClick={() => suspend(profile)}>
                                            Suspender
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                    <Pagination links={profiles.links} />
                </Card>

                <Card title="Cota de pedidos">
                    <form
                        className="flex flex-wrap items-end gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.patch(route('panel.market.settings'), { preserveScroll: true });
                        }}
                    >
                        <Field label="Pedidos de contato por equipe, por dia" error={form.errors.daily_requests} htmlFor="daily_requests" hint="Entre 1 e 100. Vale para todas as equipes da organização.">
                            <Input id="daily_requests" type="number" min={1} max={100} value={form.data.daily_requests} onChange={(event) => form.setData('daily_requests', event.target.value)} />
                        </Field>
                        <Button type="submit" disabled={form.processing}>
                            Salvar
                        </Button>
                    </form>
                </Card>
            </div>
        </PanelLayout>
    );
}
