import { Button, Card, Field, Input, LinkButton, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Option, RulesData } from '@/types/panel';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    competition: { id: string; name: string };
    season: { id: string; name: string; year: number; starts_on: string | null; ends_on: string | null; status: string } | null;
    rules: RulesData;
    options: { statuses: Option[]; tiebreakers: Option[] };
    defaults: { year: number };
}

const toNumber = (value: string) => (value === '' ? 0 : Number(value));

export default function Form({ competition, season, rules, options, defaults }: Props) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: season?.name ?? String(defaults.year),
        year: season?.year ?? defaults.year,
        starts_on: season?.starts_on ?? '',
        ends_on: season?.ends_on ?? '',
        status: season?.status ?? 'draft',
        rules,
    });

    const err = (key: string) => (errors as Record<string, string | undefined>)[key];
    const labelOf = (value: string) => options.tiebreakers.find((o) => o.value === value)?.label ?? value;

    const updateRules = (change: (draft: RulesData) => void) => {
        const draft = structuredClone(data.rules);
        change(draft);
        setData('rules', draft);
    };

    const move = (index: number, delta: number) =>
        updateRules((draft) => {
            const target = index + delta;
            if (target < 0 || target >= draft.tiebreakers.length) return;
            [draft.tiebreakers[index], draft.tiebreakers[target]] = [draft.tiebreakers[target], draft.tiebreakers[index]];
        });

    const remaining = options.tiebreakers.filter((o) => !data.rules.tiebreakers.includes(o.value));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (season) {
            put(route('panel.seasons.update', season.id));
        } else {
            post(route('panel.competitions.seasons.store', competition.id));
        }
    };

    const numberField = (label: string, key: string, value: number, change: (draft: RulesData, value: number) => void, hint?: string) => (
        <Field label={label} htmlFor={key} error={err(key)} hint={hint}>
            <Input
                id={key}
                type="number"
                min={0}
                value={value}
                onChange={(e) => updateRules((draft) => change(draft, toNumber(e.target.value)))}
            />
        </Field>
    );

    const checkbox = (label: string, key: string, checked: boolean, change: (draft: RulesData, value: boolean) => void) => (
        <label className="flex items-start gap-2 text-sm text-gray-700">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => updateRules((draft) => change(draft, e.target.checked))}
                className="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
            />
            <span>{label}</span>
            {err(key) && <span className="text-red-600">{err(key)}</span>}
        </label>
    );

    return (
        <PanelLayout>
            <Head title={season ? 'Editar edição' : 'Nova edição'} />
            <PageHeader
                title={season ? 'Editar edição' : 'Nova edição'}
                subtitle="A edição guarda o regulamento: ele vale para todas as divisões, salvo se uma divisão sobrescrever."
                breadcrumbs={[
                    { label: 'Competições', href: route('panel.competitions.index') },
                    { label: competition.name, href: route('panel.competitions.show', competition.id) },
                    { label: season ? season.name : 'Nova edição' },
                ]}
            />

            <form onSubmit={submit} className="space-y-6">
                <Card title="Dados da edição">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Nome" htmlFor="name" error={errors.name} hint="Ex.: 2026 ou Temporada 2026/27">
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        </Field>
                        <Field label="Ano" htmlFor="year" error={errors.year}>
                            <Input id="year" type="number" value={data.year} onChange={(e) => setData('year', toNumber(e.target.value))} required />
                        </Field>
                        <Field label="Início" htmlFor="starts_on" error={errors.starts_on}>
                            <Input id="starts_on" type="date" value={data.starts_on} onChange={(e) => setData('starts_on', e.target.value)} />
                        </Field>
                        <Field label="Término" htmlFor="ends_on" error={errors.ends_on}>
                            <Input id="ends_on" type="date" value={data.ends_on} onChange={(e) => setData('ends_on', e.target.value)} />
                        </Field>
                        <Field label="Situação" htmlFor="status" error={errors.status}>
                            <Select id="status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                {options.statuses.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    </div>
                </Card>

                <Card title="Regulamento: pontuação">
                    <div className="grid gap-4 sm:grid-cols-3">
                        {numberField('Pontos por vitória', 'rules.points.win', data.rules.points.win, (d, v) => (d.points.win = v))}
                        {numberField('Pontos por empate', 'rules.points.draw', data.rules.points.draw, (d, v) => (d.points.draw = v))}
                        {numberField('Pontos por derrota', 'rules.points.loss', data.rules.points.loss, (d, v) => (d.points.loss = v))}
                    </div>
                </Card>

                <Card title="Regulamento: critérios de desempate">
                    <p className="mb-3 text-sm text-gray-600">
                        Aplicados na ordem abaixo, só às equipes que continuam empatadas em pontos. O confronto direto só vale quando restam exatamente duas equipes.
                    </p>
                    {err('rules.tiebreakers') && <p className="mb-2 text-sm text-red-600">{err('rules.tiebreakers')}</p>}
                    <ol className="space-y-2">
                        {data.rules.tiebreakers.map((value, index) => (
                            <li key={value} className="flex items-center gap-2 rounded-md bg-gray-50 px-3 py-2 text-sm ring-1 ring-gray-200">
                                <span className="w-6 text-center font-semibold text-gray-500">{index + 1}º</span>
                                <span className="flex-1 text-gray-800">{labelOf(value)}</span>
                                <Button small variant="ghost" disabled={index === 0} onClick={() => move(index, -1)} aria-label="Subir">
                                    ↑
                                </Button>
                                <Button
                                    small
                                    variant="ghost"
                                    disabled={index === data.rules.tiebreakers.length - 1}
                                    onClick={() => move(index, 1)}
                                    aria-label="Descer"
                                >
                                    ↓
                                </Button>
                                <Button
                                    small
                                    variant="danger"
                                    disabled={data.rules.tiebreakers.length === 1}
                                    onClick={() => updateRules((draft) => draft.tiebreakers.splice(index, 1))}
                                >
                                    Remover
                                </Button>
                            </li>
                        ))}
                    </ol>
                    {remaining.length > 0 && (
                        <div className="mt-3 flex items-center gap-2">
                            <Select
                                aria-label="Adicionar critério"
                                value=""
                                onChange={(e) => e.target.value && updateRules((draft) => draft.tiebreakers.push(e.target.value))}
                                className="sm:w-80"
                            >
                                <option value="">Adicionar critério…</option>
                                {remaining.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    )}
                </Card>

                <Card title="Regulamento: cartões e suspensão">
                    <div className="grid gap-4 sm:grid-cols-3">
                        {numberField('Amarelos para suspender', 'rules.cards.yellow_for_suspension', data.rules.cards.yellow_for_suspension, (d, v) => (d.cards.yellow_for_suspension = v), 'Ex.: 3 amarelos')}
                        {numberField('Jogos por acúmulo de amarelos', 'rules.cards.yellow_suspension_games', data.rules.cards.yellow_suspension_games, (d, v) => (d.cards.yellow_suspension_games = v))}
                        {numberField('Jogos por cartão vermelho', 'rules.cards.red_suspension_games', data.rules.cards.red_suspension_games, (d, v) => (d.cards.red_suspension_games = v))}
                    </div>
                    <div className="mt-4 space-y-2">
                        {checkbox('Zerar os amarelos acumulados ao mudar de fase (as suspensões já geradas continuam)', 'rules.cards.reset_yellows_between_stages', data.rules.cards.reset_yellows_between_stages, (d, v) => (d.cards.reset_yellows_between_stages = v))}
                        {checkbox('Dois amarelos no mesmo jogo contam para a série de amarelos (por padrão não contam; é uma expulsão)', 'rules.cards.second_yellow_counts_for_series', data.rules.cards.second_yellow_counts_for_series, (d, v) => (d.cards.second_yellow_counts_for_series = v))}
                    </div>
                </Card>

                <Card title="Regulamento: W.O. e substituições">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {numberField('Gols do vencedor no W.O.', 'rules.walkover.winner_goals', data.rules.walkover.winner_goals, (d, v) => (d.walkover.winner_goals = v))}
                        {numberField('Gols do perdedor no W.O.', 'rules.walkover.loser_goals', data.rules.walkover.loser_goals, (d, v) => (d.walkover.loser_goals = v))}
                        {numberField('Máx. de substituições', 'rules.substitutions.max', data.rules.substitutions.max, (d, v) => (d.substitutions.max = v))}
                        {numberField('Máx. de atos de substituição', 'rules.substitutions.max_windows', data.rules.substitutions.max_windows, (d, v) => (d.substitutions.max_windows = v))}
                    </div>
                    <div className="mt-4">
                        {checkbox('Quem sai pode voltar ao jogo (substituição volante, como no futsal)', 'rules.substitutions.allow_reentry', data.rules.substitutions.allow_reentry, (d, v) => (d.substitutions.allow_reentry = v))}
                    </div>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" disabled={processing}>
                        Salvar edição
                    </Button>
                    <LinkButton
                        href={season ? route('panel.seasons.show', season.id) : route('panel.competitions.show', competition.id)}
                        variant="secondary"
                    >
                        Cancelar
                    </LinkButton>
                </div>
            </form>
        </PanelLayout>
    );
}
