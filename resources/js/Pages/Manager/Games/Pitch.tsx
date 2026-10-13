export interface FormationSlot {
    key: string;
    label: string;
    abbr: string;
    x: number;
    y: number;
    bench: boolean;
}

export interface Formation {
    code: string;
    label: string;
    slots: FormationSlot[];
}

interface Props {
    sport: string;
    formation: Formation;
    /** vaga => pessoa */
    occupants: Record<string, string>;
    label: (personId: string) => string;
    shirt: (personId: string) => number | null;
    captain: string;
    active: string | null;
    onSelect: (slotKey: string) => void;
}

// Cores fixas (não seguem o tema claro/escuro do painel): é o desenho do campo/quadra.
const surfaces: Record<string, string> = {
    football: 'bg-[#2e7d32]',
    futsal: 'bg-[#1e5fa8]',
    basketball: 'bg-[#c98a3a]',
    volleyball: 'bg-[#e0a458]',
};

/** Campo/quadra desenhado com as vagas da formação; clicar numa vaga a destaca na lista ao lado. */
export default function Pitch({ sport, formation, occupants, label, shirt, captain, active, onSelect }: Props) {
    const landscape = sport === 'basketball';

    return (
        <div
            className={`relative mx-auto mt-2 w-full max-w-sm overflow-hidden rounded-lg ring-1 ring-black/10 ${surfaces[sport] ?? surfaces.football} ${landscape ? 'aspect-[4/3]' : 'aspect-[3/4]'}`}
            role="group"
            aria-label={`Formação ${formation.label}`}
            data-testid="pitch"
        >
            <Markings sport={sport} />
            {formation.slots.map((slot) => {
                const personId = occupants[slot.key];
                const number = personId ? shirt(personId) : null;
                const selected = active === slot.key;

                return (
                    <button
                        key={slot.key}
                        type="button"
                        onClick={() => onSelect(slot.key)}
                        title={personId ? `${slot.label}: ${label(personId)}` : slot.label}
                        style={{ left: `${slot.x}%`, top: `${slot.y}%` }}
                        className="absolute flex w-16 -translate-x-1/2 -translate-y-1/2 flex-col items-center gap-0.5 text-center focus:outline-none"
                    >
                        <span
                            className={`flex h-9 w-9 items-center justify-center rounded-full border-2 text-sm font-bold ${
                                personId ? 'border-[#ffffff] bg-[#111827] text-[#ffffff]' : 'border-dashed border-[#ffffffcc] bg-transparent text-[#ffffffcc]'
                            } ${selected ? 'ring-2 ring-[#fde047]' : ''}`}
                        >
                            {personId ? (number ?? '•') : slot.abbr}
                            {personId && captain === personId && <span className="sr-only"> (capitão)</span>}
                        </span>
                        <span className="max-w-full truncate rounded bg-[#00000080] px-1 text-[10px] leading-4 text-[#ffffff]">
                            {personId ? `${label(personId).split(' ')[0]}${captain === personId ? ' (C)' : ''}` : slot.abbr}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

function Markings({ sport }: { sport: string }) {
    const stroke = 'rgba(255,255,255,0.65)';

    if (sport === 'basketball') {
        return (
            <svg className="absolute inset-0 h-full w-full" viewBox="0 0 100 75" preserveAspectRatio="none" aria-hidden="true">
                <rect x="2" y="2" width="96" height="71" fill="none" stroke={stroke} strokeWidth="0.6" />
                <rect x="34" y="40" width="32" height="33" fill="none" stroke={stroke} strokeWidth="0.6" />
                <circle cx="50" cy="40" r="10" fill="none" stroke={stroke} strokeWidth="0.6" />
                <path d="M10 73 V55 A40 40 0 0 1 90 55 V73" fill="none" stroke={stroke} strokeWidth="0.6" />
            </svg>
        );
    }

    if (sport === 'volleyball') {
        return (
            <svg className="absolute inset-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                <rect x="4" y="4" width="92" height="92" fill="none" stroke={stroke} strokeWidth="0.6" />
                <line x1="4" y1="14" x2="96" y2="14" stroke="rgba(255,255,255,0.9)" strokeWidth="1.2" />
                <line x1="4" y1="40" x2="96" y2="40" stroke={stroke} strokeWidth="0.6" />
            </svg>
        );
    }

    return (
        <svg className="absolute inset-0 h-full w-full" viewBox="0 0 100 133" preserveAspectRatio="none" aria-hidden="true">
            <rect x="3" y="3" width="94" height="127" fill="none" stroke={stroke} strokeWidth="0.6" />
            <line x1="3" y1="66.5" x2="97" y2="66.5" stroke={stroke} strokeWidth="0.6" />
            <circle cx="50" cy="66.5" r="10" fill="none" stroke={stroke} strokeWidth="0.6" />
            <rect x="26" y="3" width="48" height="18" fill="none" stroke={stroke} strokeWidth="0.6" />
            <rect x="26" y="112" width="48" height="18" fill="none" stroke={stroke} strokeWidth="0.6" />
        </svg>
    );
}
