import { Card, DeleteButton, Empty, LinkButton, PageHeader, Pagination, SearchBox } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Paginated } from '@/types/panel';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Venue {
    id: string;
    name: string;
    city: string | null;
    address: string | null;
    games_count: number;
}

export default function Index({ venues, filters }: { venues: Paginated<Venue>; filters: { q: string } }) {
    const [search, setSearch] = useState(filters.q);
    const canManage = useCan('manage-registry');

    return (
        <PanelLayout>
            <Head title="Estádios" />
            <PageHeader
                title="Estádios"
                subtitle="Locais onde os jogos acontecem."
                actions={canManage ? <LinkButton href={route('panel.venues.create')}>Novo estádio</LinkButton> : undefined}
            />

            <div className="mb-4">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={() => router.get(route('panel.venues.index'), { q: search }, { preserveState: true })}
                    placeholder="Buscar estádio pelo nome"
                />
            </div>

            {venues.data.length === 0 ? (
                <Empty title={filters.q ? 'Nenhum estádio encontrado.' : 'Nenhum estádio cadastrado ainda.'} />
            ) : (
                <Card>
                    <div className="-m-5 overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-5 py-3">Estádio</th>
                                    <th className="px-5 py-3">Cidade</th>
                                    <th className="px-5 py-3">Endereço</th>
                                    <th className="px-5 py-3">Jogos</th>
                                    <th className="px-5 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {venues.data.map((venue) => (
                                    <tr key={venue.id}>
                                        <td className="px-5 py-3 font-medium">
                                            <Link href={route('panel.venues.edit', venue.id)} className="hover:text-emerald-700">
                                                {venue.name}
                                            </Link>
                                        </td>
                                        <td className="px-5 py-3 text-gray-600">{venue.city ?? '—'}</td>
                                        <td className="px-5 py-3 text-gray-600">{venue.address ?? '—'}</td>
                                        <td className="px-5 py-3 text-gray-600">{venue.games_count}</td>
                                        <td className="px-5 py-3">
                                            {canManage && (
                                                <div className="flex justify-end gap-2">
                                                    <LinkButton href={route('panel.venues.edit', venue.id)} variant="secondary" small>
                                                        Editar
                                                    </LinkButton>
                                                    <DeleteButton
                                                        href={route('panel.venues.destroy', venue.id)}
                                                        message={`Excluir o estádio "${venue.name}"? Os jogos dele ficam sem local.`}
                                                    />
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
            <Pagination links={venues.links} />
        </PanelLayout>
    );
}
