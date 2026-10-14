import { Badge, Button, Card, DeleteButton, Field, Input, PageHeader, Select, Textarea } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { formatWhen, STATUS_TONE, ComponentStatus } from '@/lib/statusPage';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Option {
    value: string;
    label: string;
}

interface IncidentRow {
    id: string;
    title: string;
    impact: string;
    impact_label: string;
    state: string;
    state_label: string;
    auto: boolean;
    maintenance: boolean;
    closed: boolean;
    starts_at: string;
    ends_at: string | null;
    components: string[];
    updates: { state_label: string; body: string; at: string }[];
}

interface Props {
    summary: { overall: ComponentStatus; overall_label: string; components: { key: string; name: string; status: ComponentStatus; status_label: string; latency_ms: number | null }[] };
    components: { id: string; name: string }[];
    incidents: IncidentRow[];
    impacts: Option[];
    states: { incident: Option[]; maintenance: Option[] };
    public_url: string;
    badge_url: string;
    subscribers: { confirmed: number; pending: number };
}

function UpdateForm({ incident, states }: { incident: IncidentRow; states: Option[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ state: incident.state, body: '' });

    return (
        <form
            className="mt-3 grid gap-2 sm:grid-cols-[12rem_1fr_auto]"
            onSubmit={(event: FormEvent) => {
                event.preventDefault();
                post(route('platform.status.incidents.update', incident.id), { preserveScroll: true, onSuccess: () => reset('body') });
            }}
        >
            <Select aria-label="Nova situação" value={data.state} onChange={(event) => setData('state', event.target.value)}>
                {states.map((state) => <option key={state.value} value={state.value}>{state.label}</option>)}
            </Select>
            <div>
                <Input aria-label="Mensagem da atualização" value={data.body} onChange={(event) => setData('body', event.target.value)} placeholder="O que mudou? (texto público)" maxLength={2000} />
                {errors.body && <p className="mt-1 text-sm text-red-600">{errors.body}</p>}
            </div>
            <Button type="submit" variant="secondary" disabled={processing || data.body.trim() === ''}>Publicar</Button>
        </form>
    );
}

export default function StatusAdmin({ summary, components, incidents, impacts, states, public_url, badge_url, subscribers }: Props) {
    const [maintenance, setMaintenance] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ title: '', impact: 'minor', state: 'investigating', components: [] as string[], body: '', starts_at: '', ends_at: '' });
    const stateOptions = maintenance ? states.maintenance : states.incident;
    const toggle = (id: string) => setData('components', data.components.includes(id) ? data.components.filter((item) => item !== id) : [...data.components, id]);
    const switchKind = (value: boolean) => {
        setMaintenance(value);
        setData((previous) => ({ ...previous, impact: value ? 'maintenance' : 'minor', state: value ? 'scheduled' : 'investigating' }));
    };

    return (
        <PanelLayout>
            <Head title="Status page" />
            <PageHeader title="Status page" subtitle={<>Página pública: <a className="text-emerald-700 hover:underline" href={public_url} target="_blank" rel="noreferrer">{public_url}</a>. As sondagens rodam a cada minuto e abrem incidentes sozinhas; aqui você avisa o público e agenda manutenções.</>} />

            <Card title="Situação agora" className="mb-6">
                <p className={`mb-3 font-semibold ${STATUS_TONE[summary.overall].text}`}>{summary.overall_label}</p>
                <ul className="grid gap-2 sm:grid-cols-2">
                    {summary.components.map((component) => (
                        <li key={component.key} className="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 text-sm">
                            <span>{component.name}</span>
                            <span className={`inline-flex items-center gap-1.5 font-semibold ${STATUS_TONE[component.status].text}`}>
                                <span className={`h-2 w-2 rounded-full ${STATUS_TONE[component.status].dot}`} aria-hidden="true" />
                                {component.status_label}{component.latency_ms !== null && <span className="font-normal text-gray-400"> · {component.latency_ms} ms</span>}
                            </span>
                        </li>
                    ))}
                </ul>
            </Card>

            <Card title="Avisos por e-mail e selo" className="mb-6">
                <p className="text-sm text-gray-700" data-testid="subscribers">
                    <strong>{subscribers.confirmed}</strong> inscritos confirmados ({subscribers.pending} aguardando confirmação). Cada comunicado, atualização e incidente automático é enviado a eles; os e-mails são pedidos, confirmados e cancelados por eles mesmos na página pública.
                </p>
                <p className="mt-3 text-sm font-medium text-gray-700">Selo de status para outros sites (atualiza sozinho):</p>
                <img src={badge_url} alt="Selo de status" className="mt-1 h-5" />
                <pre className="mt-2 overflow-x-auto rounded-md bg-gray-100 p-3 text-xs text-gray-800">{`<a href="${public_url}"><img src="${badge_url}" alt="Status"></a>`}</pre>
            </Card>

            <Card title={maintenance ? 'Agendar manutenção' : 'Novo comunicado'} className="mb-6" actions={<Button variant="secondary" small onClick={() => switchKind(!maintenance)}>{maintenance ? 'Mudar para incidente' : 'Mudar para manutenção'}</Button>}>
                <form
                    className="grid gap-4"
                    onSubmit={(event: FormEvent) => {
                        event.preventDefault();
                        post(route('platform.status.incidents.store'), { preserveScroll: true, onSuccess: () => { reset(); switchKind(false); } });
                    }}
                >
                    <Field label="Título" error={errors.title}>
                        <Input value={data.title} onChange={(event) => setData('title', event.target.value)} maxLength={120} placeholder={maintenance ? 'Atualização do servidor de tempo real' : 'Lentidão no painel'} />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {!maintenance && (
                            <Field label="Impacto" error={errors.impact}>
                                <Select value={data.impact} onChange={(event) => setData('impact', event.target.value)}>
                                    {impacts.filter((impact) => impact.value !== 'maintenance').map((impact) => <option key={impact.value} value={impact.value}>{impact.label}</option>)}
                                </Select>
                            </Field>
                        )}
                        <Field label="Situação" error={errors.state}>
                            <Select value={data.state} onChange={(event) => setData('state', event.target.value)}>
                                {stateOptions.map((state) => <option key={state.value} value={state.value}>{state.label}</option>)}
                            </Select>
                        </Field>
                        {maintenance && (
                            <>
                                <Field label="Início" error={errors.starts_at}><Input type="datetime-local" value={data.starts_at} onChange={(event) => setData('starts_at', event.target.value)} /></Field>
                                <Field label="Fim previsto" error={errors.ends_at}><Input type="datetime-local" value={data.ends_at} onChange={(event) => setData('ends_at', event.target.value)} /></Field>
                            </>
                        )}
                    </div>
                    <Field label="Componentes afetados" error={errors.components}>
                        <div className="flex flex-wrap gap-3">
                            {components.map((component) => (
                                <label key={component.id} className="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" checked={data.components.includes(component.id)} onChange={() => toggle(component.id)} />
                                    {component.name}
                                </label>
                            ))}
                        </div>
                    </Field>
                    <Field label="Mensagem pública" error={errors.body}>
                        <Textarea rows={3} value={data.body} onChange={(event) => setData('body', event.target.value)} maxLength={2000} />
                    </Field>
                    <div><Button type="submit" disabled={processing}>Publicar na status page</Button></div>
                </form>
            </Card>

            <Card title="Incidentes e manutenções">
                {incidents.length === 0 && <p className="text-sm text-gray-500">Nada publicado ainda.</p>}
                <ul className="space-y-4">
                    {incidents.map((incident) => (
                        <li key={incident.id} className="rounded-md border border-gray-200 p-4" data-testid="admin-incident">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="font-semibold text-gray-900">{incident.title}</h3>
                                    <Badge tone={incident.closed ? 'green' : incident.maintenance ? 'blue' : 'amber'}>{incident.state_label}</Badge>
                                    <Badge>{incident.impact_label}</Badge>
                                    {incident.auto && <Badge tone="gray">automático</Badge>}
                                </div>
                                <DeleteButton href={route('platform.status.incidents.destroy', incident.id)} message="Remover este comunicado da status page?" />
                            </div>
                            <p className="mt-1 text-xs text-gray-500">{incident.components.join(', ')} · {formatWhen(incident.starts_at)}{incident.ends_at && ` até ${formatWhen(incident.ends_at)}`}</p>
                            <ol className="mt-2 space-y-1 text-sm text-gray-700">
                                {incident.updates.map((update) => <li key={update.at + update.body}><span className="text-xs font-semibold text-gray-500">{update.state_label} · {formatWhen(update.at)}:</span> {update.body}</li>)}
                            </ol>
                            {!incident.closed && <UpdateForm incident={incident} states={incident.maintenance ? states.maintenance : states.incident} />}
                        </li>
                    ))}
                </ul>
            </Card>
        </PanelLayout>
    );
}
