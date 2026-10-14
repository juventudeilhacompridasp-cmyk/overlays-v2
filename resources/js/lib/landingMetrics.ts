/**
 * Medição anônima da landing page (código puro, testado em Node). Só roda com consentimento explícito e nunca com
 * "Do Not Track" ou Global Privacy Control. Envia apenas contagens de uma lista fechada (visita, origem em 4 grupos,
 * aparelho em 2, seção vista e botão clicado): nada de IP, identificador, URL ou referência completa.
 */
export type MetricEvent = { metric: string; dimension: string };
export type Consent = 'yes' | 'no' | null;
export type Source = 'direct' | 'search' | 'social' | 'other';

export const CONSENT_KEY = 'marketing:consent';
export const SESSION_KEY = 'marketing:visit';
export const SECTIONS = ['galeria', 'pacotes', 'faq', 'contato'] as const;

const SEARCH_HOSTS = ['google.', 'bing.', 'duckduckgo.', 'yahoo.', 'ecosia.', 'yandex.', 'baidu.', 'brave.com'];
const SOCIAL_HOSTS = ['facebook.', 'instagram.', 'twitter.', 'linkedin.', 'youtube.', 'whatsapp.', 'tiktok.', 'telegram.', 'threads.', 'pinterest.', 'reddit.'];
const SOCIAL_EXACT = ['t.co', 'x.com', 'wa.me', 't.me', 'lnkd.in', 'youtu.be'];

/** Lê o que foi guardado: só "yes" e "no" valem; qualquer outra coisa é "ainda não perguntado". */
export function readConsent(raw: string | null | undefined): Consent {
    return raw === 'yes' || raw === 'no' ? raw : null;
}

/** Origem da visita em 4 grupos, SEM guardar a referência: vazia/mesmo site = direto; buscadores; redes sociais; o resto. */
export function sourceOf(referrer: string, ownHost: string): Source {
    if (referrer.trim() === '') return 'direct';

    let host: string;

    try {
        host = new URL(referrer).hostname.toLowerCase();
    } catch {
        return 'other';
    }

    const own = ownHost.toLowerCase();

    if (host === own || host.endsWith(`.${own}`)) return 'direct';
    if (SOCIAL_EXACT.includes(host) || SOCIAL_HOSTS.some((part) => host.includes(part))) return 'social';
    if (SEARCH_HOSTS.some((part) => host.includes(part))) return 'search';

    return 'other';
}

export const deviceOf = (width: number): 'mobile' | 'desktop' => (width < 768 ? 'mobile' : 'desktop');

/** A pessoa pediu para não ser medida no navegador (Do Not Track ou Global Privacy Control)? */
export function privacySignal(nav: { doNotTrack?: string | null; globalPrivacyControl?: boolean }): boolean {
    return nav.doNotTrack === '1' || nav.doNotTrack === 'yes' || nav.globalPrivacyControl === true;
}

type Gate = { enabled: boolean; consent: Consent; signal: boolean };

/** Mostrar o aviso pedindo consentimento? Só com a medição ligada, sem sinal de privacidade e sem resposta ainda. */
export const shouldAsk = ({ enabled, consent, signal }: Gate): boolean => enabled && !signal && consent === null;

/** Pode enviar eventos? Só com a medição ligada, consentimento "yes" e sem sinal de privacidade. */
export const mayTrack = ({ enabled, consent, signal }: Gate): boolean => enabled && !signal && consent === 'yes';

/** Eventos da visita (uma vez por sessão da aba): a visita, a origem e o aparelho. */
export function visitEvents(referrer: string, ownHost: string, width: number): MetricEvent[] {
    return [
        { metric: 'visit', dimension: '' },
        { metric: 'source', dimension: sourceOf(referrer, ownHost) },
        { metric: 'device', dimension: deviceOf(width) },
    ];
}

/** Token do cookie XSRF do Laravel (para o `fetch` passar pela verificação de CSRF). */
export function csrfFromCookie(cookie: string): string | null {
    const match = cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));

    if (!match) return null;

    try {
        return decodeURIComponent(match.slice('XSRF-TOKEN='.length));
    } catch {
        return null;
    }
}
