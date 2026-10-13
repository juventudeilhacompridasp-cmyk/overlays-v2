import { Badge, Button, Card, Field, ImageUpload, Input, PageHeader, Select, Textarea } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Option = { value: string; label: string };

interface Props {
    profile: {
        status: string;
        status_label: string;
        sport: string | null;
        positions: string[];
        dominant_foot: string | null;
        height_cm: number | null;
        level: string | null;
        bio: string | null;
        availability: Record<string, string[]>;
        city: string | null;
        neighborhood: string | null;
        display_name: string | null;
        contact_channel: string | null;
        contact_value: string | null;
        photo_url: string | null;
        published_at: string | null;
        expires_at: string | null;
    };
    email_verified: boolean;
    options: {
        sports: (Option & { positions: Option[] })[];
        days: Option[];
        periods: Option[];
        levels: Option[];
        feet: Option[];
        channels: Option[];
    };
    revalidate_days: number;
}

const tone = (status: string) => (status === 'published' ? 'green' : status === 'suspended' ? 'red' : status === 'signed' ? 'blue' : 'amber');

export default function Dashboard({ profile, email_verified, options, revalidate_days }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        sport: profile.sport ?? '',
        positions: profile.positions,
        dominant_foot: profile.dominant_foot ?? '',
        height_cm: profile.height_cm?.toString() ?? '',
        level: profile.level ?? '',
        bio: profile.bio ?? '',
        availability: profile.availability,
        city: profile.city ?? '',
        neighborhood: profile.neighborhood ?? '',
        display_name: profile.display_name ?? '',
        contact_channel: profile.contact_channel ?? '',
        contact_value: profile.contact_value ?? '',
    });
    const [password, setPassword] = useState('');
    const [confirming, setConfirming] = useState(false);

    const positions = options.sports.find((sport) => sport.value === data.sport)?.positions ?? [];
    const published = profile.status === 'published';
    const locked = profile.status === 'signed' || profile.status === 'suspended';

    const toggle = (list: string[], value: string) => (list.includes(value) ? list.filter((item) => item !== value) : [...list, value]);

    const toggleAvailability = (day: string, period: string) =>
        setData('availability', { ...data.availability, [day]: toggle(data.availability[day] ?? [], period) });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(route('athlete.profile.update'), { preserveScroll: true });
    };

    const errorsOf = errors as Record<string, string | undefined>;

    return (
        <PanelLayout>
            <Head title="Meu perfil no Mercado" />
            <PageHeader title="Meu perfil no Mercado" subtitle="Apresente-se aos gestores de equipe: posição, disponibilidade e foto. O contato só aparece depois que você aceitar o pedido de uma equipe." />

            <div className="space-y-6">
                {!email_verified && (
                    <div role="alert" className="rounded-md bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
                        Confirme o seu e-mail para poder publicar o perfil. Procure a mensagem na sua caixa de entrada.{' '}
                        <button type="button" className="font-medium underline" onClick={() => router.post(route('verification.send'))}>
                            Reenviar e-mail
                        </button>
                    </div>
                )}

                <Card
                    title="Situação"
                    actions={
                        published ? (
                            <Button small variant="secondary" onClick={() => router.post(route('athlete.pause'), {}, { preserveScroll: true })}>
                                Pausar perfil
                            </Button>
                        ) : (
                            <Button small disabled={locked} onClick={() => router.post(route('athlete.publish'), {}, { preserveScroll: true })}>
                                Publicar no Mercado
                            </Button>
                        )
                    }
                >
                    <div className="flex flex-wrap items-center gap-3 text-sm text-gray-700">
                        <Badge tone={tone(profile.status)}>{profile.status_label}</Badge>
                        {published && profile.expires_at && (
                            <span>
                                Visível até {profile.expires_at}. Publique de novo antes dessa data para renovar (a cada {revalidate_days} dias).
                            </span>
                        )}
                        {profile.status === 'paused' && <span>Os gestores não veem o perfil enquanto estiver pausado.</span>}
                        {profile.status === 'draft' && <span>Salve os dados e publique para aparecer na busca dos gestores.</span>}
                        {published && (
                            <Button small variant="ghost" onClick={() => router.post(route('athlete.publish'), {}, { preserveScroll: true })}>
                                Renovar agora
                            </Button>
                        )}
                    </div>
                </Card>

                <Card title="Foto">
                    <ImageUpload
                        label="Foto de perfil"
                        field="photo"
                        currentUrl={profile.photo_url}
                        name="Eu"
                        uploadUrl={route('athlete.photo.store')}
                        removeUrl={route('athlete.photo.destroy')}
                        hint="JPG, PNG ou WebP. Só os gestores de equipe da organização veem, por link temporário."
                        round
                    />
                </Card>

                <form onSubmit={submit} className="space-y-6">
                    <Card title="Sobre você">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Esporte" error={errors.sport} htmlFor="sport">
                                <Select
                                    id="sport"
                                    value={data.sport}
                                    onChange={(event) => {
                                        setData((previous) => ({ ...previous, sport: event.target.value, positions: [] }));
                                    }}
                                >
                                    <option value="">Escolha…</option>
                                    {options.sports.map((sport) => (
                                        <option key={sport.value} value={sport.value}>
                                            {sport.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label="Apelido (opcional)" error={errors.display_name} htmlFor="display_name" hint="É o nome que os gestores veem; sem apelido aparece o seu nome.">
                                <Input id="display_name" value={data.display_name} onChange={(event) => setData('display_name', event.target.value)} maxLength={60} />
                            </Field>
                        </div>

                        {positions.length > 0 && (
                            <fieldset className="mt-4">
                                <legend className="mb-1 text-sm font-medium text-gray-700">Posições (até 3)</legend>
                                <div className="flex flex-wrap gap-2">
                                    {positions.map((position) => (
                                        <label key={position.value} className="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm ring-1 ring-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={data.positions.includes(position.value)}
                                                onChange={() => setData('positions', toggle(data.positions, position.value))}
                                                className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                            />
                                            {position.label}
                                        </label>
                                    ))}
                                </div>
                                {errorsOf.positions && <p className="mt-1 text-sm text-red-600">{errorsOf.positions}</p>}
                            </fieldset>
                        )}

                        <div className="mt-4 grid gap-4 sm:grid-cols-3">
                            <Field label="Pé dominante" error={errors.dominant_foot} htmlFor="dominant_foot">
                                <Select id="dominant_foot" value={data.dominant_foot} onChange={(event) => setData('dominant_foot', event.target.value)}>
                                    <option value="">Não informar</option>
                                    {options.feet.map((foot) => (
                                        <option key={foot.value} value={foot.value}>
                                            {foot.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label="Altura (cm)" error={errors.height_cm} htmlFor="height_cm">
                                <Input id="height_cm" type="number" min={100} max={250} value={data.height_cm} onChange={(event) => setData('height_cm', event.target.value)} />
                            </Field>
                            <Field label="Nível" error={errors.level} htmlFor="level">
                                <Select id="level" value={data.level} onChange={(event) => setData('level', event.target.value)}>
                                    <option value="">Não informar</option>
                                    {options.levels.map((level) => (
                                        <option key={level.value} value={level.value}>
                                            {level.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </div>

                        <div className="mt-4">
                            <Field label="Apresentação" error={errors.bio} htmlFor="bio" hint="Até 600 caracteres: onde já jogou, o que procura.">
                                <Textarea id="bio" rows={4} maxLength={600} value={data.bio} onChange={(event) => setData('bio', event.target.value)} />
                            </Field>
                        </div>
                    </Card>

                    <Card title="Onde e quando você joga">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Cidade" error={errors.city} htmlFor="city">
                                <Input id="city" value={data.city} onChange={(event) => setData('city', event.target.value)} maxLength={120} />
                            </Field>
                            <Field label="Bairro (opcional)" error={errors.neighborhood} htmlFor="neighborhood">
                                <Input id="neighborhood" value={data.neighborhood} onChange={(event) => setData('neighborhood', event.target.value)} maxLength={120} />
                            </Field>
                        </div>

                        <div className="mt-4 overflow-x-auto">
                            <table className="text-sm">
                                <thead>
                                    <tr className="text-left text-gray-500">
                                        <th className="pr-4 pb-1 font-medium">Disponibilidade</th>
                                        {options.periods.map((period) => (
                                            <th key={period.value} className="px-3 pb-1 text-center font-medium">
                                                {period.label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {options.days.map((day) => (
                                        <tr key={day.value}>
                                            <td className="py-1 pr-4 text-gray-700">{day.label}</td>
                                            {options.periods.map((period) => (
                                                <td key={period.value} className="px-3 py-1 text-center">
                                                    <input
                                                        type="checkbox"
                                                        aria-label={`${day.label}, ${period.label}`}
                                                        checked={(data.availability[day.value] ?? []).includes(period.value)}
                                                        onChange={() => toggleAvailability(day.value, period.value)}
                                                        className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    <Card title="Contato">
                        <p className="mb-3 text-sm text-gray-600">Fica escondido. Só a equipe cujo pedido você aceitar vê o canal que escolher aqui.</p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Canal" error={errors.contact_channel} htmlFor="contact_channel">
                                <Select id="contact_channel" value={data.contact_channel} onChange={(event) => setData('contact_channel', event.target.value)}>
                                    <option value="">Escolha…</option>
                                    {options.channels.map((channel) => (
                                        <option key={channel.value} value={channel.value}>
                                            {channel.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={data.contact_channel === 'whatsapp' ? 'WhatsApp (com DDD)' : 'E-mail de contato'} error={errors.contact_value} htmlFor="contact_value">
                                <Input id="contact_value" value={data.contact_value} onChange={(event) => setData('contact_value', event.target.value)} maxLength={120} />
                            </Field>
                        </div>
                    </Card>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            Salvar perfil
                        </Button>
                    </div>
                </form>

                <Card title="Apagar meu cadastro">
                    <p className="text-sm text-gray-600">Apaga o perfil, a foto e o seu acesso a esta organização. Não dá para desfazer.</p>
                    {confirming ? (
                        <form
                            className="mt-3 flex flex-wrap items-end gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.delete(route('athlete.destroy'), { data: { password } });
                            }}
                        >
                            <Field label="Confirme com a sua senha" error={errorsOf.password} htmlFor="delete_password">
                                <Input id="delete_password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="current-password" required />
                            </Field>
                            <Button type="submit" variant="danger">
                                Apagar definitivamente
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => setConfirming(false)}>
                                Cancelar
                            </Button>
                        </form>
                    ) : (
                        <div className="mt-3">
                            <Button variant="danger" onClick={() => setConfirming(true)}>
                                Apagar meu cadastro
                            </Button>
                        </div>
                    )}
                </Card>
            </div>
        </PanelLayout>
    );
}
