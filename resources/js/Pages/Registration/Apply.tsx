import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    theme: Theme;
    seo: Seo;
    competition: { name: string; slug: string };
    season: { id: string; name: string; status_label: string };
    open: boolean;
    deadline: string | null;
    instructions: string | null;
    requirements: { min_athletes: number; max_athletes: number; require_documents: boolean };
    divisions: { id: string; name: string }[];
}

const field = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';

function Row({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700">{label}</span>
            {children}
            {error && <span className="mt-1 block text-sm text-red-600">{error}</span>}
        </label>
    );
}

export default function Apply({ theme, seo, competition, season, open, deadline, instructions, requirements, divisions }: Props) {
    const { flash } = usePage().props as { flash?: { error?: string } };
    const { data, setData, post, processing, errors } = useForm({
        division_id: divisions.length === 1 ? divisions[0].id : '',
        team_name: '',
        team_short_name: '',
        team_city: '',
        contact_name: '',
        contact_email: '',
        contact_phone: '',
        consent: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('registration.store', [competition.slug, season.id]));
    };

    return (
        <PortalLayout theme={theme} seo={seo}>
            <h1 className="text-2xl font-bold">Inscrição de equipe</h1>
            <p className="mt-1 text-gray-600">
                {competition.name} · {season.name}
            </p>

            {!open ? (
                <div className="mt-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p className="font-medium">As inscrições não estão abertas no momento.</p>
                    {deadline && <p className="mt-1 text-sm text-gray-600">Prazo informado: {new Date(`${deadline}T00:00:00`).toLocaleDateString('pt-BR')}.</p>}
                    <Link href={route('portal.home')} className="mt-3 inline-block text-sm text-emerald-700 hover:underline">
                        Ver competições
                    </Link>
                </div>
            ) : (
                <form onSubmit={submit} className="mt-6 space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    {flash?.error && <div className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{flash.error}</div>}
                    {instructions && <p className="whitespace-pre-line rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{instructions}</p>}
                    <ul className="list-inside list-disc text-sm text-gray-600">
                        <li>
                            De {requirements.min_athletes} a {requirements.max_athletes} atletas por equipe, mais a comissão técnica.
                        </li>
                        {requirements.require_documents && <li>Cada atleta precisa de um documento com foto (PDF ou imagem, até 5 MB).</li>}
                        {deadline && <li>Prazo: {new Date(`${deadline}T00:00:00`).toLocaleDateString('pt-BR')}.</li>}
                        <li>Você receberá por e-mail um link pessoal para completar o cadastro depois.</li>
                    </ul>

                    <Row label="Divisão" error={errors.division_id}>
                        <select className={field} value={data.division_id} onChange={(e) => setData('division_id', e.target.value)} required>
                            <option value="">Escolha…</option>
                            {divisions.map((division) => (
                                <option key={division.id} value={division.id}>
                                    {division.name}
                                </option>
                            ))}
                        </select>
                    </Row>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <Row label="Nome da equipe" error={errors.team_name}>
                                <input className={field} value={data.team_name} onChange={(e) => setData('team_name', e.target.value)} required />
                            </Row>
                        </div>
                        <Row label="Sigla (3 letras)" error={errors.team_short_name}>
                            <input className={field} maxLength={5} value={data.team_short_name} onChange={(e) => setData('team_short_name', e.target.value.toUpperCase())} />
                        </Row>
                    </div>
                    <Row label="Cidade" error={errors.team_city}>
                        <input className={field} value={data.team_city} onChange={(e) => setData('team_city', e.target.value)} />
                    </Row>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Row label="Responsável" error={errors.contact_name}>
                            <input className={field} value={data.contact_name} onChange={(e) => setData('contact_name', e.target.value)} required />
                        </Row>
                        <Row label="E-mail" error={errors.contact_email}>
                            <input type="email" className={field} value={data.contact_email} onChange={(e) => setData('contact_email', e.target.value)} required />
                        </Row>
                        <Row label="Telefone" error={errors.contact_phone}>
                            <input className={field} value={data.contact_phone} onChange={(e) => setData('contact_phone', e.target.value)} />
                        </Row>
                    </div>
                    <label className="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-emerald-600" checked={data.consent} onChange={(e) => setData('consent', e.target.checked)} />
                        <span>
                            Concordo que os dados e documentos informados (inclusive de menores, com autorização do responsável) sejam usados pela organização apenas para
                            validar a inscrição e gerir a competição.
                        </span>
                    </label>
                    {errors.consent && <p className="text-sm text-red-600">{errors.consent}</p>}
                    <button type="submit" disabled={processing} className="rounded-md bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                        Começar inscrição
                    </button>
                </form>
            )}
        </PortalLayout>
    );
}
