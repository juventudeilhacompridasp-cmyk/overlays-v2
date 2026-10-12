import { Badge, Button, Card, DeleteButton, Empty, Field, Input, LinkButton, PageHeader, Select } from '@/Components/Panel/ui';
import Modal from '@/Components/Modal';
import PanelLayout from '@/Layouts/PanelLayout';
import { Option, RulesData } from '@/types/panel';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Division {
    id: string;
    name: string;
    category: string | null;
    gender: string;
    gender_label: string;
    entries_count: number;
    stages_count: number;
}

interface Props {
    competition: { id: string; name: string };
    season: { id: string; name: string; year: number; starts_on: string | null; ends_on: string | null; status: string; status_label: string };
    rules: RulesData;
    tiebreaker_labels: Record<string, string>;
    divisions: Division[];
    options: { genders: Option[] };
}

const formatDate = (value: string | null) => (value ? new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR') : '—');

function NewDivisionModal({ seasonId, genders, show, onClose }: { seasonId: string; genders: Option[]; show: boolean; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', category: '', gender: 'male' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('panel.seasons.divisions.store', seasonId), {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} className="space-y-4 p-6">
                <h2 className="text-lg font-semibold text-gray-900">Nova divisão</h2>
                <p className="text-sm text-gray-600">Uma divisão é uma categoria disputada separadamente, por exemplo “Sub-15 masculino” ou “Primeira divisão”.</p>
                <Field label="Nome" htmlFor="division-name" error={errors.name}>
                    <Input id="division-name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus required />
                </Field>
                <Field label="Categoria" htmlFor="division-category" error={errors.category} hint="Opcional. Ex.: Sub-15, Adulto, Veteranos.">
                    <Input id="division-category" value={data.category} onChange={(e) => setData('category', e.target.value)} />
                </Field>
                <Field label="Sexo" htmlFor="division-gender" error={errors.gender}>
                    <Select id="division-gender" value={data.gender} onChange={(e) => setData('gender', e.target.value)}>
                        {genders.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Criar divisão
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Show({ competition, season, rules, tiebreaker_labels, divisions, options }: Props) {
    const [creating, setCreating] = useState(false);

    return (
        <PanelLayout>
            <Head title={`${competition.name} ${season.name}`} />
            <PageHeader
                title={`${competition.name} · ${season.name}`}
                subtitle={
                    <>
                        <Badge tone={season.status === 'active' ? 'green' : season.status === 'finished' ? 'gray' : 'amber'}>{season.status_label}</Badge>{' '}
                        <span className="ml-2">
                            {formatDate(season.starts_on)} a {formatDate(season.ends_on)}
                        </span>
                    </>
                }
                breadcrumbs={[
                    { label: 'Competições', href: route('panel.competitions.index') },
                    { label: competition.name, href: route('panel.competitions.show', competition.id) },
                    { label: season.name },
                ]}
                actions={
                    <>
                        <LinkButton href={route('panel.seasons.edit', season.id)} variant="secondary">
                            Editar edição e regulamento
                        </LinkButton>
                        <DeleteButton
                            href={route('panel.seasons.destroy', season.id)}
                            message={`Excluir a edição "${season.name}" com todas as divisões, fases e jogos? Esta ação não pode ser desfeita.`}
                            small={false}
                        />
                    </>
                }
            />

            <div className="space-y-6">
                <Card title="Divisões" actions={<Button small onClick={() => setCreating(true)}>Nova divisão</Button>}>
                    {divisions.length === 0 ? (
                        <Empty title="Nenhuma divisão cadastrada.">Crie a primeira divisão (categoria) desta edição.</Empty>
                    ) : (
                        <ul className="-my-3 divide-y divide-gray-100">
                            {divisions.map((division) => (
                                <li key={division.id} className="flex items-center justify-between gap-3 py-3">
                                    <div>
                                        <Link href={route('panel.divisions.show', division.id)} className="font-medium text-gray-900 hover:text-emerald-700">
                                            {division.name}
                                        </Link>
                                        <div className="mt-0.5 text-sm text-gray-500">
                                            {[division.category, division.gender_label].filter(Boolean).join(' · ')} · {division.entries_count} equipes ·{' '}
                                            {division.stages_count} {division.stages_count === 1 ? 'fase' : 'fases'}
                                        </div>
                                    </div>
                                    <LinkButton href={route('panel.divisions.show', division.id)} variant="secondary" small>
                                        Abrir
                                    </LinkButton>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="Regulamento da edição">
                    <dl className="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-gray-500">Pontuação</dt>
                            <dd className="font-medium text-gray-900">
                                Vitória {rules.points.win} · Empate {rules.points.draw} · Derrota {rules.points.loss}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Suspensão por cartões</dt>
                            <dd className="font-medium text-gray-900">
                                {rules.cards.yellow_for_suspension} amarelos = {rules.cards.yellow_suspension_games} jogo(s) · vermelho = {rules.cards.red_suspension_games} jogo(s)
                            </dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">W.O.</dt>
                            <dd className="font-medium text-gray-900">
                                {rules.walkover.winner_goals} x {rules.walkover.loser_goals} para o vencedor
                            </dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Substituições</dt>
                            <dd className="font-medium text-gray-900">
                                Até {rules.substitutions.max} em {rules.substitutions.max_windows} atos
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-gray-500">Desempate (em ordem)</dt>
                            <dd className="font-medium text-gray-900">
                                {rules.tiebreakers.map((value, index) => `${index + 1}º ${tiebreaker_labels[value] ?? value}`).join(' → ')}
                            </dd>
                        </div>
                    </dl>
                </Card>
            </div>

            <NewDivisionModal seasonId={season.id} genders={options.genders} show={creating} onClose={() => setCreating(false)} />
        </PanelLayout>
    );
}
