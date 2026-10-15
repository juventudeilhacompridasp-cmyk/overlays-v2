import { Button, Card, Field, Input, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Props = {
    features: { key: string; label: string; off: boolean }[];
    mesario: { days_ahead: number; finished_hours: number; limit: number };
    portal: { home: number; widgets: number; scorers: number; stats_top: number; stats_form: number };
    public_api: boolean;
    quick_result: boolean;
};

export default function Edit({ features, mesario, portal, public_api, quick_result }: Props) {
    const { data, setData, patch, processing, errors } = useForm({ disabled: features.filter((feature) => feature.off).map((feature) => feature.key), mesario, portal, public_api, quick_result });

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

    const quantity = (key: keyof Props['portal'], label: string, min: number, max: number, hint: string) => (
        <Field label={label} htmlFor={`portal-${key}`} hint={hint} error={errors[`portal.${key}` as keyof typeof errors]}>
            <Input id={`portal-${key}`} type="number" min={min} max={max} value={data.portal[key]} onChange={(event) => setData('portal', { ...data.portal, [key]: Number(event.target.value) })} />
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

                <Card title="Lançamento de resultados" className="max-w-3xl">
                    <label className="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-emerald-600" checked={data.quick_result} onChange={(event) => setData('quick_result', event.target.checked)} />
                        <span>Permitir o <strong>resultado rápido</strong> (placar final sem súmula detalhada) na tela da fase e no app do mesário. Desligado, só a súmula (eventos) lança o placar; W.O. continua disponível.</span>
                    </label>
                </Card>

                <Card title="Portal público e widgets" className="max-w-3xl">
                    <p className="mb-3 text-sm text-gray-600">Quantos itens aparecem nas listas públicas.</p>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {quantity('home', 'Itens por bloco da página inicial', 4, 24, 'Ao vivo, próximos e recentes.')}
                        {quantity('widgets', 'Jogos por lista dos widgets', 3, 20, 'Próximos e recentes do widget de jogos.')}
                        {quantity('scorers', 'Artilheiros por divisão', 3, 30, 'Tabela de artilharia da edição.')}
                        {quantity('stats_top', 'Maiores resultados (estatísticas)', 3, 10, 'Maiores vitórias e jogos com mais placar.')}
                        {quantity('stats_form', 'Jogos da forma recente (estatísticas)', 3, 10, 'Últimos resultados de cada equipe.')}
                    </div>
                </Card>

                <Card title="API pública" className="max-w-3xl">
                    <label className="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-emerald-600" checked={data.public_api} onChange={(event) => setData('public_api', event.target.checked)} />
                        <span>
                            Manter a API pública de consulta (<code>/api/v1</code>) ligada. Desligada, ela responde 404 neste endereço; o controle privado dos overlays (chave própria) não é afetado.
                        </span>
                    </label>
                </Card>

                <Button type="submit" disabled={processing}>
                    Salvar
                </Button>
            </form>
        </PanelLayout>
    );
}
