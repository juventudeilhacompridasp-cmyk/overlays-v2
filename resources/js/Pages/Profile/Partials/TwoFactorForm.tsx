import { Badge, Button, Card, Field, Input } from '@/Components/Panel/ui';
import { useForm } from '@inertiajs/react';
import QRCode from 'qrcode';
import { FormEventHandler, useEffect, useState } from 'react';

export interface TwoFactorState {
    enabled: boolean;
    pending: boolean;
    secret: string | null;
    otpauth: string | null;
    recovery_codes_left: number;
    recovery_codes: string[] | null;
    required_for_admins: boolean;
}

/** Senha da conta para ações sensíveis (ativar, renovar códigos, desativar). */
function PasswordAction({ label, routeName, method, variant = 'secondary' }: { label: string; routeName: string; method: 'post' | 'delete'; variant?: 'primary' | 'secondary' | 'danger' }) {
    const { data, setData, post, delete: destroy, processing, errors, reset } = useForm({ password: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onFinish: () => reset('password') };

        if (method === 'post') post(route(routeName), options);
        else destroy(route(routeName), options);
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row sm:items-end">
            <div className="sm:w-64">
                <Field label="Senha atual" htmlFor={`pw-${routeName}`} error={errors.password}>
                    <Input id={`pw-${routeName}`} type="password" autoComplete="current-password" value={data.password} onChange={(e) => setData('password', e.target.value)} required />
                </Field>
            </div>
            <Button type="submit" variant={variant} disabled={processing}>
                {label}
            </Button>
        </form>
    );
}

function RecoveryCodes({ codes }: { codes: string[] }) {
    return (
        <div className="rounded-md bg-amber-50 p-4 ring-1 ring-amber-200" data-testid="recovery-codes">
            <p className="mb-2 text-sm font-medium text-amber-900">Guarde estes códigos de recuperação em lugar seguro. Cada um entra uma única vez se você perder o aplicativo. Eles não serão mostrados de novo.</p>
            <ul className="grid grid-cols-2 gap-1 font-mono text-sm text-gray-900 sm:grid-cols-4">
                {codes.map((code) => (
                    <li key={code}>{code}</li>
                ))}
            </ul>
            <Button small variant="secondary" className="mt-3" onClick={() => navigator.clipboard?.writeText(codes.join('\n'))}>
                Copiar códigos
            </Button>
        </div>
    );
}

export default function TwoFactorForm({ state }: { state: TwoFactorState }) {
    const [qr, setQr] = useState<string | null>(null);
    const { data, setData, post, processing, errors, reset } = useForm({ code: '' });

    useEffect(() => {
        if (!state.otpauth) {
            setQr(null);
            return;
        }

        let active = true;
        QRCode.toDataURL(state.otpauth, { margin: 1, width: 192 }).then((url) => active && setQr(url)).catch(() => active && setQr(null));

        return () => {
            active = false;
        };
    }, [state.otpauth]);

    const confirm: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('profile.two-factor.confirm'), { preserveScroll: true, onFinish: () => reset('code') });
    };

    return (
        <Card title="Autenticação em dois fatores">
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <Badge tone={state.enabled ? 'green' : state.pending ? 'amber' : 'gray'}>{state.enabled ? 'Ativa' : state.pending ? 'Falta confirmar' : 'Desativada'}</Badge>
                {state.required_for_admins && <Badge tone="blue">Obrigatória para administradores</Badge>}
            </div>
            <p className="mb-4 text-sm text-gray-600">Além da senha, o login pede um código de 6 dígitos de um aplicativo de autenticação (Google Authenticator, Authy, 1Password...). Protege a conta mesmo se a senha vazar.</p>

            {state.recovery_codes && state.recovery_codes.length > 0 && <div className="mb-4"><RecoveryCodes codes={state.recovery_codes} /></div>}

            {state.enabled ? (
                <div className="space-y-5">
                    <p className="text-sm text-gray-700">Códigos de recuperação restantes: <b>{state.recovery_codes_left}</b>.</p>
                    <PasswordAction label="Gerar novos códigos" routeName="profile.two-factor.recovery" method="post" />
                    <PasswordAction label="Desativar" routeName="profile.two-factor.disable" method="delete" variant="danger" />
                </div>
            ) : state.pending ? (
                <div className="space-y-4">
                    <ol className="list-decimal space-y-1 pl-5 text-sm text-gray-700">
                        <li>Abra o aplicativo de autenticação e escaneie o QR code (ou digite a chave abaixo).</li>
                        <li>Digite o código de 6 dígitos que o aplicativo mostrar e confirme.</li>
                    </ol>
                    <div className="flex flex-col items-start gap-4 sm:flex-row">
                        {qr ? <img src={qr} alt="QR code para o aplicativo de autenticação" width={192} height={192} className="rounded-md bg-white p-1 ring-1 ring-gray-200" /> : <div className="h-48 w-48 rounded-md bg-gray-100" aria-hidden="true" />}
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase text-gray-500">Chave manual</p>
                            <p className="break-all font-mono text-sm text-gray-900" data-testid="two-factor-secret">{state.secret}</p>
                        </div>
                    </div>
                    <form onSubmit={confirm} className="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div className="sm:w-48">
                            <Field label="Código de 6 dígitos" htmlFor="two-factor-code" error={errors.code}>
                                <Input id="two-factor-code" inputMode="numeric" autoComplete="one-time-code" maxLength={7} value={data.code} onChange={(e) => setData('code', e.target.value)} required />
                            </Field>
                        </div>
                        <Button type="submit" disabled={processing}>
                            Confirmar e ativar
                        </Button>
                    </form>
                    <PasswordAction label="Cancelar e recomeçar" routeName="profile.two-factor.disable" method="delete" />
                </div>
            ) : (
                <PasswordAction label="Ativar" routeName="profile.two-factor.begin" method="post" variant="primary" />
            )}
        </Card>
    );
}
