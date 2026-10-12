import type Echo from 'laravel-echo';

export interface Realtime {
    key: string | null;
    host: string | null;
    port: number;
    scheme: string;
}

export type ConnectionState = 'connecting' | 'connected' | 'disconnected';

/**
 * Assina o canal público de um jogo no Reverb. O endereço vem do servidor (config/platform.php), então o mesmo
 * build do front serve em qualquer ambiente. Retorna a função que cancela a assinatura.
 */
export async function subscribeToGame<T>(
    realtime: Realtime,
    gameId: string,
    onUpdate: (snapshot: T) => void,
    onState: (state: ConnectionState) => void,
): Promise<() => void> {
    if (!realtime.key) {
        onState('disconnected');

        return () => undefined;
    }

    const [{ default: EchoClient }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')]);

    const secure = realtime.scheme === 'https';
    const echo: Echo<'reverb'> = new EchoClient({
        broadcaster: 'reverb',
        key: realtime.key,
        wsHost: realtime.host || window.location.hostname,
        wsPort: realtime.port,
        wssPort: realtime.port,
        forceTLS: secure,
        enabledTransports: ['ws', 'wss'],
        Pusher,
    });

    const connection = echo.connector.pusher.connection;
    const sync = () => onState(connection.state === 'connected' ? 'connected' : connection.state === 'connecting' ? 'connecting' : 'disconnected');
    connection.bind('state_change', sync);
    sync();

    echo.channel(`games.${gameId}`).listen('.game.updated', (payload: { snapshot: T }) => onUpdate(payload.snapshot));

    return () => {
        echo.leave(`games.${gameId}`);
        echo.disconnect();
    };
}
