import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { maskCpf } from '@/Components/Panel/cpf';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';

interface Doc {
    id: string;
    type_label: string;
    name: string;
    size_kb: number;
}

interface Person {
    id: string;
    role: string;
    role_label: string;
    name: string;
    nickname: string | null;
    birth_date: string | null;
    has_document: boolean;
    shirt_number: number | null;
    minor: boolean;
    guardian_name: string | null;
    review_status: string;
    review_status_label: string;
    review_note: string | null;
    documents: Doc[];
}

interface Props {
    theme: Theme;
    seo: Seo;
    registration: {
        supplement: boolean;
        back_url: string | null;
        status: string;
        status_label: string;
        editable: boolean;
        review_note: string | null;
        team_name: string;
        team_short_name: string | null;
        team_city: string | null;
        contact_name: string;
        contact_email: string;
        contact_phone: string | null;
        competition: string;
        season: string;
        division: string;
    };
    people: Person[];
    problems: string[];
    requirements: { min_athletes: number; max_athletes: number; require_documents: boolean; instructions: string | null; deadline: string | null };
    options: { roles: { value: string; label: string }[]; document_types: { value: string; label: string }[] };
}

const field = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
const btn = 'rounded-md px-3 py-1.5 text-sm font-medium disabled:opacity-50';

function Row({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700">{label}</span>
            {children}
            {error && <span className="mt-1 block text-sm text-red-600">{error}</span>}
        </label>
    );
}

const tone: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-700',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
};

function PersonForm({ token, roles, person, onDone, minAthletes }: { token: string; roles: Props['options']['roles']; person?: Person; onDone: () => void; minAthletes?: number }) {
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        role: person?.role ?? 'athlete',
        name: person?.name ?? '',
        nickname: person?.nickname ?? '',
        birth_date: person?.birth_date ?? '',
        document: '',
        shirt_number: person?.shirt_number?.toString() ?? '',
        guardian_name: person?.guardian_name ?? '',
        guardian_consent: !!person?.guardian_name,
    });
    const athlete = data.role === 'athlete';
    const isMinor = athlete && data.birth_date !== '' && new Date(data.birth_date) > new Date(new Date().setFullYear(new Date().getFullYear() - 18));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onDone();
            },
        };
        if (person) {
            patch(route('registration.people.update', [token, person.id]), options);
        } else {
            post(route('registration.people.store', token), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-3 rounded-md bg-gray-50 p-4 ring-1 ring-gray-200">
            <div className="grid gap-3 sm:grid-cols-3">
                <Row label="Função" error={errors.role}>
                    <select className={field} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </select>
                </Row>
                <Row label="Nome completo" error={errors.name}>
                    <input className={field} value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                </Row>
                <Row label="Apelido (opcional)" error={errors.nickname}>
                    <input className={field} value={data.nickname} onChange={(e) => setData('nickname', e.target.value)} />
                </Row>
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
                <Row label={athlete ? 'Nascimento' : 'Nascimento (opcional)'} error={errors.birth_date}>
                    <input type="date" className={field} value={data.birth_date} onChange={(e) => setData('birth_date', e.target.value)} required={athlete} />
                </Row>
                <Row label="CPF (opcional)" error={errors.document}>
                    <input className={field} inputMode="numeric" placeholder={person?.has_document ? 'já informado' : '000.000.000-00'} value={data.document} onChange={(e) => setData('document', maskCpf(e.target.value))} />
                </Row>
                {athlete && (
                    <Row label="Camisa" error={errors.shirt_number}>
                        <input type="number" min={0} max={999} className={field} value={data.shirt_number} onChange={(e) => setData('shirt_number', e.target.value)} />
                    </Row>
                )}
            </div>
            {isMinor && (
                <div className="space-y-2 rounded bg-amber-50 p-3 ring-1 ring-amber-200">
                    <p className="text-sm text-amber-900">Atleta menor de 18 anos: informe o responsável e envie a autorização assinada como documento.</p>
                    <Row label="Nome do responsável" error={errors.guardian_name}>
                        <input className={field} value={data.guardian_name} onChange={(e) => setData('guardian_name', e.target.value)} />
                    </Row>
                    <label className="flex items-start gap-2 text-sm text-amber-900">
                        <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-emerald-600" checked={data.guardian_consent} onChange={(e) => setData('guardian_consent', e.target.checked)} />
                        O responsável autorizou a participação e o uso dos dados do atleta.
                    </label>
                    {errors.guardian_consent && <p className="text-sm text-red-600">{errors.guardian_consent}</p>}
                </div>
            )}
            <div className="flex gap-2">
                <button type="submit" disabled={processing} className={`${btn} bg-emerald-600 text-white hover:bg-emerald-700`}>
                    {person ? 'Salvar' : 'Adicionar'}
                </button>
                <button type="button" onClick={onDone} className={`${btn} bg-white text-gray-700 ring-1 ring-gray-300`}>
                    Cancelar
                </button>
            </div>
            {minAthletes === undefined && null}
        </form>
    );
}

function Documents({ token, person, types, editable }: { token: string; person: Person; types: Props['options']['document_types']; editable: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ type: string; file: File | null }>({ type: 'id_document', file: null });

    const upload = (event: FormEvent) => {
        event.preventDefault();
        post(route('registration.documents.store', [token, person.id]), { forceFormData: true, preserveScroll: true, onSuccess: () => reset('file') });
    };

    return (
        <div className="mt-2 space-y-1 text-sm">
            {person.documents.map((doc) => (
                <div key={doc.id} className="flex items-center justify-between gap-2 text-gray-700">
                    <span>
                        {doc.type_label}: {doc.name} ({doc.size_kb} KB)
                    </span>
                    {editable && (
                        <button type="button" className="text-red-600 hover:underline" onClick={() => window.confirm('Remover este documento?') && router.delete(route('registration.documents.destroy', [token, person.id, doc.id]), { preserveScroll: true })}>
                            remover
                        </button>
                    )}
                </div>
            ))}
            {editable && (
                <form onSubmit={upload} className="flex flex-wrap items-center gap-2 pt-1">
                    <select className="rounded-md border-gray-300 text-sm" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        {types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" key={processing ? 'a' : 'b'} onChange={(e) => setData('file', e.target.files?.[0] ?? null)} className="text-sm" />
                    <button type="submit" disabled={processing || !data.file} className={`${btn} bg-white text-gray-700 ring-1 ring-gray-300`}>
                        Enviar documento
                    </button>
                    {(errors.file || errors.type) && <span className="text-sm text-red-600">{errors.file ?? errors.type}</span>}
                </form>
            )}
        </div>
    );
}

export default function Manage({ theme, seo, registration, people, problems, requirements, options }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };
    const token = window.location.pathname.split('/').filter(Boolean).pop() ?? '';
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<string | null>(null);
    const team = useForm({
        team_name: registration.team_name,
        team_short_name: registration.team_short_name ?? '',
        team_city: registration.team_city ?? '',
        contact_name: registration.contact_name,
        contact_phone: registration.contact_phone ?? '',
    });
    const athletes = people.filter((p) => p.role === 'athlete').length;

    const saveTeam = (event: FormEvent) => {
        event.preventDefault();
        team.patch(route('registration.team', token), { preserveScroll: true });
    };

    return (
        <PortalLayout theme={theme} seo={seo}>
            <Head>
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            {registration.back_url && (
                <p className="mb-3 text-sm">
                    <a href={registration.back_url} className="text-emerald-700 hover:underline">
                        ← Voltar à área do gestor
                    </a>
                </p>
            )}
            <h1 className="text-2xl font-bold">{registration.supplement ? `Reforços · ${registration.team_name}` : registration.team_name}</h1>
            <p className="mt-1 text-gray-600">
                {registration.competition} · {registration.season} · {registration.division}
            </p>
            <p className="mt-2">
                <span className="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-800">{registration.status_label}</span>
            </p>

            {flash?.success && <div className="mt-4 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.success}</div>}
            {flash?.error && <div className="mt-4 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{flash.error}</div>}
            {registration.review_note && (
                <div className="mt-4 rounded bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    <strong>Recado da organização:</strong> {registration.review_note}
                </div>
            )}
            {registration.status === 'submitted' && <p className="mt-4 rounded bg-blue-50 px-3 py-2 text-sm text-blue-900">Inscrição enviada. A organização vai analisar e avisar por e-mail.</p>}
            {registration.status === 'approved' && (
                <p className="mt-4 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                    {registration.supplement ? 'Reforços aprovados! As pessoas aprovadas já estão no elenco.' : 'Inscrição aprovada! Sua equipe já está na competição.'}
                </p>
            )}
            {registration.supplement && (
                <p className="mt-4 rounded bg-blue-50 px-3 py-2 text-sm text-blue-900">
                    Pedido de reforço: cadastre só as pessoas que vão ENTRAR no elenco (dados e documentos). Quem já está no elenco não precisa ser cadastrado de novo.
                </p>
            )}

            {!registration.supplement && (
            <section className="mt-6 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h2 className="mb-3 font-semibold">Dados da equipe</h2>
                <form onSubmit={saveTeam} className="grid gap-3 sm:grid-cols-2">
                    <Row label="Nome" error={team.errors.team_name}>
                        <input className={field} disabled={!registration.editable} value={team.data.team_name} onChange={(e) => team.setData('team_name', e.target.value)} />
                    </Row>
                    <Row label="Sigla" error={team.errors.team_short_name}>
                        <input className={field} disabled={!registration.editable} maxLength={5} value={team.data.team_short_name} onChange={(e) => team.setData('team_short_name', e.target.value.toUpperCase())} />
                    </Row>
                    <Row label="Cidade" error={team.errors.team_city}>
                        <input className={field} disabled={!registration.editable} value={team.data.team_city} onChange={(e) => team.setData('team_city', e.target.value)} />
                    </Row>
                    <Row label="Responsável" error={team.errors.contact_name}>
                        <input className={field} disabled={!registration.editable} value={team.data.contact_name} onChange={(e) => team.setData('contact_name', e.target.value)} />
                    </Row>
                    <Row label="Telefone" error={team.errors.contact_phone}>
                        <input className={field} disabled={!registration.editable} value={team.data.contact_phone} onChange={(e) => team.setData('contact_phone', e.target.value)} />
                    </Row>
                    {registration.editable && (
                        <div className="flex items-end">
                            <button type="submit" disabled={team.processing} className={`${btn} bg-emerald-600 text-white hover:bg-emerald-700`}>
                                Salvar dados
                            </button>
                        </div>
                    )}
                </form>
            </section>
            )}

            <section className="mt-6 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <div className="mb-3 flex items-center justify-between gap-3">
                    <h2 className="font-semibold">
                        {registration.supplement
                            ? `Pessoas a incluir (${athletes} atleta${athletes === 1 ? '' : 's'}; máximo de ${requirements.max_athletes} no elenco)`
                            : `Atletas e comissão (${athletes} de ${requirements.min_athletes} a ${requirements.max_athletes} atletas)`}
                    </h2>
                    {registration.editable && !adding && (
                        <button type="button" onClick={() => setAdding(true)} className={`${btn} bg-emerald-600 text-white hover:bg-emerald-700`}>
                            Adicionar pessoa
                        </button>
                    )}
                </div>
                {adding && <div className="mb-4"><PersonForm token={token} roles={options.roles} onDone={() => setAdding(false)} /></div>}
                {people.length === 0 && !adding && <p className="text-sm text-gray-500">Ninguém cadastrado ainda.</p>}
                <ul className="divide-y divide-gray-100">
                    {people.map((person) => (
                        <li key={person.id} className="py-3">
                            {editing === person.id ? (
                                <PersonForm token={token} roles={options.roles} person={person} onDone={() => setEditing(null)} />
                            ) : (
                                <>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <span className="font-medium">
                                                {person.shirt_number !== null && <span className="mr-2 text-gray-500">#{person.shirt_number}</span>}
                                                {person.name}
                                            </span>
                                            <span className="ml-2 text-sm text-gray-500">
                                                {person.role_label}
                                                {person.birth_date && ` · ${new Date(`${person.birth_date}T00:00:00`).toLocaleDateString('pt-BR')}`}
                                                {person.minor && ' · menor'}
                                            </span>
                                            {registration.status !== 'draft' && <span className={`ml-2 rounded-full px-2 py-0.5 text-xs ${tone[person.review_status]}`}>{person.review_status_label}</span>}
                                        </div>
                                        {registration.editable && (
                                            <div className="flex gap-3 text-sm">
                                                <button type="button" className="text-emerald-700 hover:underline" onClick={() => setEditing(person.id)}>
                                                    editar
                                                </button>
                                                <button type="button" className="text-red-600 hover:underline" onClick={() => window.confirm(`Remover ${person.name}?`) && router.delete(route('registration.people.destroy', [token, person.id]), { preserveScroll: true })}>
                                                    remover
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                    {person.review_note && <p className="mt-1 text-sm text-red-700">Motivo: {person.review_note}</p>}
                                    <Documents token={token} person={person} types={options.document_types} editable={registration.editable} />
                                </>
                            )}
                        </li>
                    ))}
                </ul>
            </section>

            {registration.editable && (
                <section className="mt-6 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-2 font-semibold">Enviar para análise</h2>
                    {problems.length > 0 ? (
                        <ul className="list-inside list-disc text-sm text-amber-800">
                            {problems.map((problem) => (
                                <li key={problem}>{problem}</li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-gray-600">Tudo certo. Depois de enviar, você só poderá editar se a organização devolver o pedido.</p>
                    )}
                    <button
                        type="button"
                        disabled={problems.length > 0}
                        onClick={() => window.confirm(registration.supplement ? 'Enviar o pedido de reforço para análise?' : 'Enviar a inscrição para análise?') && router.post(route('registration.submit', token))}
                        className={`${btn} mt-3 bg-emerald-600 text-white hover:bg-emerald-700`}
                    >
                        {registration.supplement ? 'Enviar pedido de reforço' : 'Enviar inscrição'}
                    </button>
                </section>
            )}
        </PortalLayout>
    );
}
