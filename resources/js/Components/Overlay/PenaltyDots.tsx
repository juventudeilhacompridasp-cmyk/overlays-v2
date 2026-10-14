import { Dot } from '@/lib/shootout';

const STYLE: Record<Dot, { background: string; border: string; mark: string }> = {
    goal: { background: '#16a34a', border: '#bbf7d0', mark: '✓' },
    miss: { background: '#b91c1c', border: '#fecaca', mark: '✕' },
    pending: { background: 'transparent', border: 'rgba(255,255,255,.35)', mark: '' },
};

/** Fileira de bolinhas das cobranças de uma equipe: verde convertida, vermelha perdida, vazia ainda por cobrar. Só transform/opacity quando anima. */
export function PenaltyDots({ dots, size = 28, align = 'left', label }: { dots: Dot[]; size?: number; align?: 'left' | 'right'; label: string }) {
    return (
        <span className={`inline-flex items-center gap-1.5 ${align === 'right' ? 'flex-row-reverse' : ''}`} role="img" aria-label={label} data-testid="penalty-dots">
            {dots.map((dot, index) => (
                <span key={index} data-dot={dot} className="grid place-items-center rounded-full border-2 font-black leading-none text-white" style={{ width: size, height: size, fontSize: size * 0.5, background: STYLE[dot].background, borderColor: STYLE[dot].border }}>{STYLE[dot].mark}</span>
            ))}
        </span>
    );
}
