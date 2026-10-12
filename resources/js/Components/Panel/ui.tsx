import { Link, router } from '@inertiajs/react';
import {
    ButtonHTMLAttributes,
    InputHTMLAttributes,
    PropsWithChildren,
    ReactNode,
    SelectHTMLAttributes,
    TextareaHTMLAttributes,
} from 'react';
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
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
                    {subtitle && <p className="mt-1 text-sm text-gray-600">{subtitle}</p>}
                </div>
                {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
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
                <header className="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
                    <h2 className="text-base font-semibold text-gray-900">{title}</h2>
                    {actions && <div className="flex items-center gap-2">{actions}</div>}
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

export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
    return <input {...props} className={`${inputBase} ${props.className ?? ''}`} />;
}

export function Select({ children, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select {...props} className={`${inputBase} ${props.className ?? ''}`}>
            {children}
        </select>
    );
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
        small ? 'px-2.5 py-1 text-xs' : 'px-4 py-2 text-sm'
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
            className="flex gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit();
            }}
        >
            <Input value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} className="sm:w-72" />
            <Button type="submit" variant="secondary">
                Buscar
            </Button>
        </form>
    );
}
