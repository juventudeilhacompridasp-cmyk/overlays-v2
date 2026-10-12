import { Button, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, router } from '@inertiajs/react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

interface CardData {
    id: string;
    code: string;
    verify_url: string;
    name: string;
    nickname: string | null;
    birth_date: string | null;
    role_label: string;
    is_athlete: boolean;
    shirt_number: number | null;
    photo_url: string | null;
    team: string;
    team_logo_url: string | null;
    competition: string;
    season: string;
    division: string;
}

interface Props {
    cards: CardData[];
    entry: { id: string; team: string };
    back_url: string;
}

function Qr({ value }: { value: string }) {
    const [src, setSrc] = useState<string | null>(null);

    useEffect(() => {
        let active = true;
        QRCode.toDataURL(value, { margin: 1, width: 240, errorCorrectionLevel: 'M' }).then((url) => active && setSrc(url));

        return () => {
            active = false;
        };
    }, [value]);

    return src ? <img src={src} alt={`QR da carteirinha ${value}`} className="h-24 w-24" /> : <div className="h-24 w-24" />;
}

/** Carteirinha com cores fixas (não segue o tema escuro): é feita para imprimir. */
function Card({ card }: { card: CardData }) {
    return (
        <article className="break-inside-avoid overflow-hidden rounded-lg bg-[#ffffff] text-neutral-900 shadow ring-1 ring-neutral-300 print:shadow-none" style={{ width: '9.5cm' }}>
            <header className="flex items-center gap-2 bg-emerald-700 px-3 py-1.5 text-white">
                {card.team_logo_url && <img src={card.team_logo_url} alt="" className="h-7 w-7 rounded bg-[#ffffff] object-contain p-0.5" />}
                <div className="min-w-0 leading-tight">
                    <div className="truncate text-sm font-bold">{card.team}</div>
                    <div className="truncate text-[10px] opacity-90">
                        {card.competition} · {card.season} · {card.division}
                    </div>
                </div>
            </header>
            <div className="flex gap-3 p-3">
                <div className="flex w-24 shrink-0 flex-col items-center gap-1">
                    {card.photo_url ? (
                        <img src={card.photo_url} alt="" className="h-28 w-24 rounded object-cover ring-1 ring-neutral-300" />
                    ) : (
                        <div className="flex h-28 w-24 items-center justify-center rounded bg-neutral-100 text-[10px] text-neutral-500 ring-1 ring-neutral-300">sem foto</div>
                    )}
                    <Qr value={card.verify_url} />
                </div>
                <div className="min-w-0 flex-1">
                    <div className="text-[10px] uppercase tracking-wide text-neutral-500">{card.role_label}</div>
                    <div className="text-base font-extrabold leading-tight">{card.name}</div>
                    {card.nickname && <div className="text-sm text-neutral-600">“{card.nickname}”</div>}
                    {card.birth_date && <div className="mt-2 text-xs text-neutral-600">Nascimento: {card.birth_date}</div>}
                    {card.shirt_number !== null && <div className="mt-2 text-4xl font-black text-emerald-700">#{card.shirt_number}</div>}
                    <div className="mt-2 text-[10px] text-neutral-500">
                        Verifique pelo QR ou em
                        <br />
                        <span className="break-all font-mono text-neutral-700">{card.verify_url.replace(/^https?:\/\//, '')}</span>
                    </div>
                </div>
            </div>
        </article>
    );
}

export default function Show({ cards, entry, back_url }: Props) {
    return (
        <PanelLayout>
            <Head title={`Carteirinhas · ${entry.team}`} />
            <div className="print:hidden">
                <PageHeader
                    title={cards.length === 1 ? `Carteirinha de ${cards[0].name}` : `Carteirinhas de ${entry.team}`}
                    subtitle="O QR abre a página pública de verificação (nome público, equipe, função e situação). Imprima em A4 e recorte."
                    actions={
                        <>
                            <LinkButton href={back_url} variant="secondary">
                                Voltar ao elenco
                            </LinkButton>
                            {cards.length === 1 && (
                                <Button
                                    variant="danger"
                                    onClick={() => window.confirm('Emitir um novo código? O QR desta carteirinha deixa de valer.') && router.post(route('panel.roster.card.regenerate', cards[0].id), {}, { preserveScroll: true })}
                                >
                                    Renovar código
                                </Button>
                            )}
                            <Button onClick={() => window.print()}>Imprimir</Button>
                        </>
                    }
                />
            </div>
            {cards.length === 0 ? (
                <p className="text-sm text-gray-500">Nenhum integrante ativo no elenco.</p>
            ) : (
                <div className="flex flex-wrap gap-4 print:gap-2">
                    {cards.map((card) => (
                        <Card key={card.id} card={card} />
                    ))}
                </div>
            )}
        </PanelLayout>
    );
}
