<?php

namespace App\Modules\Portal\Support;

use App\Modules\Tenancy\CurrentOrganization;

/**
 * O que o portal público mostra, por organização (`settings.portal`). O padrão é o que não expõe nada sensível; o que é
 * mais delicado (cartões) só aparece se a organização ligar de propósito. Só reduz ou amplia dentro do que o portal já pode
 * mostrar: nunca libera dado pessoal (CPF, documentos, fotos) nem edição em rascunho.
 */
final class PortalSettings
{
    /** @var array<string, array{group: string, default: bool, label: string, hint: string}> */
    public const SWITCHES = [
        'home_live' => ['group' => 'Página inicial', 'default' => true, 'label' => 'Bloco "Ao vivo agora"', 'hint' => 'Jogos em andamento (só aparece quando há algum).'],
        'home_upcoming' => ['group' => 'Página inicial', 'default' => true, 'label' => 'Bloco "Próximos jogos"', 'hint' => 'Os próximos jogos agendados das edições publicadas.'],
        'home_recent' => ['group' => 'Página inicial', 'default' => true, 'label' => 'Bloco "Resultados recentes"', 'hint' => 'Os últimos jogos encerrados das edições publicadas.'],
        'home_competitions' => ['group' => 'Página inicial', 'default' => true, 'label' => 'Lista de competições', 'hint' => 'Competições e edições publicadas, com link para cada uma.'],
        'game_timeline' => ['group' => 'Página do jogo', 'default' => true, 'label' => 'Linha do tempo do jogo', 'hint' => 'Gols e substituições, com minuto (futebol e futsal).'],
        'game_cards' => ['group' => 'Página do jogo', 'default' => false, 'label' => 'Cartões na linha do tempo', 'hint' => 'Amarelos e vermelhos. Desligado por padrão: cartões são dado disciplinar.'],
        'game_standings' => ['group' => 'Página do jogo', 'default' => true, 'label' => 'Posição das equipes na tabela', 'hint' => 'Colocação e pontos das duas equipes, quando a fase tem tabela.'],
        'game_form' => ['group' => 'Página do jogo', 'default' => true, 'label' => 'Forma recente', 'hint' => 'Resultado dos últimos 5 jogos de cada equipe.'],
        'game_head_to_head' => ['group' => 'Página do jogo', 'default' => true, 'label' => 'Confronto direto', 'hint' => 'Histórico de jogos entre as duas equipes na organização.'],
        'game_lineup' => ['group' => 'Página do jogo', 'default' => false, 'label' => 'Escalações públicas', 'hint' => 'Titulares, reservas e comissão com nome público e camisa; desligado por padrão e sem foto, nascimento ou documento.'],
        'game_media' => ['group' => 'Página do jogo', 'default' => false, 'label' => 'Vídeos e replays', 'hint' => 'Links de vídeos publicados para esta partida; desligado por padrão.'],
        'team_history' => ['group' => 'Página da equipe', 'default' => true, 'label' => 'Campanha por edição', 'hint' => 'Jogos, vitórias, empates, derrotas, gols e colocação em cada edição publicada.'],
        'team_form' => ['group' => 'Página da equipe', 'default' => true, 'label' => 'Forma recente', 'hint' => 'Resultado dos últimos 5 jogos da equipe.'],
        'team_scorers' => ['group' => 'Página da equipe', 'default' => true, 'label' => 'Artilheiros da equipe', 'hint' => 'Os 5 maiores goleadores, pelo nome público (já é público na artilharia da edição).'],
        'team_calendar' => ['group' => 'Página da equipe', 'default' => true, 'label' => 'Calendário (.ics)', 'hint' => 'Link para a pessoa adicionar os jogos da equipe à agenda do celular.'],
        'athlete_profile' => ['group' => 'Perfil do atleta', 'default' => false, 'label' => 'Perfil público do atleta', 'hint' => 'Página própria só de quem autorizou no cadastro e é maior de 18 anos: nome público, equipes, edições, camisa, jogos e gols. Nunca foto, nascimento, documento ou cartões. Desligado por padrão.'],
        'team_roster' => ['group' => 'Página da equipe', 'default' => false, 'label' => 'Elenco público', 'hint' => 'Atletas (nome público e camisa) e técnico da edição mais recente. Desligado por padrão; sem foto, nascimento ou documento.'],
        'season_bracket' => ['group' => 'Página da edição', 'default' => true, 'label' => 'Chaveamento do mata-mata', 'hint' => 'Quadro visual das fases eliminatórias, com placares, pênaltis e o campeão.'],
        'season_stats' => ['group' => 'Página da edição', 'default' => true, 'label' => 'Página de estatísticas', 'hint' => 'Ataque, defesa, forma e maiores resultados da edição (botão "Estatísticas"). Desligada, o endereço responde "não encontrado".'],
        'season_rules' => ['group' => 'Página da edição', 'default' => true, 'label' => 'Regras da tabela explicadas', 'hint' => 'Pontos por vitória/empate/derrota e a ordem dos critérios de desempate.'],
        'share_buttons' => ['group' => 'Compartilhamento', 'default' => true, 'label' => 'Botões de compartilhar', 'hint' => 'WhatsApp e copiar link nas páginas do jogo, da equipe e da edição.'],
        'competition_page' => ['group' => 'Página da competição', 'default' => true, 'label' => 'Página própria da competição', 'hint' => 'Endereço /competicoes/{nome} com o histórico das edições. Desligada, o endereço leva direto à edição mais recente.'],
        'competition_champions' => ['group' => 'Página da competição', 'default' => true, 'label' => 'Campeão e vice de cada edição', 'hint' => 'No mata-mata, pela final decidida; em pontos corridos, pelo líder da tabela depois que a edição é encerrada.'],
        'competition_records' => ['group' => 'Página da competição', 'default' => true, 'label' => 'Recordes e títulos', 'hint' => 'Equipes com mais títulos, maiores vitórias e jogos com mais placar de todas as edições (só jogos encerrados em campo).'],
    ];

    public static function enabled(string $key): bool
    {
        $saved = CurrentOrganization::get()?->settings['portal'][$key] ?? null;

        return is_bool($saved) ? $saved : (self::SWITCHES[$key]['default'] ?? false);
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        $all = [];

        foreach (array_keys(self::SWITCHES) as $key) {
            $all[$key] = self::enabled($key);
        }

        return $all;
    }
}
