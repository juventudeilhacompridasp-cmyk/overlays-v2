import DataTable from '@/Components/DataTable';
import { Badge, Button, Empty, Input, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { formatWhen } from '@/lib/statusPage';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Account {
    id: string;
    name: string;
    email: string;
    enabled_at: string | null;
    recovery_left: number;
    is_platform_admin: boolean;
}

function ResetForm({ account }: { account: Account }) {
    const [open, setOpen] = useState(false);
    const [password, setPassword] = useState('');
    const [busy, setBusy] = useState(false);

    if (!open) {
        return (
            <button type="button" onClick={() => setOpen(true)} className="min-h-11 rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 sm:min-h-0">
                Reiniciar dois fatores
            </button>
        );
    }

    return (
        <form
            className="flex flex-wrap items-center justify-end gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                setBusy(true);
                router.post(route('platform.accounts.reset', account.id), { password }, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setOpen(false) });
            }}
        >
            <Input type="password" autoComplete="current-password" required placeholder="Sua senha" value={password} onChange={(event) => setPassword(event.target.value)} className="w-40" aria-label={`Sua senha para reiniciar ${account.name}`} />
            <Button type="submit" disabled={busy || password === ''}>
                Confirmar
            </Button>
            <button type="button" onClick={() => setOpen(false)} className="text-xs font-semibold text-gray-600 hover:underline">
                Cancelar
            </button>
        </form>
    );
}

export default function AccountsIndex({ accounts }: { accounts: Account[] }) {
    return (
        <PanelLayout>
            <Head title="Contas e dois fatores" />
            <PageHeader title="Contas e dois fatores" subtitle="Contas com autenticação em dois fatores ativa. Reinicie só quando a pessoa perdeu o aparelho e os códigos de recuperação: ela é avisada por e-mail e ativa de novo em Meu perfil." />

            <DataTable
                caption="Contas com dois fatores"
                searchPlaceholder="Buscar por nome ou e-mail"
                rows={accounts}
                rowKey={(account) => account.id}
                persistKey="platform:accounts"
                empty={<Empty title="Nenhuma conta com dois fatores">Quando alguém ativar a autenticação em dois fatores em Meu perfil, a conta aparece aqui.</Empty>}
                columns={[
                    {
                        key: 'name',
                        header: 'Conta',
                        primary: true,
                        value: (account) => `${account.name} ${account.email}`,
                        cell: (account) => (
                            <div>
                                <div className="font-semibold text-gray-900">{account.name}</div>
                                <div className="text-sm text-gray-600">{account.email}</div>
                            </div>
                        ),
                    },
                    { key: 'kind', header: 'Tipo', hideBelow: 'lg', value: (account) => (account.is_platform_admin ? 'Super Admin' : 'Usuário'), cell: (account) => (account.is_platform_admin ? <Badge tone="blue">Super Admin</Badge> : <Badge>Usuário</Badge>) },
                    { key: 'since', header: 'Ativo desde', value: (account) => account.enabled_at, cell: (account) => <span className="text-xs text-gray-500">{account.enabled_at ? formatWhen(account.enabled_at) : '—'}</span> },
                    { key: 'codes', header: 'Códigos de recuperação', hideBelow: 'lg', value: (account) => String(account.recovery_left), cell: (account) => <span className="text-sm text-gray-700">{account.recovery_left} restantes</span> },
                    { key: 'actions', header: 'Ações', align: 'right', mobileLabel: false, cell: (account) => <ResetForm account={account} /> },
                ]}
            />
        </PanelLayout>
    );
}
