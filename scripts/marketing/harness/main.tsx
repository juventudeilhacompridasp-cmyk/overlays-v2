import '../../../resources/css/app.css';
import LowerThirdCard from '@/Components/Overlay/LowerThirdCard';
import { SponsorBarCard } from '@/Components/Overlay/SponsorBarCard';
import OperatorConsole from '@/Pages/Panel/Broadcast/OperatorConsole';
import { TeamShow } from '@/Pages/Overlay/Lineup';
import Scoreboard from '@/Pages/Overlay/Scoreboard';
import Shootout from '@/Pages/Overlay/Shootout';
import Standings from '@/Pages/Overlay/Standings';
import { createRoot } from 'react-dom/client';
import { AWAY, appearance, avatar, banner, crest, game, HOME, lineupSide, standings, state } from './fixtures';

(window as unknown as { route: (name: string) => string }).route = (name: string) => `/${name}`;

const realtime = { key: 'local', host: '127.0.0.1', port: 1, scheme: 'http' } as never;
const shot = new URLSearchParams(location.search).get('shot') ?? 'placar';

/** Fundo de gramado só para os prints dos overlays (o OBS mostra a transmissão atrás). */
function Pitch({ children }: { children: React.ReactNode }) {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: -1, background: 'linear-gradient(180deg,#0b3b20,#0f5c2f 55%,#0b3b20)' }}>
            <div style={{ position: 'absolute', inset: 0, background: 'repeating-linear-gradient(90deg, rgba(255,255,255,.04) 0 120px, transparent 120px 240px)' }} />
            <div style={{ position: 'absolute', left: 0, right: 0, top: '38%', borderTop: '3px solid rgba(255,255,255,.22)' }} />
            <div style={{ position: 'absolute', left: '50%', top: '38%', width: 360, height: 360, marginLeft: -180, marginTop: -180, borderRadius: '50%', border: '3px solid rgba(255,255,255,.18)' }} />
            {children}
        </div>
    );
}

const ready = (node: React.ReactNode) => <div data-shot-ready="true">{node}</div>;

function App() {
    switch (shot) {
        case 'placar':
            return <>{ready(null)}<Pitch>{null}</Pitch><Scoreboard token="demo" state={state({ appearance: { ...appearance, position: 'top-left', scale: 2.2 } })} realtime={realtime} /></>;
        case 'gol':
            return (
                <>
                    {ready(null)}
                    <Pitch>{null}</Pitch>
                    <div style={{ position: 'fixed', left: 120, bottom: 150, transform: 'scale(2.3)', transformOrigin: 'left bottom', color: '#f8fafc' }}>
                        <LowerThirdCard style="arena" appearance={appearance} banner={{ id: 'b1', kind: 'goal', title: 'GOL!', subtitle: 'Leões da Ilha', team: 'Leões da Ilha', color: '#0d9488', minute: 58, team_short: 'LEO', team_logo: crest('#0d9488', 'LEO'), player: { name: 'Caio Ribeiro', shirt: 9, position: 'Atacante' }, score: { home: 2, away: 1, home_short: 'LEO', away_short: 'TIG' }, detail: '2º gol dele na partida' }} />
                    </div>
                </>
            );
        case 'escalacao':
            return (
                <>
                    {ready(null)}
                    <Pitch>{null}</Pitch>
                    <div style={{ position: 'fixed', inset: 0, color: '#f8fafc', textShadow: '0 1px 2px rgba(0,0,0,.55)', transform: 'scale(1.35)', transformOrigin: 'center 96%' }}>
                        <TeamShow state={state({ lineup_settings: { ...state().lineup_settings, presentation: 'full', step: 1 } })} skew={0} />
                    </div>
                </>
            );
        case 'tabela':
            return <>{ready(null)}<Pitch>{null}</Pitch><Standings token="demo" state={state({ standings: standings(), appearance: { ...appearance, position: 'top-left', scale: 1.9 } })} realtime={realtime} /></>;
        case 'penaltis':
            return (
                <>
                    {ready(null)}
                    <Pitch>{null}</Pitch>
                    <Shootout token="demo" state={state({ appearance: { ...appearance, scale: 1.45 }, game: game({ home_score: 1, away_score: 1, shootout: { home: [true, true, false, true], away: [true, false, true, true], goals: { home: 3, away: 3 }, first: 'home', next: 'home', decided: false, winner: null, rounds: 5, by_kick: true } }) })} realtime={realtime} />
                </>
            );
        case 'patrocinio':
            return (
                <>
                    {ready(null)}
                    <Pitch>{null}</Pitch>
                    <div style={{ position: 'fixed', left: '50%', bottom: 70, transform: 'translateX(-50%) scale(1.1)', transformOrigin: 'bottom center' }}>
                        <SponsorBarCard
                            appearance={appearance}
                            sponsors={{ enabled: true, seconds: 5, items: [{ name: 'Padaria Estrela', logo_url: avatar('#b91c1c'), banner_url: banner('#b91c1c', 'PADARIA ESTRELA', 'Pão quentinho a qualquer hora') }, { name: 'Auto Peças Ilha', logo_url: avatar('#1d4ed8'), banner_url: banner('#1d4ed8', 'AUTO PEÇAS ILHA', 'Tudo para o seu carro') }] }}
                        />
                    </div>
                </>
            );
        default: {
            const people = Array.from({ length: 22 }, (_, index) => ({ id: `p${index}`, name: `Jogador ${index + 1}`, shirt: index + 1, team: index < 11 ? HOME.name : AWAY.name, has_photo: false, photo_url: null }));
            const channel = {
                id: 'c1', name: 'Transmissão principal', game_id: 'g1', game_label: 'Leões da Ilha x Tigres FC · Copa Ilha · 14/10 19:30',
                layers: { scoreboard: true, lower_third: true, lineup: false, standings: false }, championship_live: { standings: true }, stoppage: 2,
                screens_live: { stats: true, sponsor_bar: true }, screen_settings: { title: 'Intervalo', subtitle: '', until_ms: null, serve: null, social: { items: [], url: null } },
                lineup_settings: { presentation: 'full', team: 'home', individual_mode: 'manual', step: 2, anchor_ms: 0 }, lineup_people: people, lineup_steps: 14,
                sponsor_settings: { enabled: true }, stats_settings: { home: { shots: 7, shots_on_target: 3, corners: 4, fouls: 9, offsides: 1, saves: 2 }, away: { shots: 5, shots_on_target: 2, corners: 2, fouls: 11, offsides: 3, saves: 4 } },
                custom_overlays: Array.from({ length: 4 }, (_, index) => ({ id: `o${index}`, name: ['Chamada do jogo', 'Aviso de rodada', 'Redes sociais', 'Parceiros'][index], url: '', live: index === 0 })),
            };
            return (
                <div data-shot-ready="true">
                    <OperatorConsole channels={[channel as never]} games={[{ id: 'g1', label: 'Leões da Ilha x Tigres FC · Copa Ilha' }] as never} scenes={[{ id: 's1', name: 'Pré-jogo' }, { id: 's2', name: 'Jogo' }, { id: 's3', name: 'Intervalo' }, { id: 's4', name: 'Pós-jogo' }] as never} canOperate operatorLayouts={{}} onExit={() => undefined} />
                </div>
            );
        }
    }
}

createRoot(document.getElementById('root')!).render(<App />);
