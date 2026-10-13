import { useState } from 'react';

export interface MarketVideo {
    id: string;
    provider: string;
    provider_label: string;
    url: string;
    embed_url: string | null;
}

/**
 * Vídeo de jogada por link externo. Nada de terceiros carrega com a página: só ao clicar em "Assistir" entra o player
 * incorporado (iframe isolado, sem referência de origem). O link original sempre fica disponível.
 */
export default function VideoEmbed({ video }: { video: MarketVideo }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="rounded-md bg-gray-50 p-2 ring-1 ring-gray-200">
            {open && video.embed_url ? (
                <iframe
                    src={video.embed_url}
                    title={`Vídeo no ${video.provider_label}`}
                    className="aspect-video w-full rounded"
                    loading="lazy"
                    allowFullScreen
                    referrerPolicy="no-referrer"
                    sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"
                />
            ) : (
                <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="font-medium text-gray-800">Vídeo · {video.provider_label}</span>
                    <span className="flex gap-3">
                        {video.embed_url && (
                            <button type="button" onClick={() => setOpen(true)} className="font-medium text-emerald-700 hover:underline">
                                Assistir
                            </button>
                        )}
                        <a href={video.url} target="_blank" rel="noreferrer noopener" className="text-gray-600 hover:underline">
                            Abrir no site
                        </a>
                    </span>
                </div>
            )}
        </div>
    );
}
