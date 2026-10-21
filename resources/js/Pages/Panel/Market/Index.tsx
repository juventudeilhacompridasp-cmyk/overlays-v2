import DataTable, { serverTable } from '@/Components/DataTable';
import { Badge, Button, Card, Empty, Field, Input, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Paginated, TableFilters } from '@/types/panel';
import { Head, router, useForm } from '@inertiajs/react';

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
    profiles: Paginated<ProfileRow>;
    filters: TableFilters;
    statuses: { value: string; label: string }[];
    totals: { published: number; open_reports: number; pending_requests: number };
    settings: { daily_requests: number };
}

const tone = (status: string) => (status === 'published' ? 'green' : status === 'suspended' ? 'red' : status === 'signed' ? 'blue' : 'gray');

export default function Index({ reports, profiles, filters, statuses, totals, settings }: Props) {
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
                    <DataTable
                        flush
                        caption="Denúncias do Mercado"
                        searchPlaceholder="Buscar denúncia"
                        rows={reports}
                        rowKey={(report) => report.id}
                        empty={<Empty title="Nenhuma denúncia">Quando um gestor ou atleta denunciar, ela aparece aqui.</Empty>}
                        columns={[
                            {
                                key: 'report',
                                header: 'Denúncia',
                                primary: true,
                                value: (report) => `${report.reporter} ${report.about} ${report.reason}`,
                                cell: (report) => (
                                    <div>
                                        <span className="text-sm text-gray-900">
                                            <span className="font-medium">{report.reporter}</span> denunciou <span className="font-medium">{report.about}</span>
                                        </span>
                                        <p className="mt-1 whitespace-pre-line text-sm text-gray-600">“{report.reason}”</p>
                                        {report.note && <p className="mt-1 text-xs text-gray-500">Observação: {report.note}</p>}
                                    </div>
                                ),
                            },
                            {
                                key: 'status',
                                header: 'Situação',
                                filter: true,
                                value: (report) => report.status_label,
                                cell: (report) => <Badge tone={report.status === 'open' ? 'amber' : report.status === 'actioned' ? 'green' : 'gray'}>{report.status_label}</Badge>,
                            },
                            { key: 'when', header: 'Data', value: (report) => report.created_at.split('/').reverse().join('-'), cell: (report) => <span className="text-xs text-gray-500">{report.created_at}</span> },
                            {
                                key: 'actions',
                                header: 'Ações',
                                align: 'right',
                                mobileLabel: false,
                                cell: (report) =>
                                    report.status === 'open' ? (
                                        <span className="flex flex-wrap justify-end gap-2">
                                            <Button small onClick={() => resolve(report, 'actioned')}>
                                                Procedente
                                            </Button>
                                            <Button small variant="secondary" onClick={() => resolve(report, 'dismissed')}>
                                                Arquivar
                                            </Button>
                                        </span>
                                    ) : null,
                            },
                        ]}
                    />
                </Card>

                <Card title="Perfis">
                    <DataTable
                        flush
                        caption="Perfis do Mercado"
                        searchPlaceholder="Buscar por nome, cidade ou esporte"
                        rows={profiles.data}
                        rowKey={(profile) => profile.id}
                        server={serverTable(route('panel.market.index'), profiles, filters, { sort: 'created', dir: 'desc' }, ['profiles', 'filters'])}
                        empty="Nenhum perfil nesta situação."
                        columns={[
                            { key: 'name', header: 'Atleta', primary: true, value: (profile) => profile.name, cell: (profile) => <span className="font-medium text-gray-900">{profile.name}</span> },
                            { key: 'sport', header: 'Esporte', hideBelow: 'lg', value: (profile) => profile.sport, cell: (profile) => <span className="text-gray-600">{profile.sport ?? '—'}</span> },
                            { key: 'city', header: 'Cidade', hideBelow: 'lg', value: (profile) => profile.city, cell: (profile) => <span className="text-gray-600">{profile.city ?? '—'}</span> },
                            {
                                key: 'status',
                                header: 'Situação',
                                filter: statuses,
                                value: (profile) => profile.status_label,
                                cell: (profile) => (
                                    <span>
                                        <Badge tone={tone(profile.status)}>{profile.status_label}</Badge>
                                        {profile.suspended_reason && <span className="ml-2 text-xs text-gray-500">Motivo: {profile.suspended_reason}</span>}
                                    </span>
                                ),
                            },
                            {
                                key: 'reports',
                                header: 'Denúncias abertas',
                                value: (profile) => profile.open_reports,
                                cell: (profile) => (profile.open_reports > 0 ? <Badge tone="amber">{profile.open_reports}</Badge> : <span className="text-gray-400">0</span>),
                            },
                            { key: 'created', header: 'Criado', hideBelow: 'xl', value: (profile) => profile.published_at, cell: (profile) => <span className="text-xs text-gray-500">{profile.published_at ?? '—'}</span> },
                            {
                                key: 'actions',
                                header: 'Ações',
                                align: 'right',
                                mobileLabel: false,
                                cell: (profile) =>
                                    profile.status === 'suspended' ? (
                                        <Button small variant="secondary" onClick={() => router.post(route('panel.market.restore', profile.id), {}, { preserveScroll: true })}>
                                            Reativar
                                        </Button>
                                    ) : (
                                        <Button small variant="danger" onClick={() => suspend(profile)}>
                                            Suspender
                                        </Button>
                                    ),
                            },
                        ]}
                    />
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
