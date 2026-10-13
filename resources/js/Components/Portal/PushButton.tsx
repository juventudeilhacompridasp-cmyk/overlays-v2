import { useEffect, useState } from 'react';

type State = 'hidden' | 'idle' | 'on' | 'denied' | 'busy';

const storageKey = (kind: string, id: string) => `push:${kind}:${id}`;

const keyToBytes = (base64: string): Uint8Array<ArrayBuffer> => {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(padded);
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));
    for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);

    return bytes;
};

/**
 * "Avisar-me" de um jogo ou de uma equipe por notificação push. Só aparece onde o navegador suporta e o servidor
 * tem as chaves (publicKey). O pedido é anônimo; o usuário cancela quando quiser.
 */
export default function PushButton({ kind, id, publicKey, label }: { kind: 'game' | 'team'; id: string; publicKey: string | null; label: string }) {
    const [state, setState] = useState<State>('hidden');

    useEffect(() => {
        if (!publicKey || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;

        try {
            setState(Notification.permission === 'denied' ? 'denied' : window.localStorage.getItem(storageKey(kind, id)) === '1' ? 'on' : 'idle');
        } catch {
            setState('idle');
        }
    }, [publicKey, kind, id]);

    if (state === 'hidden' || !publicKey) return null;

    const subscribe = async () => {
        setState('busy');

        try {
            if ((await Notification.requestPermission()) !== 'granted') {
                setState('denied');

                return;
            }

            await navigator.serviceWorker.register('/sw.js');
            const registration = await navigator.serviceWorker.ready;
            const subscription = (await registration.pushManager.getSubscription()) ?? (await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(publicKey) }));
            const json = subscription.toJSON();

            await window.axios.post(route('push.subscribe'), { kind, id, endpoint: subscription.endpoint, keys: json.keys });
            window.localStorage.setItem(storageKey(kind, id), '1');
            setState('on');
        } catch {
            setState('idle');
        }
    };

    const unsubscribe = async () => {
        setState('busy');

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await window.axios.post(route('push.unsubscribe'), { kind, id, endpoint: subscription.endpoint });
            }

            window.localStorage.removeItem(storageKey(kind, id));
        } catch {
            // sem rede: o pedido continua valendo no servidor até a próxima tentativa
        }

        setState('idle');
    };

    if (state === 'denied') {
        return <p className="text-xs text-gray-500">Os avisos estão bloqueados neste navegador. Libere as notificações do site para receber.</p>;
    }

    return (
        <button
            type="button"
            disabled={state === 'busy'}
            onClick={state === 'on' ? unsubscribe : subscribe}
            className="rounded-md bg-white px-3 py-2 text-sm font-medium ring-1 ring-gray-300 hover:bg-gray-100 disabled:opacity-50 print:hidden"
            data-testid="push-button"
        >
            {state === 'on' ? '🔔 Avisos ligados · desligar' : `🔔 ${label}`}
        </button>
    );
}
