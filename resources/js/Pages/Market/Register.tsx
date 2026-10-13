import { Field, Input } from '@/Components/Panel/ui';
import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    theme: Theme;
    seo: Seo;
    min_age: number;
}

export default function Register({ theme, seo, min_age }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        birth_date: '',
        password: '',
        password_confirmation: '',
        accept: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('market.signup.store'));
    };

    return (
        <PortalLayout theme={theme} seo={seo}>
            <Head>
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <h1 className="text-2xl font-bold">Mercado de jogadores livres</h1>
            <p className="mt-1 max-w-2xl text-gray-600">
                Sem time? Monte o seu perfil com posição, disponibilidade e foto e seja encontrado pelos gestores das equipes. Só maiores de {min_age} anos. Seu
                telefone ou e-mail só aparece para uma equipe depois que você aceitar o contato dela.
            </p>

            <form onSubmit={submit} className="mt-6 max-w-xl space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <Field label="Nome completo" error={errors.name} htmlFor="name">
                    <Input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} autoComplete="name" required />
                </Field>
                <Field label="E-mail" error={errors.email} htmlFor="email" hint="Enviaremos um link para confirmar o e-mail. Sem a confirmação o perfil não pode ser publicado.">
                    <Input id="email" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} autoComplete="username" required />
                </Field>
                <Field label="Data de nascimento" error={errors.birth_date} htmlFor="birth_date" hint={`Só maiores de ${min_age} anos. A data exata nunca aparece para as equipes.`}>
                    <Input id="birth_date" type="date" value={data.birth_date} onChange={(event) => setData('birth_date', event.target.value)} required />
                </Field>
                <Field label="Senha" error={errors.password} htmlFor="password">
                    <Input id="password" type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} autoComplete="new-password" required />
                </Field>
                <Field label="Repita a senha" error={errors.password_confirmation} htmlFor="password_confirmation">
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                        autoComplete="new-password"
                        required
                    />
                </Field>

                <div>
                    <label className="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={data.accept} onChange={(event) => setData('accept', event.target.checked)} className="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                        <span>
                            Li e aceito os{' '}
                            <a href={route('portal.terms')} target="_blank" rel="noreferrer" className="text-emerald-700 underline">
                                termos de uso
                            </a>{' '}
                            e a{' '}
                            <a href={route('portal.privacy')} target="_blank" rel="noreferrer" className="text-emerald-700 underline">
                                política de privacidade
                            </a>
                            .
                        </span>
                    </label>
                    {errors.accept && <p className="mt-1 text-sm text-red-600">{errors.accept}</p>}
                </div>

                <div className="flex items-center justify-between">
                    <Link href={route('login')} className="text-sm text-gray-600 underline hover:text-gray-900">
                        Já tenho cadastro
                    </Link>
                    <button type="submit" disabled={processing} className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                        Criar meu perfil
                    </button>
                </div>
            </form>
        </PortalLayout>
    );
}
