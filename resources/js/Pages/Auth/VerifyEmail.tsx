import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout
            title="Confirme seu e-mail"
            subtitle="Enviamos um link de confirmação para o seu e-mail. Clique nele para continuar. Se não chegou, podemos enviar outro."
        >
            <Head title="Confirmar e-mail" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-emerald-200">
                    Enviamos um novo link de confirmação para o seu e-mail.
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <PrimaryButton className="w-full" disabled={processing}>
                    Reenviar e-mail de confirmação
                </PrimaryButton>

                <p className="text-center text-sm">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-gray-600 hover:text-emerald-700 hover:underline"
                    >
                        Sair
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
