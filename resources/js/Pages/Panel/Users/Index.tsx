import { Badge, Button, Card, Field, Input, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Member {
    id: string;
    name: string;
    email: string;
    role: string;
    is_me: boolean;
}

interface RoleInfo {
    value: string;
    label: string;
    abilities: string[];
}

export default function Index({ members, roles }: { members: Member[]; roles: RoleInfo[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', email: '', password: '', role: 'operator' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('panel.users.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <PanelLayout>
            <Head title="Usuários" />
            <PageHeader title="Usuários e papéis" subtitle="Quem acessa o painel e o que cada pessoa pode fazer. O cadastro público é fechado: os acessos são criados aqui." />

            <div className="space-y-6">
                <Card title={`Usuários (${members.length})`}>
                    <ul className="divide-y divide-gray-100">
                        {members.map((member) => (
                            <li key={member.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div>
                                    <div className="font-medium text-gray-900">
                                        {member.name} {member.is_me && <Badge tone="blue">você</Badge>}
                                    </div>
                                    <div className="text-sm text-gray-500">{member.email}</div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Select
                                        aria-label={`Papel de ${member.name}`}
                                        className="!w-48"
                                        value={member.role}
                                        onChange={(event) => router.patch(route('panel.users.update', member.id), { role: event.target.value }, { preserveScroll: true })}
                                    >
                                        {roles.map((role) => (
                                            <option key={role.value} value={role.value}>
                                                {role.label}
                                            </option>
                                        ))}
                                    </Select>
                                    <Button
                                        small
                                        variant="danger"
                                        onClick={() => {
                                            if (window.confirm(`Remover o acesso de ${member.name}? A conta continua existindo, sem acesso ao painel.`)) {
                                                router.delete(route('panel.users.destroy', member.id), { preserveScroll: true });
                                            }
                                        }}
                                    >
                                        Remover acesso
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card title="Novo usuário">
                    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2" aria-label="Novo usuário">
                        <Field label="Nome" htmlFor="name" error={errors.name}>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        </Field>
                        <Field label="E-mail" htmlFor="email" error={errors.email}>
                            <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                        </Field>
                        <Field label="Senha provisória" htmlFor="password" error={errors.password} hint="Mínimo de 8 caracteres. Passe por um canal seguro; a pessoa troca depois em Perfil.">
                            <Input id="password" type="text" autoComplete="off" value={data.password} onChange={(e) => setData('password', e.target.value)} required />
                        </Field>
                        <Field label="Papel" htmlFor="role" error={errors.role}>
                            <Select id="role" value={data.role} onChange={(e) => setData('role', e.target.value)}>
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <div className="sm:col-span-2">
                            <Button type="submit" disabled={processing}>
                                Criar usuário
                            </Button>
                        </div>
                    </form>
                </Card>

                <Card title="O que cada papel pode">
                    <dl className="grid gap-4 sm:grid-cols-2">
                        {roles.map((role) => (
                            <div key={role.value}>
                                <dt className="font-medium text-gray-900">{role.label}</dt>
                                <dd className="mt-1 text-sm text-gray-600">
                                    {role.abilities.length === 0 ? 'Somente consulta o painel.' : `Consulta tudo e pode: ${role.abilities.map((a) => a.toLowerCase()).join('; ')}.`}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Card>
            </div>
        </PanelLayout>
    );
}
