import { Button, Card, DeleteButton, Field, Input, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

function LogoCard({ logoUrl }: { logoUrl: string | null }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ logo: File | null }>({ logo: null });

    return (
        <Card title="Marca própria (logotipo)" className="mt-6 max-w-2xl">
            <div className="flex flex-wrap items-center gap-4">
                {logoUrl ? <img src={logoUrl} alt="" className="h-16 w-16 rounded bg-[#ffffff] object-contain p-1 ring-1 ring-gray-200" /> : <span className="text-sm text-gray-500">Sem logotipo.</span>}
                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event: FormEvent) => {
                        event.preventDefault();
                        post(route('panel.organization.logo.store'), { forceFormData: true, preserveScroll: true, onSuccess: () => reset() });
                    }}
                >
                    <input type="file" accept=".jpg,.jpeg,.png,.webp" onChange={(e) => setData('logo', e.target.files?.[0] ?? null)} className="text-sm" />
                    <Button type="submit" variant="secondary" disabled={processing || !data.logo}>
                        Enviar
                    </Button>
                </form>
                {logoUrl && <DeleteButton href={route('panel.organization.logo.destroy')} message="Remover o logotipo?" label="Remover" />}
            </div>
            {errors.logo && <p className="mt-2 text-sm text-red-600">{errors.logo}</p>}
            <p className="mt-2 text-xs text-gray-500">Aparece no topo do portal e no menu do painel. JPG, PNG ou WebP, até 3 MB.</p>
        </Card>
    );
}

type Switch = { key: string; group: string; label: string; hint: string };

function PortalOptionsCard({ settings, switches }: { settings: Record<string, boolean>; switches: Switch[] }) {
    const { data, setData, patch, processing } = useForm<Record<string, boolean>>(settings);

    return (
        <Card title="O que as páginas públicas mostram" className="mt-6 max-w-2xl">
            <form
                className="space-y-3"
                onSubmit={(event: FormEvent) => {
                    event.preventDefault();
                    patch(route('panel.organization.portal'), { preserveScroll: true });
                }}
            >
                {Array.from(new Set(switches.map((item) => item.group))).map((group) => (
                    <fieldset key={group} className="space-y-3">
                        <legend className="mb-1 text-sm font-semibold text-gray-900">{group}</legend>
                        {switches
                            .filter((item) => item.group === group)
                            .map((item) => (
                                <label key={item.key} className="flex items-start gap-2 text-sm text-gray-800">
                                    <input type="checkbox" className="mt-1" checked={data[item.key] ?? false} onChange={(e) => setData(item.key, e.target.checked)} />
                                    <span>
                                        <span className="font-medium">{item.label}</span>
                                        <span className="block text-xs text-gray-500">{item.hint}</span>
                                    </span>
                                </label>
                            ))}
                    </fieldset>
                ))}
                <Button type="submit" disabled={processing}>
                    Salvar
                </Button>
            </form>
            <p className="mt-3 text-xs text-gray-500">Nomes de atletas seguem a regra de privacidade do portal (menores aparecem abreviados). Escalações, fotos e documentos nunca aparecem.</p>
        </Card>
    );
}

export default function Edit({
    theme,
    portal_url,
    white_label,
    logo_url,
    portal_settings,
    portal_switches,
}: {
    theme: { name: string; primary: string; tagline: string | null };
    portal_url: string;
    white_label: boolean;
    logo_url: string | null;
    portal_settings: Record<string, boolean>;
    portal_switches: Switch[];
}) {
    const { data, setData, patch, processing, errors } = useForm({ name: theme.name, primary: theme.primary, tagline: theme.tagline ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('panel.organization.update'), { preserveScroll: true });
    };

    return (
        <PanelLayout>
            <Head title="Portal público" />
            <PageHeader
                title="Portal público"
                subtitle={
                    <>
                        Identidade do portal aberto ao público em{' '}
                        <a href={portal_url} target="_blank" rel="noreferrer" className="text-emerald-700 underline">
                            {portal_url}
                        </a>
                        . Só edições publicadas (fora de rascunho) aparecem lá.
                    </>
                }
            />
            <Card className="max-w-2xl">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Nome da organização" htmlFor="name" error={errors.name}>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Frase de apresentação" htmlFor="tagline" error={errors.tagline} hint="Opcional. Aparece abaixo do nome no topo do portal.">
                        <Input id="tagline" value={data.tagline} onChange={(e) => setData('tagline', e.target.value)} />
                    </Field>
                    <Field label="Cor principal" htmlFor="primary" error={errors.primary}>
                        <div className="flex items-center gap-3">
                            <input id="primary" type="color" value={data.primary} onChange={(e) => setData('primary', e.target.value)} className="h-10 w-14 cursor-pointer rounded border border-gray-300" />
                            <span className="text-sm text-gray-500">{data.primary}</span>
                        </div>
                    </Field>
                    <Button type="submit" disabled={processing}>
                        Salvar
                    </Button>
                </form>
            </Card>
            <PortalOptionsCard settings={portal_settings} switches={portal_switches} />
            {white_label && <LogoCard logoUrl={logo_url} />}
        </PanelLayout>
    );
}
