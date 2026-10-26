import { PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import TwoFactorForm, { TwoFactorState } from './Partials/TwoFactorForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
    twoFactor,
}: PageProps<{ mustVerifyEmail: boolean; status?: string; twoFactor: TwoFactorState }>) {
    return (
        <PanelLayout>
            <Head title="Meu perfil" />

            <PageHeader title="Meu perfil" subtitle="Seus dados de acesso, senha e segurança da conta." />

            <div className="max-w-3xl space-y-6">
                <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                <UpdatePasswordForm />
                <TwoFactorForm state={twoFactor} />
                <DeleteUserForm />
            </div>
        </PanelLayout>
    );
}
