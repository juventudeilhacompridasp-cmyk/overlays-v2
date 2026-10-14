import { dayColor, dayTitle, formatDuration, formatUptime, formatWhen, STATUS_TONE, ComponentStatus } from '@/lib/statusPage';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

interface DayPoint {
    date: string;
    uptime: number | null;
}

interface StatusComponent {
    key: string;
    name: string;
    status: ComponentStatus;
    status_label: string;
    uptime: number | null;
    latency_ms: number | null;
    days: DayPoint[];
}

interface Update {
    state: string;
    state_label: string;
    body: string;
    at: string;
}

interface Incident {
    id: string;
    title: string;
    impact: string;
    impact_label: string;
    state: string;
    state_label: string;
    auto: boolean;
    starts_at: string;
    ends_at: string | null;
    resolved_at: string | null;
    components: string[];
    updates: Update[];
}

interface Status {
    overall: ComponentStatus;
    overall_label: string;
    updated_at: string;
    components: StatusComponent[];
    incidents: Incident[];
    maintenance: Incident[];
}

/** Inscrição nos avisos por e-mail (dupla confirmação). A resposta é sempre a mesma, exista ou não o e-mail na lista. */
function SubscribeForm() {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', website: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('status.subscribe'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <section aria-label="Receber avisos" className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <h2 className="font-semibold">Receber avisos por e-mail</h2>
            <p className="mt-1 text-sm text-gray-600">Avisamos quando houver incidente, atualização ou manutenção. Você confirma o e-mail e pode cancelar a qualquer momento.</p>
            {flash?.success && (
                <p role="status" className="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
                    {flash.success}
                </p>
            )}
            <form onSubmit={submit} className="mt-3 flex flex-wrap gap-2" noValidate>
                <label className="min-w-0 flex-1 text-sm">
                    <span className="sr-only">Seu e-mail</span>
                    <input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} placeholder="voce@exemplo.com.br" maxLength={160} autoComplete="email" required className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600" />
                </label>
                {/* Armadilha para robôs: escondido de pessoas e de leitores de tela. */}
                <div className="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                    <label>
                        Não preencha
                        <input tabIndex={-1} autoComplete="off" value={data.website} onChange={(event) => setData('website', event.target.value)} />
                    </label>
                </div>
                <button type="submit" disabled={processing} className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">
                    {processing ? 'Enviando…' : 'Inscrever'}
                </button>
            </form>
            {errors.email && <p className="mt-2 text-sm text-red-600">{errors.email}</p>}
        </section>
    );
}

/** Página pública de status da plataforma: situação geral, componentes com 90 dias, incidentes e manutenções. Atualiza sozinha a cada 30 s. */
export default function StatusPage({ status: initial, json_url }: { status: Status; json_url: string }) {
    const [status, setStatus] = useState<Status>(initial);
    const [offline, setOffline] = useState(false);

    useEffect(() => {
        const refresh = async () => {
            if (document.hidden) return;

            try {
                const response = await fetch(json_url, { headers: { Accept: 'application/json' } });

                if (!response.ok) throw new Error(String(response.status));

                setStatus(await response.json());
                setOffline(false);
            } catch {
                setOffline(true);
            }
        };
        const timer = window.setInterval(refresh, 30000);

        return () => window.clearInterval(timer);
    }, [json_url]);

    const tone = STATUS_TONE[status.overall];
    const open = status.incidents.filter((incident) => incident.resolved_at === null);
    const past = status.incidents.filter((incident) => incident.resolved_at !== null);

    return (
        <div className="min-h-screen bg-gray-50 text-gray-900">
            <Head title="Status da plataforma">
                <meta name="description" content="Situação em tempo real do painel, do placar ao vivo e dos overlays." />
                <link rel="alternate" type="application/atom+xml" title="Status (Atom)" href="/status.atom" />
            </Head>
            <header className="border-b border-gray-200 bg-white">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-4">
                    <h1 className="text-lg font-extrabold">Status da plataforma</h1>
                    <nav className="flex items-center gap-3 text-sm text-gray-500">
                        <a href="/status.atom" className="hover:text-emerald-700">Feed</a>
                        <a href="/status.json" className="hover:text-emerald-700">JSON</a>
                    </nav>
                </div>
            </header>

            <main className="mx-auto max-w-3xl space-y-6 px-4 py-8">
                <section className={`rounded-lg px-5 py-4 text-white shadow-sm ${tone.banner}`} role="status" data-testid="status-overall">
                    <p className="text-xl font-bold">{status.overall_label}</p>
                    <p className="mt-0.5 text-sm text-white/80">
                        Atualizado às {formatWhen(status.updated_at)}
                        {offline && ' · sem conexão para atualizar, mostrando o último retrato'}
                    </p>
                </section>

                {status.maintenance.length > 0 && (
                    <section aria-label="Manutenções" className="space-y-3">
                        <h2 className="text-sm font-bold uppercase tracking-wide text-gray-500">Manutenções programadas</h2>
                        {status.maintenance.map((item) => (
                            <article key={item.id} id={item.id} className="rounded-lg border border-blue-200 bg-blue-50 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h3 className="font-semibold text-blue-900">{item.title}</h3>
                                    <span className="text-xs font-semibold text-blue-700">{item.state_label}</span>
                                </div>
                                <p className="mt-1 text-xs text-blue-800">
                                    {formatWhen(item.starts_at)}
                                    {item.ends_at && ` até ${formatWhen(item.ends_at)}`} · {item.components.join(', ')}
                                </p>
                                {item.updates[0] && <p className="mt-2 text-sm text-blue-900">{item.updates[0].body}</p>}
                            </article>
                        ))}
                    </section>
                )}

                <section aria-label="Componentes" className="divide-y divide-gray-100 rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    {status.components.map((component) => (
                        <div key={component.key} className="p-4" data-testid={`component-${component.key}`}>
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="font-semibold">{component.name}</h2>
                                <span className={`inline-flex items-center gap-1.5 text-sm font-semibold ${STATUS_TONE[component.status].text}`}>
                                    <span className={`h-2.5 w-2.5 rounded-full ${STATUS_TONE[component.status].dot}`} aria-hidden="true" />
                                    {component.status_label}
                                </span>
                            </div>
                            <div className="mt-3 flex h-8 items-stretch gap-[2px]" role="img" aria-label={`Disponibilidade dos últimos ${component.days.length} dias: ${formatUptime(component.uptime)}`}>
                                {component.days.map((day) => (
                                    <span key={day.date} title={dayTitle(day.date, day.uptime)} className={`min-w-0 flex-1 rounded-[2px] ${dayColor(day.uptime)}`} />
                                ))}
                            </div>
                            <div className="mt-1.5 flex justify-between text-xs text-gray-500">
                                <span>{component.days.length} dias atrás</span>
                                <span>
                                    {formatUptime(component.uptime)} no período{component.latency_ms !== null && ` · ${component.latency_ms} ms`}
                                </span>
                                <span>hoje</span>
                            </div>
                        </div>
                    ))}
                    {status.components.length === 0 && <p className="p-4 text-sm text-gray-500">Ainda sem medições.</p>}
                </section>

                <section aria-label="Incidentes" className="space-y-3">
                    <h2 className="text-sm font-bold uppercase tracking-wide text-gray-500">Incidentes</h2>
                    {open.length === 0 && past.length === 0 && <p className="rounded-lg bg-white p-4 text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">Nenhum incidente nos últimos 14 dias.</p>}
                    {[...open, ...past].map((incident) => (
                        <article key={incident.id} id={incident.id} className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200" data-testid="incident">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 className="font-semibold">{incident.title}</h3>
                                <span className={`text-xs font-semibold ${incident.resolved_at ? 'text-emerald-700' : 'text-amber-700'}`}>{incident.state_label}</span>
                            </div>
                            <p className="mt-0.5 text-xs text-gray-500">
                                {incident.impact_label} · {incident.components.join(', ')} · {formatWhen(incident.starts_at)} ({formatDuration(incident.starts_at, incident.resolved_at)})
                            </p>
                            <ol className="mt-3 space-y-2 border-l-2 border-gray-200 pl-3">
                                {incident.updates.map((update) => (
                                    <li key={update.at + update.state} className="text-sm">
                                        <p className="text-xs font-semibold text-gray-500">
                                            {update.state_label} · {formatWhen(update.at)}
                                        </p>
                                        <p className="text-gray-800">{update.body}</p>
                                    </li>
                                ))}
                            </ol>
                        </article>
                    ))}
                </section>

                <SubscribeForm />
            </main>
        </div>
    );
}
