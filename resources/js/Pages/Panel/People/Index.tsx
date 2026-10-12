import { Badge, Card, DeleteButton, Empty, LinkButton, Logo, PageHeader, Pagination, SearchBox } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Paginated } from '@/types/panel';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Person {
    id: string;
    name: string;
    nickname: string | null;
    birth_date: string | null;
    age: number | null;
    has_document: boolean;
    photo_url: string | null;
    roster_entries_count: number;
}

export default function Index({ people, filters }: { people: Paginated<Person>; filters: { q: string } }) {
    const [search, setSearch] = useState(filters.q);
    const canManage = useCan('manage-registry');

    return (
        <PanelLayout>
            <Head title="Atletas e comissão" />
            <PageHeader
                title="Atletas e comissão"
                subtitle="Cadastro único de pessoas (atletas, técnicos, dirigentes). O elenco de cada equipe é montado dentro da inscrição na divisão."
                actions={canManage ? <LinkButton href={route('panel.people.create')}>Nova pessoa</LinkButton> : undefined}
            />

            <div className="mb-4">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={() => router.get(route('panel.people.index'), { q: search }, { preserveState: true })}
                    placeholder="Buscar por nome, apelido ou CPF completo"
                />
            </div>

            {people.data.length === 0 ? (
                <Empty title={filters.q ? 'Nenhuma pessoa encontrada.' : 'Nenhuma pessoa cadastrada ainda.'}>
                    {!filters.q && 'Você também pode cadastrar direto no elenco de uma equipe.'}
                </Empty>
            ) : (
                <Card>
                    <div className="-m-5 overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-5 py-3">Nome</th>
                                    <th className="px-5 py-3">Idade</th>
                                    <th className="px-5 py-3">CPF</th>
                                    <th className="px-5 py-3">Elencos</th>
                                    <th className="px-5 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {people.data.map((person) => (
                                    <tr key={person.id}>
                                        <td className="px-5 py-3">
                                            <div className="flex items-center gap-3">
                                                <Logo src={person.photo_url} name={person.name} round />
                                                <div>
                                                    <Link href={route('panel.people.edit', person.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                                        {person.name}
                                                    </Link>
                                                    {person.nickname && <span className="ml-2 text-gray-500">({person.nickname})</span>}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-3 text-gray-600">{person.age !== null ? `${person.age} anos` : '—'}</td>
                                        <td className="px-5 py-3">{person.has_document ? <Badge tone="green">Informado</Badge> : <span className="text-gray-400">—</span>}</td>
                                        <td className="px-5 py-3 text-gray-600">{person.roster_entries_count}</td>
                                        <td className="px-5 py-3">
                                            {canManage && (
                                                <div className="flex justify-end gap-2">
                                                    <LinkButton href={route('panel.people.edit', person.id)} variant="secondary" small>
                                                        Editar
                                                    </LinkButton>
                                                    <DeleteButton href={route('panel.people.destroy', person.id)} message={`Excluir o cadastro de "${person.name}"?`} />
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}
            <Pagination links={people.links} />
        </PanelLayout>
    );
}
