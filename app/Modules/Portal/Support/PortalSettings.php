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
        'game_timeline' => ['group' => 'Página do jogo', 'default' => true, 'label' => 'Linha do tempo do jogo', 'hint' => 'Gols e substituições, com minuto (futebol e futsal).'],
        'game_cards' => ['group' => 'Página do jogo', 'default' => false, 'label' => 'Cartões na linha do tempo', 'hint' => 'Amarelos e vermelhos. Desligado por padrão: cartões são dado disciplinar.'],
        'game_standings' => ['group' => 'Página do jogo', 'default' => true, 'label' => 'Posição das equipes na tabela', 'hint' => 'Colocação e pontos das duas equipes, quando a fase tem tabela.'],
        'game_form' => ['group' => 'Página do jogo', 'default' => true, 'label' => 'Forma recente', 'hint' => 'Resultado dos últimos 5 jogos de cada equipe.'],
        'game_head_to_head' => ['group' => 'Página do jogo', 'default' => true, 'label' => 'Confronto direto', 'hint' => 'Histórico de jogos entre as duas equipes na organização.'],
        'team_history' => ['group' => 'Página da equipe', 'default' => true, 'label' => 'Campanha por edição', 'hint' => 'Jogos, vitórias, empates, derrotas, gols e colocação em cada edição publicada.'],
        'team_form' => ['group' => 'Página da equipe', 'default' => true, 'label' => 'Forma recente', 'hint' => 'Resultado dos últimos 5 jogos da equipe.'],
        'team_scorers' => ['group' => 'Página da equipe', 'default' => true, 'label' => 'Artilheiros da equipe', 'hint' => 'Os 5 maiores goleadores, pelo nome público (já é público na artilharia da edição).'],
        'team_calendar' => ['group' => 'Página da equipe', 'default' => true, 'label' => 'Calendário (.ics)', 'hint' => 'Link para a pessoa adicionar os jogos da equipe à agenda do celular.'],
        'team_roster' => ['group' => 'Página da equipe', 'default' => false, 'label' => 'Elenco público', 'hint' => 'Atletas (nome público e camisa) e técnico da edição mais recente. Desligado por padrão; sem foto, nascimento ou documento.'],
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
