import { Seo, SeoHead } from '@/Components/Portal/PortalLayout';
import { CONSENT_KEY, csrfFromCookie, mayTrack, MetricEvent, privacySignal, readConsent, SECTIONS, SESSION_KEY, shouldAsk, visitEvents } from '@/lib/landingMetrics';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent, ReactNode, useEffect, useRef, useState } from 'react';

interface Plan {
    key: string;
    label: string;
    features: string[];
}

interface MatrixRow {
    label: string;
    plans: Record<string, boolean>;
    on_demand: boolean;
}

interface GalleryItem {
    src: string;
    title: string;
    text: string;
    alt: string;
}

interface Content {
    hero: { title: string; subtitle: string };
    gallery: GalleryItem[];
    pillars: { title: string; text: string }[];
    overlays: string[];
    sports: string[];
    plans: Plan[];
    matrix: MatrixRow[];
    privacy: string[];
    faq: { q: string; a: string }[];
    interests: { value: string; label: string }[];
}

interface Props {
    content: Content;
    brand: string;
    analytics: boolean;
    links: { privacy: string; terms: string; status: string; login: string };
    seo: Seo;
}

const Check = () => (
    <svg className="mx-auto h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-label="Incluído" role="img">
        <path fillRule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clipRule="evenodd" />
    </svg>
);

/** Maquete do placar do OBS, feita só com HTML/CSS (nenhuma imagem a baixar). */
function ScoreboardMock() {
    return (
        <div className="overflow-hidden rounded-xl bg-neutral-900/90 shadow-2xl ring-1 ring-white/10" aria-hidden="true">
            <div className="flex items-stretch text-white">
                <div className="flex min-w-0 flex-1 items-center gap-3 px-3 py-3 sm:px-4">
                    <span className="grid h-9 w-9 place-items-center rounded-full bg-teal-600 text-xs font-black">LEO</span>
                    <span className="min-w-0 truncate text-sm font-bold uppercase tracking-wide">Leões da Ilha</span>
                </div>
                <div className="flex shrink-0 items-center gap-2 bg-black/40 px-3 text-2xl font-black tabular-nums sm:gap-3 sm:px-5 sm:text-3xl">
                    <span>2</span>
                    <span className="text-white/30">×</span>
                    <span>1</span>
                </div>
                <div className="flex min-w-0 flex-1 items-center justify-end gap-3 px-3 py-3 sm:px-4">
                    <span className="min-w-0 truncate text-sm font-bold uppercase tracking-wide">Tigres FC</span>
                    <span className="grid h-9 w-9 place-items-center rounded-full bg-orange-600 text-xs font-black">TIG</span>
                </div>
            </div>
            <div className="flex items-center justify-between bg-emerald-600 px-4 py-1 text-xs font-bold uppercase tracking-widest text-white">
                <span>Copa Ilha</span>
                <span className="tabular-nums">67:12</span>
            </div>
        </div>
    );
}

/** Maquete da faixa de lance (gol). */
function LowerThirdMock() {
    return (
        <div className="flex items-stretch overflow-hidden rounded-lg bg-neutral-900/90 text-white shadow-xl ring-1 ring-white/10" aria-hidden="true">
            <span className="grid w-14 place-items-center bg-emerald-600 text-2xl font-black">9</span>
            <div className="px-4 py-2">
                <p className="text-[10px] font-bold uppercase tracking-widest text-emerald-400">Gol · 67&apos;</p>
                <p className="text-lg font-black uppercase leading-tight">Rafael Souza</p>
                <p className="text-xs text-white/60">Leões da Ilha 2 × 1 Tigres FC</p>
            </div>
        </div>
    );
}

/** Maquete do Modo operador: blocos de uma grade. */
function OperatorMock() {
    const tile = (label: string, on: boolean) => (
        <span key={label} className={`rounded px-2 py-1 text-[10px] font-bold ${on ? 'bg-emerald-600 text-white' : 'bg-white/10 text-white/70'}`}>
            {label}
        </span>
    );

    return (
        <div className="grid grid-cols-6 grid-rows-4 gap-1.5 rounded-xl bg-neutral-800 p-2 shadow-2xl ring-1 ring-white/10" style={{ height: 220 }} aria-hidden="true">
            <div className="col-span-2 row-span-2 flex flex-wrap content-start gap-1 rounded-lg bg-neutral-900 p-2">
                <p className="w-full text-[9px] font-bold uppercase tracking-wide text-white/40">No ar agora</p>
                {tile('Placar', true)}
                {tile('Lances', true)}
                {tile('Escalação', false)}
                {tile('Tabela', false)}
            </div>
            <div className="col-span-4 row-span-2 rounded-lg bg-neutral-900 p-2">
                <p className="text-[9px] font-bold uppercase tracking-wide text-white/40">Escalação</p>
                <div className="mt-2 grid grid-cols-3 gap-1.5">
                    <span className="rounded bg-white/10 py-2 text-center text-[10px] text-white/70">← Anterior</span>
                    <span className="rounded bg-teal-600 py-2 text-center text-[10px] font-bold text-white">Próximo →</span>
                    <span className="rounded bg-white/10 py-2 text-center text-[10px] text-white/70">↺ Início</span>
                </div>
            </div>
            <div className="col-span-3 row-span-2 rounded-lg bg-neutral-900 p-2">
                <p className="text-[9px] font-bold uppercase tracking-wide text-white/40">Estatísticas</p>
                <div className="mt-2 space-y-1.5">
                    {[70, 45, 60].map((width) => (
                        <div key={width} className="h-2 rounded bg-white/10">
                            <div className="h-2 rounded bg-emerald-500" style={{ width: `${width}%` }} />
                        </div>
                    ))}
                </div>
            </div>
            <div className="col-span-3 row-span-2 rounded-lg bg-neutral-900 p-2">
                <p className="text-[9px] font-bold uppercase tracking-wide text-white/40">Avisos</p>
                <div className="mt-2 flex flex-wrap gap-1">
                    {['VAR', 'PÊNALTI', 'INTERVALO'].map((label) => (
                        <span key={label} className="rounded bg-amber-400/20 px-2 py-1 text-[10px] font-bold text-amber-300">
                            {label}
                        </span>
                    ))}
                </div>
            </div>
        </div>
    );
}

/** Galeria de prints reais (dados fictícios). Clicar abre a imagem grande numa janela; Esc, clique fora ou o botão fecham. */
function Gallery({ items }: { items: GalleryItem[] }) {
    const [open, setOpen] = useState<number | null>(null);
    const closeRef = useRef<HTMLButtonElement>(null);
    const opener = useRef<HTMLElement | null>(null);

    useEffect(() => {
        if (open === null) return;

        closeRef.current?.focus();
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setOpen(null);
            else if (event.key === 'ArrowRight') setOpen((current) => (current === null ? null : (current + 1) % items.length));
            else if (event.key === 'ArrowLeft') setOpen((current) => (current === null ? null : (current + items.length - 1) % items.length));
        };
        window.addEventListener('keydown', onKey);

        return () => {
            window.removeEventListener('keydown', onKey);
            opener.current?.focus();
        };
    }, [open, items.length]);

    const current = open === null ? null : items[open];

    return (
        <>
            <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {items.map((item, index) => (
                    <li key={item.src}>
                        <figure className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <button type="button" onClick={(event) => { opener.current = event.currentTarget; setOpen(index); }} className="block w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" aria-label={`Ampliar: ${item.title}`}>
                                <img src={item.src} alt={item.alt} width={1280} height={720} loading="lazy" decoding="async" className="aspect-video w-full object-cover" />
                            </button>
                            <figcaption className="p-4">
                                <p className="font-bold text-gray-900">{item.title}</p>
                                <p className="mt-1 text-sm text-gray-600">{item.text}</p>
                            </figcaption>
                        </figure>
                    </li>
                ))}
            </ul>
            {current && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4" role="dialog" aria-modal="true" aria-label={current.title} onClick={() => setOpen(null)}>
                    <figure className="max-h-full w-full max-w-5xl" onClick={(event) => event.stopPropagation()}>
                        <img src={current.src} alt={current.alt} className="max-h-[80vh] w-full rounded-lg object-contain" />
                        <figcaption className="mt-3 text-center text-sm text-white/85">
                            <strong>{current.title}</strong> · {current.text}
                        </figcaption>
                    </figure>
                    <button ref={closeRef} type="button" onClick={() => setOpen(null)} className="absolute right-4 top-4 rounded-md bg-white/15 px-3 py-1.5 text-sm font-bold text-white hover:bg-white/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                        Fechar ✕
                    </button>
                </div>
            )}
        </>
    );
}

/** Preferências guardadas só neste navegador; sem armazenamento a pergunta volta a cada visita. */
const store = {
    get: (area: 'local' | 'session', key: string): string | null => {
        try {
            return (area === 'local' ? window.localStorage : window.sessionStorage).getItem(key);
        } catch {
            return null;
        }
    },
    set: (area: 'local' | 'session', key: string, value: string) => {
        try {
            (area === 'local' ? window.localStorage : window.sessionStorage).setItem(key, value);
        } catch {
            /* sem armazenamento: vale só nesta visita */
        }
    },
};

/**
 * Medição anônima de acessos: só pergunta se a medição está ligada e não há sinal de privacidade (Do Not Track / GPC), e só
 * envia contagens (visita, origem, aparelho, seção vista, botão clicado) depois do "Permitir". Sem cookies de rastreio, IP ou
 * identificador. O rodapé reabre a pergunta para mudar de ideia.
 */
function Measurement({ enabled, privacy }: { enabled: boolean; privacy: string }) {
    const [ready, setReady] = useState(false);
    const [consent, setConsent] = useState<'yes' | 'no' | null>(null);
    const [signal, setSignal] = useState(false);
    const gate = { enabled, consent, signal };
    const seen = useRef(new Set<string>());

    useEffect(() => {
        const nav = navigator as Navigator & { globalPrivacyControl?: boolean };
        setSignal(privacySignal({ doNotTrack: nav.doNotTrack, globalPrivacyControl: nav.globalPrivacyControl }));
        setConsent(readConsent(store.get('local', CONSENT_KEY)));
        setReady(true);

        const reopen = () => setConsent(null);
        window.addEventListener('marketing:consent-reopen', reopen);

        return () => window.removeEventListener('marketing:consent-reopen', reopen);
    }, []);

    const send = (events: MetricEvent[]) => {
        const token = csrfFromCookie(document.cookie);

        fetch(route('marketing.metrics'), {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(token ? { 'X-XSRF-TOKEN': token } : {}) },
            body: JSON.stringify({ events }),
        }).catch(() => undefined);
    };

    // Com consentimento: a visita (uma vez por sessão da aba), cada seção vista e os cliques nos botões.
    useEffect(() => {
        if (!ready || !mayTrack(gate)) return;

        if (store.get('session', SESSION_KEY) === null) {
            store.set('session', SESSION_KEY, '1');
            send(visitEvents(document.referrer, window.location.hostname, window.innerWidth));
        }

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    const id = entry.target.id;

                    if (entry.isIntersecting && !seen.current.has(id)) {
                        seen.current.add(id);
                        send([{ metric: 'section', dimension: id }]);
                    }
                }
            },
            { threshold: 0.4 },
        );
        SECTIONS.forEach((id) => {
            const element = document.getElementById(id);

            if (element) observer.observe(element);
        });

        const onClick = (event: MouseEvent) => {
            const target = (event.target as Element | null)?.closest<HTMLElement>('[data-cta]');

            if (target?.dataset.cta) send([{ metric: 'cta', dimension: target.dataset.cta }]);
        };
        document.addEventListener('click', onClick);

        return () => {
            observer.disconnect();
            document.removeEventListener('click', onClick);
        };
    }, [ready, consent, enabled, signal]);

    const answer = (value: 'yes' | 'no') => {
        store.set('local', CONSENT_KEY, value);
        setConsent(value);
    };

    if (!ready || !shouldAsk(gate)) return null;

    return (
        <section role="region" aria-label="Medição de acessos" className="fixed inset-x-3 bottom-3 z-40 mx-auto max-w-2xl rounded-xl bg-neutral-900 p-4 text-sm text-white shadow-2xl ring-1 ring-white/10">
            <p className="leading-relaxed text-white/90">
                Podemos medir os acessos desta página? É uma <strong>contagem anônima</strong>: sem cookies de rastreio, sem IP e sem identificar você. Ela nos mostra quais partes ajudam. Veja a{' '}
                <a href={privacy} className="font-semibold text-emerald-300 underline">
                    política de privacidade
                </a>
                . Você pode mudar de ideia no rodapé.
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
                <button type="button" onClick={() => answer('yes')} className="rounded-md bg-emerald-500 px-4 py-2 font-bold text-neutral-950 hover:bg-emerald-400">
                    Permitir
                </button>
                <button type="button" onClick={() => answer('no')} className="rounded-md border border-white/25 px-4 py-2 font-bold text-white hover:bg-white/10">
                    Agora não
                </button>
            </div>
        </section>
    );
}

function Section({ id, title, subtitle, children, tone = 'light' }: { id?: string; title: string; subtitle?: string; children: ReactNode; tone?: 'light' | 'muted' }) {
    return (
        <section id={id} className={tone === 'muted' ? 'bg-gray-100 py-16' : 'py-16'}>
            <div className="mx-auto max-w-6xl px-4">
                <h2 className="text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl">{title}</h2>
                {subtitle && <p className="mt-2 max-w-2xl text-gray-600">{subtitle}</p>}
                <div className="mt-8">{children}</div>
            </div>
        </section>
    );
}

function ContactForm({ interests, privacy }: { interests: Content['interests']; privacy: string }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', email: '', organization_name: '', interest: 'both', message: '', consent: false, website: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('marketing.contact'), { preserveScroll: true, onSuccess: () => reset() });
    };
    const field = 'mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600';

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200" noValidate>
            {flash?.success && (
                <p role="status" className="rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
                    {flash.success}
                </p>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
                <label className="text-sm font-medium text-gray-700">
                    Seu nome
                    <input className={field} value={data.name} onChange={(event) => setData('name', event.target.value)} maxLength={120} autoComplete="name" required />
                    {errors.name && <span className="mt-1 block text-sm text-red-600">{errors.name}</span>}
                </label>
                <label className="text-sm font-medium text-gray-700">
                    E-mail
                    <input type="email" className={field} value={data.email} onChange={(event) => setData('email', event.target.value)} maxLength={160} autoComplete="email" required />
                    {errors.email && <span className="mt-1 block text-sm text-red-600">{errors.email}</span>}
                </label>
                <label className="text-sm font-medium text-gray-700">
                    Organização ou liga (opcional)
                    <input className={field} value={data.organization_name} onChange={(event) => setData('organization_name', event.target.value)} maxLength={120} />
                </label>
                <label className="text-sm font-medium text-gray-700">
                    Assunto
                    <select className={field} value={data.interest} onChange={(event) => setData('interest', event.target.value)}>
                        {interests.map((interest) => (
                            <option key={interest.value} value={interest.value}>
                                {interest.label}
                            </option>
                        ))}
                    </select>
                </label>
            </div>
            <label className="text-sm font-medium text-gray-700">
                Mensagem
                <textarea className={field} rows={4} value={data.message} onChange={(event) => setData('message', event.target.value)} maxLength={2000} required />
                {errors.message && <span className="mt-1 block text-sm text-red-600">{errors.message}</span>}
            </label>
            {/* Armadilha para robôs: escondido de pessoas e de leitores de tela. */}
            <div className="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>
                    Não preencha este campo
                    <input tabIndex={-1} autoComplete="off" value={data.website} onChange={(event) => setData('website', event.target.value)} />
                </label>
            </div>
            <label className="flex items-start gap-2 text-sm text-gray-600">
                <input type="checkbox" className="mt-1" checked={data.consent} onChange={(event) => setData('consent', event.target.checked)} />
                <span>
                    Li e aceito a{' '}
                    <a href={privacy} className="font-medium text-emerald-700 underline">
                        política de privacidade
                    </a>
                    . Usaremos seus dados só para responder a esta mensagem.
                </span>
            </label>
            {errors.consent && <p className="-mt-2 text-sm text-red-600">{errors.consent}</p>}
            {errors.website && <p className="-mt-2 text-sm text-red-600">{errors.website}</p>}
            <div>
                <button type="submit" data-cta="contato" disabled={processing} className="rounded-md bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60">
                    {processing ? 'Enviando…' : 'Enviar mensagem'}
                </button>
            </div>
        </form>
    );
}

export default function Landing({ content, brand, analytics, links, seo }: Props) {
    const nav = [
        { href: '#recursos', label: 'Recursos' },
        { href: '#galeria', label: 'Em ação' },
        { href: '#overlays', label: 'Overlays' },
        { href: '#pacotes', label: 'Pacotes' },
        { href: '#faq', label: 'Dúvidas' },
    ];

    return (
        <div className="bg-white text-gray-900">
            <SeoHead seo={seo} />
            <Measurement enabled={analytics} privacy={links.privacy} />
            <a href="#conteudo" className="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">
                Pular para o conteúdo
            </a>

            <header className="sticky top-0 z-40 border-b border-white/10 bg-neutral-950/95 text-white backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                    <a href="#topo" className="min-w-0 truncate text-lg font-extrabold tracking-tight">
                        {brand}
                    </a>
                    <nav className="hidden items-center gap-6 text-sm font-medium text-white/80 md:flex" aria-label="Seções">
                        {nav.map((item) => (
                            <a key={item.href} href={item.href} className="hover:text-white">
                                {item.label}
                            </a>
                        ))}
                    </nav>
                    <div className="flex shrink-0 items-center gap-3 text-sm font-semibold">
                        <a href={links.login} className="text-white/80 hover:text-white">
                            Entrar
                        </a>
                        <a href="#contato" data-cta="menu" className="rounded-md bg-emerald-500 px-3 py-1.5 text-neutral-950 hover:bg-emerald-400">
                            <span className="hidden sm:inline">Falar com a equipe</span><span className="sm:hidden">Contato</span>
                        </a>
                    </div>
                </div>
            </header>

            <main id="conteudo">
                <section id="topo" className="overflow-x-hidden bg-gradient-to-b from-neutral-950 via-neutral-900 to-neutral-800 text-white">
                    <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 lg:grid-cols-2 lg:py-24">
                        <div className="min-w-0">
                            <p className="mb-3 inline-block rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-bold uppercase tracking-widest text-emerald-300">Gestão de competições + OBS</p>
                            <h1 className="break-words text-3xl font-black leading-tight tracking-tight sm:text-5xl">{content.hero.title}</h1>
                            <p className="mt-4 max-w-xl text-lg text-white/75">{content.hero.subtitle}</p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <a href="#contato" data-cta="hero" className="rounded-md bg-emerald-500 px-5 py-3 font-bold text-neutral-950 hover:bg-emerald-400">
                                    Quero conhecer
                                </a>
                                <a href="#overlays" className="rounded-md border border-white/25 px-5 py-3 font-bold text-white hover:bg-white/10">
                                    Ver os overlays
                                </a>
                            </div>
                        </div>
                        <div className="min-w-0 space-y-4">
                            <ScoreboardMock />
                            <div className="max-w-sm">
                                <LowerThirdMock />
                            </div>
                        </div>
                    </div>
                </section>

                <Section id="recursos" title="Tudo o que um campeonato precisa" subtitle="Do primeiro cadastro à final transmitida: uma plataforma só, com a sua marca.">
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {content.pillars.map((pillar) => (
                            <article key={pillar.title} className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                                <h3 className="font-bold text-gray-900">{pillar.title}</h3>
                                <p className="mt-2 text-sm leading-relaxed text-gray-600">{pillar.text}</p>
                            </article>
                        ))}
                    </div>
                </Section>

                <Section id="galeria" tone="muted" title="O produto em ação" subtitle="Telas reais da plataforma, com dados fictícios. Clique para ampliar.">
                    <Gallery items={content.gallery} />
                </Section>

                <section id="overlays" className="bg-neutral-900 py-16 text-white">
                    <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 lg:grid-cols-2">
                        <div>
                            <h2 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Overlays prontos para o OBS</h2>
                            <p className="mt-2 text-white/70">Cada tela tem uma URL fixa: adicione como fonte de navegador e opere pelo painel, por atalhos ou por API.</p>
                            <ul className="mt-6 grid gap-2 text-sm sm:grid-cols-2">
                                {content.overlays.map((item) => (
                                    <li key={item} className="flex items-start gap-2 text-white/85">
                                        <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400" aria-hidden="true" />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <div>
                            <p className="mb-2 text-xs font-bold uppercase tracking-widest text-white/50">Modo operador</p>
                            <OperatorMock />
                            <p className="mt-3 text-sm text-white/60">Tela única, sem rolagem, com blocos que você move e redimensiona. Tudo o que vai ao ar a um clique.</p>
                        </div>
                    </div>
                </section>

                <Section title="Vários esportes, cada um com as suas regras" subtitle="Pontuação, relógio, faltas e substituições seguem o esporte, e o regulamento de cada edição é parametrizável.">
                    <ul className="flex flex-wrap gap-3">
                        {content.sports.map((sport) => (
                            <li key={sport} className="rounded-full bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-800 ring-1 ring-emerald-200">
                                {sport}
                            </li>
                        ))}
                    </ul>
                </Section>

                <Section id="pacotes" tone="muted" title="Pacotes" subtitle="Escolha o que a sua organização precisa. Valores e condições são combinados diretamente.">
                    <div className="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                        <table className="w-full min-w-[34rem] text-sm">
                            <thead>
                                <tr className="border-b border-gray-200 text-left">
                                    <th scope="col" className="px-4 py-3 font-bold text-gray-900">Recurso</th>
                                    {content.plans.map((plan) => (
                                        <th key={plan.key} scope="col" className="px-4 py-3 text-center font-bold text-gray-900">
                                            {plan.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {content.matrix.map((row) => (
                                    <tr key={row.label}>
                                        <th scope="row" className="px-4 py-3 text-left font-medium text-gray-700">
                                            {row.label}
                                            {row.on_demand && <span className="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-800">sob demanda</span>}
                                        </th>
                                        {content.plans.map((plan) => (
                                            <td key={plan.key} className="px-4 py-3 text-center">
                                                {row.plans[plan.key] ? <Check /> : <span className="text-gray-300" aria-label="Não incluído">—</span>}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="mt-3 text-sm text-gray-600">Recursos “sob demanda” ficam fora dos pacotes e são liberados caso a caso, a pedido da organização.</p>
                </Section>

                <Section title="Feita para a LGPD" subtitle="Atletas, muitos deles menores, merecem dados protegidos desde o desenho.">
                    <ul className="grid gap-4 md:grid-cols-2">
                        {content.privacy.map((item) => (
                            <li key={item} className="rounded-lg border border-gray-200 p-4 text-sm leading-relaxed text-gray-700">
                                {item}
                            </li>
                        ))}
                    </ul>
                </Section>

                <Section id="faq" tone="muted" title="Dúvidas frequentes">
                    <div className="max-w-3xl divide-y divide-gray-200 rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                        {content.faq.map((item) => (
                            <details key={item.q} className="group p-4">
                                <summary className="cursor-pointer list-none font-semibold text-gray-900">
                                    <span className="mr-2 text-emerald-600 group-open:hidden" aria-hidden="true">+</span>
                                    <span className="mr-2 hidden text-emerald-600 group-open:inline" aria-hidden="true">−</span>
                                    {item.q}
                                </summary>
                                <p className="mt-2 pl-5 text-sm leading-relaxed text-gray-600">{item.a}</p>
                            </details>
                        ))}
                    </div>
                </Section>

                <Section id="contato" title="Vamos conversar?" subtitle="Conte um pouco sobre o seu campeonato ou a sua transmissão e respondemos pelo e-mail informado.">
                    <div className="max-w-3xl">
                        <ContactForm interests={content.interests} privacy={links.privacy} />
                    </div>
                </Section>
            </main>

            <footer className="bg-neutral-950 py-8 text-sm text-white/60">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4">
                    <p>© {new Date().getFullYear()} {brand}</p>
                    <nav className="flex gap-5" aria-label="Rodapé">
                        <a href={links.terms} className="hover:text-white">Termos de uso</a>
                        <a href={links.privacy} className="hover:text-white">Privacidade</a>
                        <a href={links.status} className="hover:text-white">Status</a>
                        {analytics && (
                            <button type="button" onClick={() => window.dispatchEvent(new Event('marketing:consent-reopen'))} className="hover:text-white">
                                Preferências de medição
                            </button>
                        )}
                    </nav>
                </div>
            </footer>
        </div>
    );
}
