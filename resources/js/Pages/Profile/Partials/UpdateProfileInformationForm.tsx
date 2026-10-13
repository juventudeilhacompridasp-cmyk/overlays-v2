import { Button, Card, Field, Input } from '@/Components/Panel/ui';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <Card title="Dados pessoais">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nome" htmlFor="name" error={errors.name}>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoComplete="name"
                        />
                    </Field>

                    <Field label="E-mail" htmlFor="email" error={errors.email}>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="username"
                        />
                    </Field>
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
                        Seu e-mail ainda não foi confirmado.{' '}
                        <Link
                            href={route('verification.send')}
                            method="post"
                            as="button"
                            className="font-medium underline hover:text-amber-900"
                        >
                            Reenviar e-mail de confirmação
                        </Link>
                        {status === 'verification-link-sent' && (
                            <div className="mt-1 font-medium text-emerald-700">
                                Enviamos um novo link de confirmação para o seu e-mail.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        Salvar
                    </Button>
                    {recentlySuccessful && <span className="text-sm text-emerald-700">Salvo.</span>}
                </div>
            </form>
        </Card>
    );
}
