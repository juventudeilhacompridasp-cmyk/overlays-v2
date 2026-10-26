import { Link, router, usePage } from '@inertiajs/react';
import {
    ButtonHTMLAttributes,
    InputHTMLAttributes,
    PropsWithChildren,
    ReactNode,
    SelectHTMLAttributes,
    TextareaHTMLAttributes,
    forwardRef,
    useEffect,
    useRef,
    useState,
} from 'react';
import SearchSelect from '@/Components/SearchSelect';
import { PaginationLink } from '@/types/panel';

const inputBase =
    'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100';

export function PageHeader({
    title,
    subtitle,
    actions,
    breadcrumbs,
}: {
    title: string;
    subtitle?: ReactNode;
    actions?: ReactNode;
    breadcrumbs?: { label: string; href?: string }[];
}) {
    return (
        <div className="mb-6">
            {breadcrumbs && breadcrumbs.length > 0 && (
                <nav className="mb-2 flex flex-wrap items-center gap-1 text-sm text-gray-500">
                    {breadcrumbs.map((crumb, index) => (
                        <span key={index} className="flex items-center gap-1">
                            {index > 0 && <span>/</span>}
                            {crumb.href ? (
                                <Link href={crumb.href} className="hover:text-emerald-700 hover:underline">
                                    {crumb.label}
                                </Link>
                            ) : (
                                <span className="text-gray-700">{crumb.label}</span>
                            )}
                        </span>
                    ))}
                </nav>
            )}
            <div className="flex flex-col items-stretch justify-between gap-3 sm:flex-row sm:items-start">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
                    {subtitle && <p className="mt-1 text-sm text-gray-600">{subtitle}</p>}
                </div>
                {actions && <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">{actions}</div>}
            </div>
        </div>
    );
}

export function Card({
    title,
    actions,
    children,
    className = '',
}: PropsWithChildren<{ title?: string; actions?: ReactNode; className?: string }>) {
    return (
        <section className={`rounded-lg bg-white shadow-sm ring-1 ring-gray-200 ${className}`}>
            {(title || actions) && (
                <header className="flex flex-col items-stretch justify-between gap-2 border-b border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:px-5">
                    <h2 className="text-base font-semibold text-gray-900">{title}</h2>
                    {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                </header>
            )}
            <div className="p-5">{children}</div>
        </section>
    );
}

export function Field({
    label,
    error,
    hint,
    children,
    htmlFor,
}: PropsWithChildren<{ label: string; error?: string; hint?: string; htmlFor?: string }>) {
    return (
        <div>
            <label htmlFor={htmlFor} className="mb-1 block text-sm font-medium text-gray-700">
                {label}
            </label>
            {children}
            {hint && !error && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input(props, ref) {
    return <input {...props} ref={ref} className={`${inputBase} ${props.className ?? ''}`} />;
});

/** Select pesquisável padrão (busca a partir de 2 letras); mesma API do `<select>`. Ver `SearchSelect`. */
export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
    return <SearchSelect {...props} />;
}

export function Textarea(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <textarea {...props} className={`${inputBase} ${props.className ?? ''}`} />;
}

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';

const variants: Record<Variant, string> = {
    primary: 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500',
    secondary: 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus:ring-emerald-500',
    danger: 'bg-white text-red-600 ring-1 ring-red-200 hover:bg-red-50 focus:ring-red-500',
    ghost: 'text-gray-600 hover:bg-gray-100 focus:ring-gray-400',
};

const buttonClasses = (variant: Variant, small: boolean) =>
    `inline-flex items-center justify-center rounded-md font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 ${
        small ? 'min-h-11 px-2.5 py-1 text-xs sm:min-h-0' : 'min-h-11 px-4 py-2 text-sm sm:min-h-0'
    } ${variants[variant]}`;

export function Button({
    variant = 'primary',
    small = false,
    className = '',
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant; small?: boolean }) {
    return <button type="button" {...props} className={`${buttonClasses(variant, small)} ${className}`} />;
}

export function LinkButton({
    href,
    variant = 'primary',
    small = false,
    children,
}: PropsWithChildren<{ href: string; variant?: Variant; small?: boolean }>) {
    return (
        <Link href={href} className={buttonClasses(variant, small)}>
            {children}
        </Link>
    );
}

export function DeleteButton({
    href,
    message,
    label = 'Excluir',
    small = true,
}: {
    href: string;
    message: string;
    label?: string;
    small?: boolean;
}) {
    return (
        <Button
            variant="danger"
            small={small}
            onClick={() => {
                if (window.confirm(message)) {
                    router.delete(href, { preserveScroll: true });
                }
            }}
        >
            {label}
        </Button>
    );
}

export function Badge({
    children,
    tone = 'gray',
}: PropsWithChildren<{ tone?: 'gray' | 'green' | 'amber' | 'red' | 'blue' }>) {
    const tones = {
        gray: 'bg-gray-100 text-gray-700',
        green: 'bg-emerald-100 text-emerald-800',
        amber: 'bg-amber-100 text-amber-800',
        red: 'bg-red-100 text-red-800',
        blue: 'bg-blue-100 text-blue-800',
    };

    return (
        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${tones[tone]}`}>{children}</span>
    );
}

/** Número grande com rótulo, no padrão dos indicadores do painel (cartão com anel cinza). `tone` colore só o número. */
export function StatCard({
    label,
    value,
    hint,
    tone = 'gray',
    href,
}: {
    label: string;
    value: ReactNode;
    hint?: string;
    tone?: 'gray' | 'green' | 'amber' | 'red' | 'blue';
    href?: string;
}) {
    const tones = { gray: 'text-gray-900', green: 'text-emerald-700', amber: 'text-amber-700', red: 'text-red-700', blue: 'text-blue-700' };
    const body = (
        <>
            <div className={`text-2xl font-bold tabular-nums sm:text-3xl ${tones[tone]}`}>{value}</div>
            <div className="mt-1 text-sm font-medium text-gray-700">{label}</div>
            {hint && <div className="mt-0.5 text-xs text-gray-500">{hint}</div>}
        </>
    );
    const base = 'block min-w-0 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-5';

    return href ? (
        <Link href={href} className={`${base} transition hover:ring-emerald-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500`}>
            {body}
        </Link>
    ) : (
        <div className={base}>{body}</div>
    );
}

/**
 * Faixa de filtros num cartão: campos empilhados no celular e lado a lado a partir de `sm`. Use com `Field` + `Select`;
 * os botões (limpar, aplicar) vão em `actions`.
 */
export function FilterBar({ children, actions, label }: PropsWithChildren<{ actions?: ReactNode; label: string }>) {
    return (
        <div className="mb-4 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200 sm:p-4" role="group" aria-label={label}>
            <div className="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {children}
                {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
            </div>
        </div>
    );
}

/**
 * Menu de ações do cabeçalho: agrupa links secundários (ex.: PDFs) num botão só, para o título não ficar com uma fila de botões
 * no celular. Fecha com Esc, clique fora ou ao escolher uma opção.
 */
export function ActionMenu({
    label,
    items,
}: {
    label: string;
    items: { label: string; href: string; hint?: string; newTab?: boolean }[];
}) {
    const [open, setOpen] = useState(false);
    const box = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);
        const onClick = (event: MouseEvent) => box.current && !box.current.contains(event.target as Node) && setOpen(false);
        document.addEventListener('keydown', onKey);
        document.addEventListener('mousedown', onClick);

        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onClick);
        };
    }, [open]);

    if (items.length === 0) return null;

    return (
        <div ref={box} className="relative w-full sm:w-auto">
            <Button variant="secondary" className="w-full sm:w-auto" aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen(!open)}>
                {label}
                <span aria-hidden="true" className="ml-2 text-gray-400">
                    ▾
                </span>
            </Button>
            {open && (
                <ul role="menu" className="absolute left-0 right-0 z-30 mt-1 rounded-lg bg-white p-1 shadow-lg ring-1 ring-gray-200 sm:left-auto sm:w-72 sm:max-w-[calc(100vw-2rem)]">
                    {items.map((item) => (
                        <li key={item.href} role="none">
                            <a
                                role="menuitem"
                                href={item.href}
                                target={item.newTab ? '_blank' : undefined}
                                rel={item.newTab ? 'noreferrer' : undefined}
                                onClick={() => setOpen(false)}
                                className="block min-h-11 rounded-md px-3 py-2 text-sm text-gray-800 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none sm:min-h-0"
                            >
                                <span className="font-medium">{item.label}</span>
                                {item.hint && <span className="block text-xs text-gray-500">{item.hint}</span>}
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export function Empty({ title, children }: PropsWithChildren<{ title: string }>) {
    return (
        <div className="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center">
            <p className="font-medium text-gray-700">{title}</p>
            {children && <div className="mt-2 text-sm text-gray-500">{children}</div>}
        </div>
    );
}

export function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    const label = (link: PaginationLink, index: number) => {
        if (index === 0) return 'Anterior';
        if (index === links.length - 1) return 'Próxima';
        return link.label;
    };

    return (
        <nav className="mt-4 flex flex-wrap items-center justify-center gap-1">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={`rounded-md px-3 py-1 text-sm ${
                            link.active ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'
                        }`}
                    >
                        {label(link, index)}
                    </Link>
                ) : (
                    <span key={index} className="rounded-md px-3 py-1 text-sm text-gray-400">
                        {label(link, index)}
                    </span>
                ),
            )}
        </nav>
    );
}

export function SearchBox({
    value,
    onChange,
    onSubmit,
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    onSubmit: () => void;
    placeholder: string;
}) {
    return (
        <form
            className="flex flex-col gap-2 sm:flex-row"
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit();
            }}
        >
            <Input value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} className="sm:w-72" />
            <Button type="submit" variant="secondary" className="w-full sm:w-auto">
                Buscar
            </Button>
        </form>
    );
}

/** Escudo/foto em miniatura; sem imagem mostra as iniciais sobre a cor informada. */
export function Logo({ src, name, size = 28, round = false, color }: { src?: string | null; name: string; size?: number; round?: boolean; color?: string | null }) {
    const shape = round ? 'rounded-full' : 'rounded-md';
    const initials = name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

    if (src) {
        return <img src={src} alt="" width={size} height={size} loading="lazy" className={`shrink-0 bg-[#ffffff] object-contain ${shape}`} style={{ width: size, height: size }} />;
    }

    return (
        <span
            aria-hidden="true"
            className={`inline-flex shrink-0 items-center justify-center text-[10px] font-semibold text-white ${shape}`}
            style={{ width: size, height: size, backgroundColor: color ?? '#9ca3af' }}
        >
            {initials}
        </span>
    );
}

/** Envio de imagem (escudo/foto): mostra a atual, envia o arquivo escolhido e permite remover. */
export function ImageUpload({
    label,
    field,
    currentUrl,
    name,
    uploadUrl,
    removeUrl,
    hint,
    round = false,
}: {
    label: string;
    field: string;
    currentUrl: string | null;
    name: string;
    uploadUrl: string;
    removeUrl: string;
    hint?: string;
    round?: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [sending, setSending] = useState(false);
    const error = (usePage().props.errors as Record<string, string> | undefined)?.[field];

    const send = (file: File | undefined) => {
        if (!file) return;
        setSending(true);
        router.post(uploadUrl, { [field]: file }, { forceFormData: true, preserveScroll: true, onFinish: () => setSending(false) });
        if (input.current) input.current.value = '';
    };

    return (
        <div>
            <span className="mb-1 block text-sm font-medium text-gray-700">{label}</span>
            <div className="flex items-center gap-4">
                <Logo src={currentUrl} name={name} size={72} round={round} />
                <div className="space-y-1">
                    <div className="flex flex-wrap gap-2">
                        <Button small variant="secondary" disabled={sending} onClick={() => input.current?.click()}>
                            {currentUrl ? 'Trocar imagem' : 'Enviar imagem'}
                        </Button>
                        {currentUrl && (
                            <Button
                                small
                                variant="danger"
                                disabled={sending}
                                onClick={() => {
                                    if (window.confirm('Remover esta imagem?')) router.delete(removeUrl, { preserveScroll: true });
                                }}
                            >
                                Remover
                            </Button>
                        )}
                    </div>
                    <input ref={input} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" aria-label={label} onChange={(event) => send(event.target.files?.[0])} />
                    <p className="text-xs text-gray-500">{hint ?? 'JPG, PNG ou WebP.'}</p>
                    {error && <p className="text-sm text-red-600">{error}</p>}
                </div>
            </div>
        </div>
    );
}
