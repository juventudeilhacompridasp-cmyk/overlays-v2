/** Regras puras da status page (testadas em Node): cores das barras de 90 dias, textos e horários em São Paulo. */
export type ComponentStatus = 'operational' | 'degraded' | 'outage' | 'maintenance';

export const STATUS_TONE: Record<ComponentStatus, { dot: string; text: string; banner: string }> = {
    operational: { dot: 'bg-emerald-500', text: 'text-emerald-700', banner: 'bg-emerald-600' },
    degraded: { dot: 'bg-amber-500', text: 'text-amber-700', banner: 'bg-amber-500' },
    outage: { dot: 'bg-red-500', text: 'text-red-700', banner: 'bg-red-600' },
    maintenance: { dot: 'bg-blue-500', text: 'text-blue-700', banner: 'bg-blue-600' },
};

/** Cor do segmento de um dia: cinza sem medições, verde ≥ 99,9 %, âmbar ≥ 95 %, vermelho abaixo. */
export function dayColor(uptime: number | null): string {
    if (uptime === null) return 'bg-gray-200';
    if (uptime >= 99.9) return 'bg-emerald-500';
    if (uptime >= 95) return 'bg-amber-400';

    return 'bg-red-500';
}

/** "99,95%" (vírgula decimal) ou "—" sem dados. */
export function formatUptime(uptime: number | null): string {
    return uptime === null ? '—' : `${uptime.toFixed(2).replace('.', ',')}%`;
}

/** Texto do tooltip de um dia: "13/10: 99,95%" ou "13/10: sem dados". */
export function dayTitle(date: string, uptime: number | null): string {
    const [, month, day] = date.split('-');

    return `${day}/${month}: ${uptime === null ? 'sem dados' : formatUptime(uptime)}`;
}

/** Data e hora no fuso de São Paulo, igual no servidor e no navegador. */
export function formatWhen(iso: string): string {
    return new Date(iso).toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

/** Duração entre dois instantes ("12 min", "2 h 5 min"); sem fim, "em andamento". */
export function formatDuration(startIso: string, endIso: string | null): string {
    if (endIso === null) return 'em andamento';

    const minutes = Math.max(0, Math.round((new Date(endIso).getTime() - new Date(startIso).getTime()) / 60000));

    if (minutes < 60) return `${minutes} min`;

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}
