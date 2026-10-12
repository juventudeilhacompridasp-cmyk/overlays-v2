<?php

namespace App\Modules\Rules;

/**
 * Critérios de desempate configuráveis, aplicados na ordem definida no regulamento.
 * Os pontos sempre vêm antes de qualquer critério.
 */
enum Tiebreaker: string
{
    case Wins = 'wins';
    case GoalDifference = 'goal_difference';
    case GoalsFor = 'goals_for';
    case GoalsAgainst = 'goals_against';
    /** Confronto direto: só se aplica quando restam exatamente duas equipes empatadas. */
    case HeadToHead = 'head_to_head';
    case FewestRedCards = 'fewest_red_cards';
    case FewestYellowCards = 'fewest_yellow_cards';
    case FewestBlueCards = 'fewest_blue_cards';
    /** Sorteio: não é calculável; o empate é sinalizado como não resolvido. */
    case Draw = 'draw';

    public function label(): string
    {
        return match ($this) {
            self::Wins => 'Mais vitórias',
            self::GoalDifference => 'Maior saldo de gols',
            self::GoalsFor => 'Mais gols marcados',
            self::GoalsAgainst => 'Menos gols sofridos',
            self::HeadToHead => 'Confronto direto (só entre duas equipes)',
            self::FewestRedCards => 'Menos cartões vermelhos',
            self::FewestYellowCards => 'Menos cartões amarelos',
            self::FewestBlueCards => 'Menos cartões azuis',
            self::Draw => 'Sorteio',
        };
    }
}
