import DataTable, { serverTable } from '@/Components/DataTable';
import { Badge, DeleteButton, LinkButton, Logo, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Paginated, TableFilters } from '@/types/panel';
import { Head, Link } from '@inertiajs/react';

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

export default function Index({ people, filters }: { people: Paginated<Person>; filters: TableFilters }) {
    const canManage = useCan('manage-registry');

    return (
        <PanelLayout>
            <Head title="Pessoas" />
            <PageHeader
                title="Pessoas"
                subtitle="Cadastro único de pessoas (atletas, técnicos, dirigentes). O elenco de cada equipe é montado dentro da inscrição na divisão."
                actions={canManage ? <LinkButton href={route('panel.people.create')}>Nova pessoa</LinkButton> : undefined}
            />

            <DataTable
                caption="Pessoas"
                searchPlaceholder="Buscar por nome, apelido ou CPF completo"
                rows={people.data}
                rowKey={(person) => person.id}
                server={serverTable(route('panel.people.index'), people, filters, { sort: 'name' }, ['people', 'filters'])}
                empty="Nenhuma pessoa cadastrada ainda. Você também pode cadastrar direto no elenco de uma equipe."
                columns={[
                    {
                        key: 'name',
                        header: 'Nome',
                        primary: true,
                        value: (person) => person.name,
                        cell: (person) => (
                            <div className="flex items-center gap-3">
                                <Logo src={person.photo_url} name={person.name} round />
                                <div>
                                    <Link href={route('panel.people.edit', person.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                        {person.name}
                                    </Link>
                                    {person.nickname && <span className="ml-2 text-gray-500">({person.nickname})</span>}
                                </div>
                            </div>
                        ),
                    },
                    {
                        key: 'age',
                        header: 'Idade',
                        value: (person) => person.age,
                        filter: [
                            { value: 'minor', label: 'Menores de 18' },
                            { value: 'adult', label: '18 anos ou mais' },
                            { value: 'unknown', label: 'Sem data de nascimento' },
                        ],
                        cell: (person) => <span className="text-gray-600">{person.age !== null ? `${person.age} anos` : '—'}</span>,
                    },
                    {
                        key: 'document',
                        header: 'CPF',
                        sortable: false,
                        value: (person) => (person.has_document ? 'Informado' : 'Não informado'),
                        filter: [
                            { value: 'informed', label: 'Informado' },
                            { value: 'missing', label: 'Não informado' },
                        ],
                        hideBelow: 'lg',
                        cell: (person) => (person.has_document ? <Badge tone="green">Informado</Badge> : <span className="text-gray-400">—</span>),
                    },
                    {
                        key: 'rosters',
                        header: 'Elencos',
                        value: (person) => person.roster_entries_count,
                        filter: [
                            { value: 'with', label: 'Em algum elenco' },
                            { value: 'without', label: 'Sem elenco' },
                        ],
                        cell: (person) => <span className="text-gray-600">{person.roster_entries_count}</span>,
                    },
                    {
                        key: 'actions',
                        header: 'Ações',
                        align: 'right',
                        mobileLabel: false,
                        cell: (person) =>
                            canManage ? (
                                <div className="flex flex-wrap justify-end gap-2">
                                    <LinkButton href={route('panel.people.edit', person.id)} variant="secondary" small>
                                        Editar
                                    </LinkButton>
                                    <DeleteButton href={route('panel.people.destroy', person.id)} message={`Excluir o cadastro de "${person.name}"?`} />
                                </div>
                            ) : null,
                    },
                ]}
            />
        </PanelLayout>
    );
}
