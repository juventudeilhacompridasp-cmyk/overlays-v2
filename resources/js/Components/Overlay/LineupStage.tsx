import { LineupTransition, stageEnterClass, stageExitClass, transitionTiming } from '@/lib/lineupStage';
import { CSSProperties, ReactNode, useEffect, useRef, useState } from 'react';

/**
 * Palco da escalação por equipe: quando a etapa muda (`id`), a que estava na tela sai com a animação de saída enquanto a nova
 * entra, uma sobre a outra, sem piscar. `children` recebe `first` (verdadeiro enquanto ainda é a primeira etapa mostrada), para
 * a etapa usar a própria animação de entrada só na estreia e a troca animada nas demais.
 */
export function LineupStage({ id, transition, motion, speed = 100, children }: { id: string; transition: LineupTransition; motion: 'dynamic' | 'subtle' | 'none'; speed?: number; children: (first: boolean) => ReactNode }) {
    const timing = transitionTiming(transition, motion, speed);
    const [seen, setSeen] = useState(id);
    const [first, setFirst] = useState(true);
    const [leaving, setLeaving] = useState<{ id: string; node: ReactNode } | null>(null);
    const last = useRef<{ id: string; node: ReactNode }>({ id, node: null });

    // Mudou a etapa: a anterior (último quadro desenhado) vira a que está saindo.
    if (seen !== id) {
        setSeen(id);
        setFirst(false);
        setLeaving(timing.exit > 0 ? last.current : null);
    }

    const node = children(first && seen === id);

    useEffect(() => {
        last.current = { id, node };
    });

    useEffect(() => {
        if (!leaving) return;
        const timer = window.setTimeout(() => setLeaving(null), timing.exit + 40);

        return () => window.clearTimeout(timer);
    }, [leaving, timing.exit]);

    const style = { '--lineup-in': `${timing.enter}ms`, '--lineup-out': `${timing.exit}ms` } as CSSProperties;

    return (
        <div className="lineup-stage" style={style} data-testid="lineup-stage" data-stage={id} data-leaving={leaving?.id ?? ''}>
            {leaving && <div key={`out-${leaving.id}`} className={stageExitClass(transition)} aria-hidden="true">{leaving.node}</div>}
            <div key={`in-${id}`} className={first ? undefined : stageEnterClass(transition)}>{node}</div>
        </div>
    );
}
