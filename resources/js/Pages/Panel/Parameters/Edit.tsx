import { Button, Card, Field, Input, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Props = {
    features: { key: string; label: string; off: boolean }[];
    mesario: { days_ahead: number; finished_hours: number; limit: number };
};

export default function Edit({ features, mesario }: Props) {
    const { data, setData, patch, processing, errors } = useForm({ disabled: features.filter((feature) => feature.off).map((feature) => feature.key), mesario });

    const toggle = (key: string, on: boolean) => setData('disabled', on ? data.disabled.filter((item) => item !== key) : [...data.disabled, key]);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('panel.parameters.update'), { preserveScroll: true });
    };
    const number = (key: keyof Props['mesario'], label: string, min: number, max: number, hint: string) => (
        <Field label={label} htmlFor={`mesario-${key}`} hint={hint} error={errors[`mesario.${key}` as keyof typeof errors]}>
            <Input id={`mesario-${key}`} type="number" min={min} max={max} value={data.mesario[key]} onChange={(event) => setData('mesario', { ...data.mesario, [key]: Number(event.target.value) })} />
        </Field>
    );

    return (
        <PanelLayout>
            <Head title="Parâmetros" />
            <PageHeader title="Parâmetros" subtitle="Ajustes da organização. O padrão é o comportamento original; nada muda para quem não mexer." />
            <form onSubmit={submit} className="space-y-6">
                <Card title="Recursos em uso" className="max-w-3xl">
                    <p className="mb-3 text-sm text-gray-600">Desmarque o que a organização não quer usar. Isso só <strong>reduz</strong> o que o seu pacote libera: some do menu e das páginas, e as rotas passam a recusar. Dá para religar quando quiser; nada é apagado. Overlays para OBS e domínio próprio não entram aqui.</p>
                    {features.length === 0 ? (
                        <p className="text-sm text-gray-500">Nenhum recurso opcional liberado no pacote.</p>
                    ) : (
                        <div className="space-y-2">
                            {features.map((feature) => (
                                <label key={feature.key} className="flex items-start gap-2 text-sm text-gray-700">
                                    <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-emerald-600" checked={!data.disabled.includes(feature.key)} onChange={(event) => toggle(feature.key, event.target.checked)} />
                                    {feature.label}
                                </label>
                            ))}
                        </div>
                    )}
                </Card>

                <Card title="App do mesário" className="max-w-3xl">
                    <p className="mb-3 text-sm text-gray-600">Quais jogos aparecem em Painel &gt; Mesário.</p>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {number('days_ahead', 'Dias à frente', 0, 7, 'Jogos agendados até daqui a esses dias (0 = só hoje).')}
                        {number('finished_hours', 'Horas de encerrados', 1, 48, 'Quanto tempo um jogo encerrado continua na tela.')}
                        {number('limit', 'Limite de jogos', 10, 100, 'Máximo de cartões na tela.')}
                    </div>
                </Card>

                <Button type="submit" disabled={processing}>
                    Salvar
                </Button>
            </form>
        </PanelLayout>
    );
}
