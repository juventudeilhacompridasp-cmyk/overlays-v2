import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';
import { Head } from '@inertiajs/react';

interface Props {
    theme: Theme;
    seo: Seo;
    card: {
        valid: boolean;
        status_label: string;
        name: string | null;
        role_label: string;
        shirt_number: number | null;
        team: string;
        team_logo_url: string | null;
        competition: string;
        season: string;
        division: string;
    };
}

export default function Card({ theme, seo, card }: Props) {
    return (
        <PortalLayout theme={theme} seo={seo}>
            <Head>
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <div className="mx-auto max-w-md rounded-lg bg-white p-6 text-center shadow-sm ring-1 ring-gray-200">
                <div className={`mb-4 rounded-md px-3 py-2 text-sm font-semibold ${card.valid ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'}`}>
                    {card.valid ? '✓ ' : '✕ '}
                    {card.status_label}
                </div>
                {card.team_logo_url && <img src={card.team_logo_url} alt="" className="mx-auto mb-3 h-20 w-20 object-contain" />}
                <h1 className="text-2xl font-extrabold">{card.name}</h1>
                <p className="mt-1 text-gray-600">
                    {card.role_label}
                    {card.shirt_number !== null && ` · camisa ${card.shirt_number}`}
                </p>
                <p className="mt-3 font-medium">{card.team}</p>
                <p className="text-sm text-gray-500">
                    {card.competition} · {card.season} · {card.division}
                </p>
            </div>
        </PortalLayout>
    );
}
