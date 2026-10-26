import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

/** QR compacto para abrir a página pública do jogo em outro aparelho. */
export default function GameQrCode({ url, label = 'QR code da partida' }: { url: string; label?: string }) {
    const [src, setSrc] = useState<string | null>(null);

    useEffect(() => {
        let active = true;
        QRCode.toDataURL(url, { margin: 1, width: 240, errorCorrectionLevel: 'M' })
            .then((value) => active && setSrc(value))
            .catch(() => active && setSrc(null));

        return () => {
            active = false;
        };
    }, [url]);

    if (src === null) {
        return <div className="h-40 w-40 rounded-md bg-gray-100" aria-label="Gerando QR code" />;
    }

    return (
        <div className="flex flex-col items-center gap-2">
            <img src={src} alt={label} width={160} height={160} className="rounded-md bg-white p-1 ring-1 ring-gray-200" />
            <a href={src} download="qr-partida.png" className="text-xs font-semibold text-emerald-700 hover:underline">
                Baixar QR code
            </a>
        </div>
    );
}
