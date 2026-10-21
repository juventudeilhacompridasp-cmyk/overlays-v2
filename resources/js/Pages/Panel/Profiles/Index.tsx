import DataTable from '@/Components/DataTable';
import { Badge, Button, DeleteButton, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Profile {
    id: string;
    name: string;
    description: string | null;
    is_system: boolean;
    members: number;
    permissions: string[];
}

export default function Index({ profiles }: { profiles: Profile[] }) {
    return (
        <PanelLayout>
            <Head title="Perfis de acesso" />
            <PageHeader
                title="Perfis de acesso"
                subtitle="Conjuntos de permissões que você escolhe para cada usuário. Os perfis de sistema espelham os papéis padrão e não mudam: duplique um deles para personalizar."
                actions={<LinkButton href={route('panel.profiles.create')}>Novo perfil</LinkButton>}
            />

            <div data-testid="profiles">
                <DataTable
                    caption="Perfis de acesso"
                    searchPlaceholder="Buscar por nome, descrição ou permissão"
                    rows={profiles}
                    rowKey={(profile) => profile.id}
                    columns={[
                        {
                            key: 'name',
                            header: 'Perfil',
                            primary: true,
                            value: (profile) => `${profile.name} ${profile.description ?? ''}`,
                            cell: (profile) => (
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2 font-medium text-gray-900">
                                        {profile.name}
                                        {profile.is_system && <Badge tone="gray">sistema</Badge>}
                                    </div>
                                    {profile.description && <p className="text-sm text-gray-600">{profile.description}</p>}
                                </div>
                            ),
                        },
                        {
                            key: 'type',
                            header: 'Tipo',
                            filter: [
                                { value: 'sistema', label: 'Sistema' },
                                { value: 'personalizado', label: 'Personalizado' },
                            ],
                            value: (profile) => (profile.is_system ? 'sistema' : 'personalizado'),
                            cell: (profile) => <span className="text-gray-600">{profile.is_system ? 'Sistema (só leitura)' : 'Personalizado'}</span>,
                        },
                        {
                            key: 'members',
                            header: 'Usuários',
                            value: (profile) => profile.members,
                            cell: (profile) => (
                                <span className="text-gray-600">
                                    {profile.members}
                                    {profile.is_system ? ' (papéis padrão ficam em Usuários)' : ''}
                                </span>
                            ),
                        },
                        {
                            key: 'permissions',
                            header: 'Permissões',
                            sortable: false,
                            hideBelow: 'lg',
                            value: (profile) => profile.permissions.join(' '),
                            cell: (profile) => (
                                <ul className="list-disc pl-5 text-sm text-gray-700">
                                    {profile.permissions.length === 0 ? <li>Somente consulta o painel.</li> : profile.permissions.map((permission) => <li key={permission}>{permission}</li>)}
                                </ul>
                            ),
                        },
                        {
                            key: 'actions',
                            header: 'Ações',
                            align: 'right',
                            mobileLabel: false,
                            cell: (profile) => (
                                <div className="flex flex-wrap items-center justify-end gap-2">
                                    {!profile.is_system && (
                                        <Link href={route('panel.profiles.edit', profile.id)} className="text-sm text-emerald-700 underline">
                                            Editar
                                        </Link>
                                    )}
                                    <Button small variant="secondary" onClick={() => router.post(route('panel.profiles.duplicate', profile.id))}>
                                        Duplicar
                                    </Button>
                                    {!profile.is_system && <DeleteButton href={route('panel.profiles.destroy', profile.id)} message={`Excluir o perfil “${profile.name}”?`} label="Excluir" />}
                                </div>
                            ),
                        },
                    ]}
                />
            </div>
        </PanelLayout>
    );
}
