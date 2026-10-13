import { PropsWithChildren, useEffect, useRef } from 'react';

export interface EmbedOptions {
    dark: boolean;
    accent: string;
    name: string;
}

/**
 * Moldura dos widgets embutidos: tema claro/escuro e cor de destaque vindos da URL, e aviso da altura ao site
 * hospedeiro (`postMessage`), para o iframe se ajustar sozinho (ver o código gerado em Painel > Widgets).
 */
export default function Frame({ options, footer, children }: PropsWithChildren<{ options: EmbedOptions; footer: { label: string; href: string } }>) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const node = ref.current;
        if (!node || window.parent === window) return;

        const send = () => window.parent.postMessage({ type: 'overlays-embed-height', height: Math.ceil(node.getBoundingClientRect().height) }, '*');
        const observer = new ResizeObserver(send);
        observer.observe(node);
        send();

        return () => observer.disconnect();
    }, []);

    const surface = options.dark ? 'bg-neutral-900 text-neutral-100' : 'bg-[#ffffff] text-neutral-900';

    return (
        <div ref={ref} className={`${surface} font-sans text-sm`} style={{ ['--accent' as string]: options.accent }}>
            {children}
            <div className={`border-t px-3 py-1.5 text-right text-xs ${options.dark ? 'border-neutral-700 text-neutral-400' : 'border-neutral-200 text-neutral-500'}`}>
                <a href={footer.href} target="_blank" rel="noopener noreferrer" className="hover:underline">
                    {footer.label} · {options.name}
                </a>
            </div>
        </div>
    );
}

export const muted = (options: EmbedOptions) => (options.dark ? 'text-neutral-400' : 'text-neutral-500');
export const line = (options: EmbedOptions) => (options.dark ? 'divide-neutral-800 border-neutral-800' : 'divide-neutral-200 border-neutral-200');
