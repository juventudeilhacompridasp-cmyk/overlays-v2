// Cliente WebSocket de verificação (usa o mesmo pusher-js do front): assina um canal público do Reverb, avisa quando
// a assinatura foi aceita e termina com o primeiro evento recebido.
//
// Uso: node tests/e2e/subscribe.mjs <porta> <chave> <canal> <evento> [timeoutMs]
import Pusher from 'pusher-js';

const [port, key, channelName, eventName, timeout = '15000'] = process.argv.slice(2);

const pusher = new Pusher(key, {
    wsHost: '127.0.0.1',
    wsPort: Number(port),
    wssPort: Number(port),
    forceTLS: false,
    enabledTransports: ['ws'],
    disableStats: true,
    cluster: 'mt1',
});

const timer = setTimeout(() => {
    console.log('TIMEOUT');
    process.exit(1);
}, Number(timeout));

const channel = pusher.subscribe(channelName);
channel.bind('pusher:subscription_succeeded', () => console.log('SUBSCRIBED'));
channel.bind(eventName, (data) => {
    clearTimeout(timer);
    console.log(`EVENT ${JSON.stringify(data)}`);
    pusher.disconnect();
    process.exit(0);
});
pusher.connection.bind('error', (error) => console.log(`ERROR ${JSON.stringify(error)}`));
