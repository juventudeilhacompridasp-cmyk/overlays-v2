import { useState } from 'react';

/** Compartilhar no WhatsApp (link com texto pronto) e copiar o endereço. Não envia nada a ninguém por conta própria. */
export default function ShareButtons({ text, url }: { text: string; url: string }) {
    const [copied, setCopied] = useState(false);
    const whatsapp = `https://wa.me/?text=${encodeURIComponent(`${text} ${url}`)}`;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            // sem permissão da área de transferência
        }
    };

    return (
        <span className="inline-flex gap-2 print:hidden">
            <a href={whatsapp} target="_blank" rel="noopener noreferrer" className="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-900 ring-1 ring-gray-300 hover:bg-gray-100" data-testid="share-whatsapp">
                Compartilhar no WhatsApp
            </a>
            <button type="button" onClick={copy} className="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-900 ring-1 ring-gray-300 hover:bg-gray-100">
                {copied ? 'Copiado!' : 'Copiar link'}
            </button>
        </span>
    );
}
