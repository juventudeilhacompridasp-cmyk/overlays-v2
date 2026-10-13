import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useOnline } from './pwa';
import type { QueueItem, QueueKind } from './sheetProject';

/**
 * Fila offline do mesário (súmula). Cada lançamento vira um item com chave própria e a hora do lance, guardado neste
 * aparelho (localStorage; só ids e horas, nada de nome, CPF ou documento) e enviado em lote para `games/{jogo}/sync`
 * assim que há conexão. O servidor reaplica tudo com a validação do regulamento: o que ele recusar fica visível para o
 * mesário decidir. O service worker continua sem guardar páginas nem respostas (LGPD): se a página for recarregada sem
 * internet ela não abre, mas a fila continua aqui e é enviada quando a súmula abrir de novo.
 */

const storageKey = (userId: number | string, gameId: string): string => `mesa:v1:${userId}:${gameId}`;

function load(key: string): QueueItem[] {
    try {
        const parsed: unknown = JSON.parse(window.localStorage.getItem(key) ?? '[]');

        return Array.isArray(parsed) ? (parsed as QueueItem[]).filter((item) => typeof item?.key === 'string' && typeof item?.kind === 'string') : [];
    } catch {
        return [];
    }
}

function save(key: string, items: QueueItem[]): void {
    try {
        if (items.length === 0) window.localStorage.removeItem(key);
        else window.localStorage.setItem(key, JSON.stringify(items));
    } catch {
        // sem armazenamento (navegação privada, cheio): a fila segue só na memória desta aba
    }
}

function newKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

interface SyncResult {
    key: string;
    status: 'applied' | 'duplicate' | 'rejected' | 'skipped';
    message: string | null;
}

export interface SheetQueue {
    items: QueueItem[];
    sending: boolean;
    online: boolean;
    /** Aviso para a tela (recusa de um lançamento feito com internet, sessão vencida...). */
    notice: string | null;
    dismissNotice: () => void;
    enqueue: (kind: QueueKind, payload: Record<string, unknown>, label: string) => void;
    discard: (key: string) => void;
    discardAll: () => void;
    retry: () => void;
}

export function useSheetQueue({ gameId, userId, skewMs, enabled }: { gameId: string; userId: number | string; skewMs: number; enabled: boolean }): SheetQueue {
    const key = storageKey(userId, gameId);
    const online = useOnline();
    const [items, setItems] = useState<QueueItem[]>(() => (enabled ? load(key) : []));
    const [sending, setSending] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const itemsRef = useRef(items);
    const sendingRef = useRef(false);
    const skewRef = useRef(skewMs);
    skewRef.current = skewMs;

    const update = useCallback(
        (next: QueueItem[]) => {
            itemsRef.current = next;
            setItems(next);
            save(key, next);
        },
        [key],
    );

    const flush = useCallback(async () => {
        const head = itemsRef.current[0];

        if (!enabled || sendingRef.current || head === undefined || head.status === 'blocked' || !navigator.onLine) return;

        sendingRef.current = true;
        setSending(true);

        const batch = itemsRef.current.map(({ key: itemKey, kind, at, payload }) => ({ key: itemKey, kind, at, payload }));

        try {
            const response = await fetch(route('panel.games.sync', gameId), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
                body: JSON.stringify({ items: batch }),
            });

            if (response.status === 401 || response.status === 419) {
                setNotice('Sua sessão expirou. Entre de novo; os lançamentos continuam guardados neste aparelho e serão enviados.');

                return;
            }

            if (response.status === 403) {
                setNotice('Você não tem permissão para lançar neste jogo. Os lançamentos pendentes não foram enviados.');

                return;
            }

            if (!response.ok) {
                // 423 (outro envio em andamento), 5xx etc.: tenta de novo mais tarde
                return;
            }

            const { results } = (await response.json()) as { results: SyncResult[] };
            const byKey = new Map(results.map((result) => [result.key, result]));
            const next: QueueItem[] = [];
            let touched = false;

            for (const item of itemsRef.current) {
                const result = byKey.get(item.key);

                if (result === undefined || result.status === 'skipped') {
                    next.push(item);
                } else if (result.status === 'applied' || result.status === 'duplicate') {
                    touched = true;
                } else if (!item.offline) {
                    // Lançamento feito com internet: o erro aparece na hora, como antes, e não fica na fila.
                    touched = true;
                    setNotice(result.message ?? 'Lançamento recusado.');
                } else {
                    next.push({ ...item, status: 'blocked', message: result.message ?? 'Lançamento recusado.' });
                }
            }

            update(next);

            if (touched || next.some((item) => item.status === 'blocked')) {
                router.reload();
            }
        } catch {
            // sem rede de verdade (o navegador disse online, mas a requisição falhou): mantém a fila e tenta de novo
        } finally {
            sendingRef.current = false;
            setSending(false);
        }
    }, [enabled, gameId, update]);

    // Envia quando há conexão e itens; insiste a cada 15 s enquanto sobrar algo (rede ruim, outro envio em andamento).
    useEffect(() => {
        if (!enabled || items.length === 0 || !online) return;

        void flush();
        const timer = window.setInterval(() => void flush(), 15000);

        return () => window.clearInterval(timer);
    }, [enabled, items.length, online, flush]);

    const enqueue = useCallback(
        (kind: QueueKind, payload: Record<string, unknown>, label: string) => {
            const item: QueueItem = {
                key: newKey(),
                kind,
                payload,
                at: Date.now() - skewRef.current,
                // Sem internet, ou atrás de outros itens (a ordem importa): se for recusado, o mesário decide.
                offline: !navigator.onLine || itemsRef.current.length > 0,
                status: 'pending',
                label,
            };

            update([...itemsRef.current, item]);
            void flush();
        },
        [flush, update],
    );

    return {
        items,
        sending,
        online,
        notice,
        dismissNotice: () => setNotice(null),
        enqueue,
        discard: (itemKey) => update(itemsRef.current.filter((item) => item.key !== itemKey)),
        discardAll: () => update([]),
        retry: () => {
            update(itemsRef.current.map((item, index) => (index === 0 && item.status === 'blocked' ? { ...item, status: 'pending', message: undefined } : item)));
            void flush();
        },
    };
}
