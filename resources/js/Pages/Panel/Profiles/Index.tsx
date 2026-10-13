import { Badge, Button, Card, DeleteButton, LinkButton, PageHeader } from '@/Components/Panel/ui';
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

            <div className="max-w-4xl space-y-4" data-testid="profiles">
                {profiles.map((profile) => (
                    <Card key={profile.id}>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <div className="flex items-center gap-2 font-medium text-gray-900">
                                    {profile.name}
                                    {profile.is_system && <Badge tone="gray">sistema</Badge>}
                                </div>
                                {profile.description && <p className="text-sm text-gray-600">{profile.description}</p>}
                                <p className="mt-1 text-xs text-gray-500">
                                    {profile.members} usuário(s) com este perfil
                                    {profile.is_system ? ' (os papéis padrão são atribuídos em Usuários)' : ''}
                                </p>
                                <ul className="mt-2 list-disc pl-5 text-sm text-gray-700">
                                    {profile.permissions.length === 0 ? <li>Somente consulta o painel.</li> : profile.permissions.map((permission) => <li key={permission}>{permission}</li>)}
                                </ul>
                            </div>
                            <div className="flex items-center gap-2">
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
                        </div>
                    </Card>
                ))}
            </div>
        </PanelLayout>
    );
}
