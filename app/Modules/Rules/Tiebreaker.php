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
}
