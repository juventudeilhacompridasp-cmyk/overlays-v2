import { Button, Card, Field, Input } from '@/Components/Panel/ui';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <Card title="Alterar senha">
            <p className="mb-4 text-sm text-gray-600">Use uma senha longa e aleatória para manter a conta segura.</p>

            <form onSubmit={updatePassword} className="space-y-4">
                <Field label="Senha atual" htmlFor="current_password" error={errors.current_password}>
                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        type="password"
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        autoComplete="current-password"
                        className="sm:max-w-sm"
                    />
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nova senha" htmlFor="password" error={errors.password}>
                        <Input
                            id="password"
                            ref={passwordInput}
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                        />
                    </Field>

                    <Field label="Confirmar nova senha" htmlFor="password_confirmation" error={errors.password_confirmation}>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                        />
                    </Field>
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        Salvar senha
                    </Button>
                    {recentlySuccessful && <span className="text-sm text-emerald-700">Salva.</span>}
                </div>
            </form>
        </Card>
    );
}
