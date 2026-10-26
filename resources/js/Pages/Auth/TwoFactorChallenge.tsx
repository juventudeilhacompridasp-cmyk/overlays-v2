import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function TwoFactorChallenge() {
    const [recovery, setRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ code: '', recovery_code: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('two-factor.verify'), { onFinish: () => reset('code', 'recovery_code') });
    };

    return (
        <GuestLayout title="Verificação em duas etapas" subtitle={recovery ? 'Digite um dos seus códigos de recuperação.' : 'Digite o código de 6 dígitos do seu aplicativo de autenticação.'}>
            <Head title="Verificação em duas etapas" />

            <form onSubmit={submit} className="space-y-4">
                {recovery ? (
                    <div>
                        <InputLabel htmlFor="recovery_code" value="Código de recuperação" />
                        <TextInput
                            id="recovery_code"
                            name="recovery_code"
                            value={data.recovery_code}
                            className="mt-1 block w-full font-mono"
                            autoComplete="one-time-code"
                            isFocused={true}
                            placeholder="xxxxx-xxxxx"
                            onChange={(e) => setData('recovery_code', e.target.value)}
                        />
                        <InputError message={errors.recovery_code} className="mt-1" />
                    </div>
                ) : (
                    <div>
                        <InputLabel htmlFor="code" value="Código do aplicativo" />
                        <TextInput
                            id="code"
                            name="code"
                            inputMode="numeric"
                            value={data.code}
                            className="mt-1 block w-full text-center font-mono text-xl tracking-widest"
                            autoComplete="one-time-code"
                            isFocused={true}
                            maxLength={7}
                            placeholder="000000"
                            onChange={(e) => setData('code', e.target.value)}
                        />
                        <InputError message={errors.code} className="mt-1" />
                    </div>
                )}

                <PrimaryButton className="w-full" disabled={processing}>
                    Verificar e entrar
                </PrimaryButton>
            </form>

            <div className="mt-4 flex flex-col gap-2 text-sm sm:flex-row sm:justify-between">
                <button
                    type="button"
                    className="min-h-11 text-left text-emerald-700 hover:underline sm:min-h-0"
                    onClick={() => {
                        setRecovery(!recovery);
                        reset('code', 'recovery_code');
                    }}
                >
                    {recovery ? 'Usar o código do aplicativo' : 'Usar um código de recuperação'}
                </button>
                <Link href={route('login')} className="flex min-h-11 items-center text-gray-600 hover:underline sm:min-h-0">
                    Voltar ao login
                </Link>
            </div>
        </GuestLayout>
    );
}
