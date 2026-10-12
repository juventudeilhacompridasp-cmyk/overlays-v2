import Modal from '@/Components/Modal';
import { maskCpf } from '@/Components/Panel/cpf';
import { Badge, Button, Card, DeleteButton, Empty, Field, Input, LinkButton, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Option } from '@/types/panel';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface RosterItem {
    id: string;
    role: string;
    role_label: string;
    shirt_number: number | null;
    status: string;
    status_label: string;
    person: { id: string; name: string; nickname: string | null; age: number | null };
}

interface Props {
    competition: { id: string; name: string };
    season: { id: string; name: string };
    division: { id: string; name: string };
    entry: { id: string; status_label: string };
    team: { id: string; name: string; city: string | null };
    roster: RosterItem[];
    people: { id: string; name: string }[];
    options: { roles: Option[]; statuses: Option[] };
}

function AddModal({ entryId, people, roles, onClose }: { entryId: string; people: Props['people']; roles: Option[]; onClose: () => void }) {
    const [mode, setMode] = useState<'existing' | 'new'>(people.length > 0 ? 'existing' : 'new');
    const { data, setData, post, processing, errors } = useForm({
        person_id: '',
        name: '',
        nickname: '',
        birth_date: '',
        document: '',
        role: 'athlete',
        shirt_number: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('panel.entries.roster.store', entryId), { preserveScroll: true, onSuccess: onClose });
    };

    const choose = (next: 'existing' | 'new') => {
        setMode(next);
        setData((current) => ({ ...current, person_id: '', name: '', nickname: '', birth_date: '', document: '' }));
    };

    return (
        <Modal show onClose={onClose} maxWidth="lg">
            <form onSubmit={submit} className="space-y-4 p-6">
                <h2 className="text-lg font-semibold text-gray-900">Adicionar ao elenco</h2>

                <div className="inline-flex rounded-md bg-gray-100 p-1 text-sm">
                    <button type="button" onClick={() => choose('existing')} className={`rounded px-3 py-1 ${mode === 'existing' ? 'bg-white font-medium shadow-sm' : 'text-gray-600'}`}>
                        Pessoa já cadastrada
                    </button>
                    <button type="button" onClick={() => choose('new')} className={`rounded px-3 py-1 ${mode === 'new' ? 'bg-white font-medium shadow-sm' : 'text-gray-600'}`}>
                        Nova pessoa
                    </button>
                </div>

                {mode === 'existing' ? (
                    <Field label="Pessoa" htmlFor="person_id" error={errors.person_id}>
                        <Select id="person_id" value={data.person_id} onChange={(e) => setData('person_id', e.target.value)} required>
                            <option value="">Selecione…</option>
                            {people.map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                ) : (
                    <>
                        <Field label="Nome completo" htmlFor="name" error={errors.name}>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus required />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Apelido" htmlFor="nickname" error={errors.nickname}>
                                <Input id="nickname" value={data.nickname} onChange={(e) => setData('nickname', e.target.value)} />
                            </Field>
                            <Field label="Data de nascimento" htmlFor="birth_date" error={errors.birth_date}>
                                <Input id="birth_date" type="date" value={data.birth_date} onChange={(e) => setData('birth_date', e.target.value)} />
                            </Field>
                        </div>
                        <Field label="CPF" htmlFor="document" error={errors.document} hint="Opcional. Não é guardado: só um código de verificação, para evitar duplicidade (LGPD).">
                            <Input id="document" inputMode="numeric" autoComplete="off" placeholder="000.000.000-00" value={data.document} onChange={(e) => setData('document', maskCpf(e.target.value))} />
                        </Field>
                    </>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Função" htmlFor="role" error={errors.role}>
                        <Select id="role" value={data.role} onChange={(e) => setData('role', e.target.value)}>
                            {roles.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    {data.role === 'athlete' && (
                        <Field label="Camisa" htmlFor="shirt_number" error={errors.shirt_number}>
                            <Input id="shirt_number" type="number" min={1} max={99} value={data.shirt_number} onChange={(e) => setData('shirt_number', e.target.value)} />
                        </Field>
                    )}
                </div>

                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Adicionar
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function EditModal({ item, roles, statuses, onClose }: { item: RosterItem; roles: Option[]; statuses: Option[]; onClose: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({
        role: item.role,
        shirt_number: item.shirt_number ? String(item.shirt_number) : '',
        status: item.status,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('panel.roster.update', item.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal show onClose={onClose} maxWidth="md">
            <form onSubmit={submit} className="space-y-4 p-6">
                <h2 className="text-lg font-semibold text-gray-900">{item.person.name}</h2>
                <Field label="Função" htmlFor="edit-role" error={errors.role}>
                    <Select id="edit-role" value={data.role} onChange={(e) => setData('role', e.target.value)}>
                        {roles.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                </Field>
                {data.role === 'athlete' && (
                    <Field label="Camisa" htmlFor="edit-shirt" error={errors.shirt_number}>
                        <Input id="edit-shirt" type="number" min={1} max={99} value={data.shirt_number} onChange={(e) => setData('shirt_number', e.target.value)} />
                    </Field>
                )}
                <Field label="Situação" htmlFor="edit-status" error={errors.status}>
                    <Select id="edit-status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                        {statuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Salvar
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Show({ competition, season, division, entry, team, roster, people, options }: Props) {
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<RosterItem | null>(null);

    const active = roster.filter((item) => item.status === 'active');
    const athletes = active.filter((item) => item.role === 'athlete').length;
    const staff = active.length - athletes;

    return (
        <PanelLayout>
            <Head title={`Elenco · ${team.name}`} />
            <PageHeader
                title={`Elenco de ${team.name}`}
                subtitle={
                    <>
                        {division.name} · {athletes} {athletes === 1 ? 'atleta' : 'atletas'} · {staff} na comissão{' '}
                        <Badge tone={entry.status_label === 'Confirmada' ? 'green' : 'amber'}>{entry.status_label}</Badge>
                    </>
                }
                breadcrumbs={[
                    { label: 'Competições', href: route('panel.competitions.index') },
                    { label: competition.name, href: route('panel.competitions.show', competition.id) },
                    { label: season.name, href: route('panel.seasons.show', season.id) },
                    { label: division.name, href: route('panel.divisions.show', division.id) },
                    { label: team.name },
                ]}
                actions={
                    <>
                        <LinkButton href={route('panel.entries.cards', entry.id)} variant="secondary">
                            Carteirinhas
                        </LinkButton>
                        <Button onClick={() => setAdding(true)}>Adicionar ao elenco</Button>
                    </>
                }
            />

            <Card>
                {roster.length === 0 ? (
                    <Empty title="Elenco vazio.">Adicione os atletas e a comissão técnica desta equipe.</Empty>
                ) : (
                    <div className="-m-5 overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-5 py-3">Camisa</th>
                                    <th className="px-5 py-3">Nome</th>
                                    <th className="px-5 py-3">Idade</th>
                                    <th className="px-5 py-3">Função</th>
                                    <th className="px-5 py-3">Situação</th>
                                    <th className="px-5 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {roster.map((item) => (
                                    <tr key={item.id} className={item.status === 'removed' ? 'text-gray-400' : ''}>
                                        <td className="px-5 py-3 font-semibold">{item.shirt_number ?? '—'}</td>
                                        <td className="px-5 py-3">
                                            <Link href={route('panel.people.edit', item.person.id)} className="font-medium hover:text-emerald-700">
                                                {item.person.name}
                                            </Link>
                                            {item.person.nickname && <span className="ml-2 text-gray-500">({item.person.nickname})</span>}
                                        </td>
                                        <td className="px-5 py-3">{item.person.age !== null ? `${item.person.age} anos` : '—'}</td>
                                        <td className="px-5 py-3">{item.role_label}</td>
                                        <td className="px-5 py-3">
                                            <Badge tone={item.status === 'active' ? 'green' : 'gray'}>{item.status_label}</Badge>
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex justify-end gap-2">
                                                {item.status === 'active' && (
                                                    <LinkButton small variant="secondary" href={route('panel.roster.card', item.id)}>
                                                        Carteirinha
                                                    </LinkButton>
                                                )}
                                                <Button small variant="secondary" onClick={() => setEditing(item)}>
                                                    Editar
                                                </Button>
                                                <DeleteButton
                                                    href={route('panel.roster.destroy', item.id)}
                                                    message={`Tirar "${item.person.name}" do elenco?`}
                                                    label="Remover"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {adding && <AddModal entryId={entry.id} people={people} roles={options.roles} onClose={() => setAdding(false)} />}
            {editing && <EditModal key={editing.id} item={editing} roles={options.roles} statuses={options.statuses} onClose={() => setEditing(null)} />}
        </PanelLayout>
    );
}
