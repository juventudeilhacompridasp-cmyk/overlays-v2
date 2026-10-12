import { Badge, Button, Card, DeleteButton, Field, PageHeader, Select, Textarea } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Person {
    id: string;
    role_label: string;
    is_athlete: boolean;
    name: string;
    nickname: string | null;
    birth_date: string | null;
    age: number | null;
    minor: boolean;
    has_cpf: boolean;
    shirt_number: number | null;
    guardian_name: string | null;
    review_status: string;
    review_note: string | null;
    documents: { id: string; type_label: string; name: string; size_kb: number; url: string }[];
}

interface Props {
    registration: {
        id: string;
        status: string;
        status_label: string;
        reviewable: boolean;
        review_note: string | null;
        team_name: string;
        team_short_name: string | null;
        team_city: string | null;
        contact_name: string;
        contact_email: string;
        contact_phone: string | null;
        consent_at: string | null;
        submitted_at: string | null;
        competition: string;
        season: string;
        season_id: string;
        division: string;
        entry_id: string | null;
    };
    people: Person[];
    requirements: { min_athletes: number; require_documents: boolean };
    teams: { id: string; name: string }[];
    suggested_team_id: string | null;
}

const tone = (status: string) => (status === 'approved' ? 'green' : status === 'rejected' ? 'red' : 'gray');
const label: Record<string, string> = { pending: 'Pendente', approved: 'Aprovado', rejected: 'Recusado' };

function PersonRow({ registrationId, person, reviewable }: { registrationId: string; person: Person; reviewable: boolean }) {
    const [note, setNote] = useState(person.review_note ?? '');
    const url = route('panel.registrations.people.review', [registrationId, person.id]);
    const decide = (review_status: string) => router.patch(url, { review_status, review_note: review_status === 'rejected' ? note : '' }, { preserveScroll: true });

    return (
        <li className="py-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="font-medium text-gray-900">
                        {person.shirt_number !== null && <span className="mr-2 text-gray-500">#{person.shirt_number}</span>}
                        {person.name} <Badge tone={tone(person.review_status)}>{label[person.review_status]}</Badge>
                    </div>
                    <div className="text-sm text-gray-500">
                        {person.role_label}
                        {person.nickname && ` · “${person.nickname}”`}
                        {person.birth_date && ` · ${person.birth_date} (${person.age} anos)`}
                        {person.has_cpf && ' · CPF informado'}
                        {person.minor && ` · menor, responsável: ${person.guardian_name ?? '—'}`}
                    </div>
                    <div className="mt-1 text-sm">
                        {person.documents.length === 0 ? (
                            <span className="text-amber-700">Sem documentos</span>
                        ) : (
                            person.documents.map((doc) => (
                                <a key={doc.id} href={doc.url} target="_blank" rel="noreferrer" className="mr-3 text-emerald-700 hover:underline">
                                    {doc.type_label} ({doc.name})
                                </a>
                            ))
                        )}
                    </div>
                    {person.review_note && <p className="mt-1 text-sm text-red-700">Motivo: {person.review_note}</p>}
                </div>
                {reviewable && (
                    <div className="flex flex-col items-end gap-2">
                        <div className="flex gap-2">
                            <Button small onClick={() => decide('approved')}>Aprovar</Button>
                            <Button small variant="danger" onClick={() => decide('rejected')}>Recusar</Button>
                            <Button small variant="ghost" onClick={() => decide('pending')}>Pendente</Button>
                        </div>
                        <input className="w-64 rounded-md border-gray-300 text-xs" placeholder="Motivo (obrigatório para recusar)" value={note} onChange={(e) => setNote(e.target.value)} />
                    </div>
                )}
            </div>
        </li>
    );
}

export default function Show({ registration, people, requirements, teams, suggested_team_id }: Props) {
    const [note, setNote] = useState('');
    const [teamId, setTeamId] = useState(suggested_team_id ?? '');
    const post = (name: string, data: Record<string, string> = {}) => router.post(route(name, registration.id), data, { preserveScroll: true });

    return (
        <PanelLayout>
            <Head title={`Inscrição · ${registration.team_name}`} />
            <PageHeader
                title={registration.team_name}
                subtitle={
                    <>
                        <Badge tone={registration.status === 'submitted' ? 'amber' : registration.status === 'approved' ? 'green' : registration.status === 'rejected' ? 'red' : 'gray'}>{registration.status_label}</Badge>{' '}
                        <span className="ml-2">
                            {registration.competition} · {registration.season} · {registration.division}
                        </span>
                    </>
                }
                breadcrumbs={[{ label: 'Inscrições', href: route('panel.registrations.index') }, { label: registration.team_name }]}
                actions={registration.status !== 'approved' && <DeleteButton href={route('panel.registrations.destroy', registration.id)} message="Excluir este pedido e seus documentos?" small={false} />}
            />

            <div className="space-y-6">
                <Card title="Contato e equipe">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt className="text-gray-500">Sigla / cidade</dt><dd>{registration.team_short_name ?? '—'} · {registration.team_city ?? '—'}</dd></div>
                        <div><dt className="text-gray-500">Responsável</dt><dd>{registration.contact_name}</dd></div>
                        <div><dt className="text-gray-500">E-mail</dt><dd>{registration.contact_email}</dd></div>
                        <div><dt className="text-gray-500">Telefone</dt><dd>{registration.contact_phone ?? '—'}</dd></div>
                        <div><dt className="text-gray-500">Aceite de privacidade</dt><dd>{registration.consent_at ?? '—'}</dd></div>
                        <div><dt className="text-gray-500">Enviado em</dt><dd>{registration.submitted_at ?? 'ainda não enviado'}</dd></div>
                    </dl>
                    {registration.review_note && <p className="mt-3 text-sm text-gray-700">Última observação: {registration.review_note}</p>}
                    {registration.status === 'approved' && (
                        <p className="mt-3 text-sm text-emerald-800">Aprovada: equipe e elenco já estão na divisão.</p>
                    )}
                </Card>

                <Card title={`Atletas e comissão (mínimo de ${requirements.min_athletes} atletas)`}>
                    <ul className="-my-3 divide-y divide-gray-100">
                        {people.map((person) => (
                            <PersonRow key={person.id} registrationId={registration.id} person={person} reviewable={registration.reviewable} />
                        ))}
                    </ul>
                </Card>

                {registration.reviewable && (
                    <Card title="Decisão">
                        <div className="space-y-4">
                            <Field label="Vincular a uma equipe já cadastrada (opcional)" htmlFor="team" hint="Deixe em branco para criar uma equipe nova com os dados do pedido.">
                                <Select id="team" value={teamId} onChange={(e) => setTeamId(e.target.value)}>
                                    <option value="">Criar equipe nova</option>
                                    {teams.map((team) => (
                                        <option key={team.id} value={team.id}>{team.name}</option>
                                    ))}
                                </Select>
                            </Field>
                            <Button onClick={() => window.confirm('Aprovar a inscrição? A equipe e o elenco serão criados.') && post('panel.registrations.approve', teamId ? { team_id: teamId } : {})}>
                                Aprovar inscrição
                            </Button>
                            <Field label="Recado para a equipe (devolver ou recusar)" htmlFor="note">
                                <Textarea id="note" rows={3} value={note} onChange={(e) => setNote(e.target.value)} />
                            </Field>
                            <div className="flex gap-2">
                                <Button variant="secondary" onClick={() => post('panel.registrations.return', { note })}>Devolver para ajustes</Button>
                                <Button variant="danger" onClick={() => window.confirm('Recusar a inscrição?') && post('panel.registrations.reject', { note })}>Recusar inscrição</Button>
                            </div>
                        </div>
                    </Card>
                )}
            </div>
        </PanelLayout>
    );
}
