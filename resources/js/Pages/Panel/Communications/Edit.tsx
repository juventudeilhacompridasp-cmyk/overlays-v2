import { Button, Card, Field, Input, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Settings = {
    registration_opened: boolean;
    registration_approved: boolean;
    registration_returned: boolean;
    registration_rejected: boolean;
    manager_invitation: boolean;
    manager_lineup_reminder: boolean;
    manager_game_changed: boolean;
    manager_suspensions: boolean;
    manager_documents: boolean;
    push_started: boolean;
    push_score: boolean;
    push_finished: boolean;
    market_contact_request: boolean;
    market_contact_answered: boolean;
    market_profile_expiring: boolean;
    sheet_read_finished: boolean;
    official_assignment: boolean;
    reminder_first_minutes: number;
    reminder_second_minutes: number;
};

type SwitchKey = Exclude<keyof Settings, 'reminder_first_minutes' | 'reminder_second_minutes'>;

const groups: { title: string; hint: string; feature: 'online_registration' | 'team_portal' | 'push' | 'market' | 'sheet_import' | null; items: { key: SwitchKey; label: string }[] }[] = [
    {
        title: 'Inscrição online (e-mails para a equipe que se inscreve)',
        hint: 'Desligado, o pedido segue normalmente, mas a equipe não recebe o e-mail (o link pessoal continua aparecendo na tela depois de criar o pedido).',
        feature: 'online_registration',
        items: [
            { key: 'registration_opened', label: 'Pedido criado (link pessoal da inscrição)' },
            { key: 'registration_approved', label: 'Inscrição aprovada' },
            { key: 'registration_returned', label: 'Pedido devolvido para ajustes' },
            { key: 'registration_rejected', label: 'Pedido recusado' },
        ],
    },
    {
        title: 'Gestores de equipe',
        hint: 'Os gestores ainda podem desligar os avisos para si mesmos.',
        feature: 'team_portal',
        items: [
            { key: 'manager_invitation', label: 'Convite por e-mail (desligado, o gestor entra por "Esqueci minha senha")' },
            { key: 'manager_lineup_reminder', label: 'Lembrete de escalação pendente' },
            { key: 'manager_game_changed', label: 'Mudança de data/local, adiamento ou cancelamento do jogo' },
            { key: 'manager_suspensions', label: 'Suspensões depois do jogo' },
            { key: 'manager_documents', label: 'Documentos de atletas vencidos ou perto de vencer' },
        ],
    },
    {
        title: 'Avisos push (torcedores que seguem jogos e equipes)',
        hint: 'Escolha em quais momentos do jogo o aviso é enviado. No basquete só há início e fim.',
        feature: 'push',
        items: [
            { key: 'push_started', label: 'Jogo começou' },
            { key: 'push_score', label: 'Gol ou set (placar mudou)' },
            { key: 'push_finished', label: 'Jogo terminou' },
        ],
    },
    {
        title: 'Mercado de jogadores (e-mails para atletas e gestores)',
        hint: 'O e-mail nunca leva o contato do atleta: ele só aparece na área do gestor depois que o atleta aceita o pedido.',
        feature: 'market',
        items: [
            { key: 'market_contact_request', label: 'Atleta recebeu um pedido de contato' },
            { key: 'market_contact_answered', label: 'Gestor recebeu a resposta (aceito ou recusado)' },
            { key: 'market_profile_expiring', label: 'Perfil do atleta vai expirar em 7 dias' },
        ],
    },
    {
        title: 'Súmulas enviadas depois do jogo',
        hint: 'O e-mail só diz que a leitura terminou (ou falhou) e leva à revisão, que exige login; nunca traz o conteúdo lido.',
        feature: 'sheet_import',
        items: [{ key: 'sheet_read_finished', label: 'Leitura da súmula terminou (aviso a quem pediu)' }],
    },
    {
        title: 'Escala de árbitros e mesários',
        hint: 'Árbitros e mesários não têm login: o e-mail com função, jogo, data e local é a única via. Sem e-mail no cadastro, nada é enviado.',
        feature: null,
        items: [{ key: 'official_assignment', label: 'Pessoa escalada para um jogo recebe o aviso' }],
    },
];

function minutesLabel(minutes: number): string {
    if (minutes % 1440 === 0) return `${minutes / 1440} dia(s)`;
    if (minutes % 60 === 0) return `${minutes / 60} h`;
    return `${minutes} min`;
}

function Group({ group, data, setData }: { group: (typeof groups)[number]; data: Settings; setData: <K extends keyof Settings>(key: K, value: Settings[K]) => void }) {
    const features = usePage().props.features;
    const available = group.feature === null || features.includes(group.feature);

    return (
        <Card title={group.title} className="max-w-3xl">
            {!available && <p className="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200">Este recurso não está liberado no pacote da organização; as opções abaixo só valem depois de liberado.</p>}
            <p className="mb-3 text-sm text-gray-600">{group.hint}</p>
            <div className="space-y-2">
                {group.items.map((item) => (
                    <label key={item.key} className="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-emerald-600" checked={data[item.key]} onChange={(e) => setData(item.key, e.target.checked)} />
                        {item.label}
                    </label>
                ))}
            </div>
            {group.feature === 'team_portal' && (
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <Field label="Primeiro lembrete (minutos antes do prazo)" htmlFor="first" hint={`Hoje: ${minutesLabel(data.reminder_first_minutes)} antes do prazo para confirmar a escalação.`}>
                        <Input id="first" type="number" min={30} max={10080} value={data.reminder_first_minutes} onChange={(e) => setData('reminder_first_minutes', Number(e.target.value))} />
                    </Field>
                    <Field label="Segundo lembrete, mais urgente (minutos antes do prazo)" htmlFor="second" hint={`Hoje: ${minutesLabel(data.reminder_second_minutes)} antes do prazo. Precisa ser menor que o primeiro.`}>
                        <Input id="second" type="number" min={15} max={10080} value={data.reminder_second_minutes} onChange={(e) => setData('reminder_second_minutes', Number(e.target.value))} />
                    </Field>
                </div>
            )}
        </Card>
    );
}

export default function Edit({ settings }: { settings: Settings }) {
    const { data, setData, patch, processing, errors } = useForm<Settings>(settings);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('panel.communications.update'), { preserveScroll: true });
    };

    return (
        <PanelLayout>
            <Head title="Comunicações" />
            <PageHeader title="Comunicações" subtitle="Escolha quais e-mails e avisos a organização envia e quando. O padrão é enviar tudo." />
            <form onSubmit={submit} className="space-y-6">
                {groups.map((group) => (
                    <Group key={group.title} group={group} data={data} setData={setData} />
                ))}
                {(errors.reminder_first_minutes || errors.reminder_second_minutes) && <p className="max-w-3xl text-sm text-red-600">{errors.reminder_first_minutes ?? errors.reminder_second_minutes}</p>}
                <Button type="submit" disabled={processing}>
                    Salvar
                </Button>
            </form>
        </PanelLayout>
    );
}
