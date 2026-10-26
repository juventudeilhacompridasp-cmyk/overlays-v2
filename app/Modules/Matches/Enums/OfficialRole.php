<?php

namespace App\Modules\Matches\Enums;

/** Função de quem trabalha no jogo (arbitragem e mesa). Cada função tem uma pessoa por jogo. */
enum OfficialRole: string
{
    case Referee = 'referee';
    case AssistantReferee1 = 'assistant_referee_1';
    case AssistantReferee2 = 'assistant_referee_2';
    case FourthOfficial = 'fourth_official';
    case Scorer = 'scorer';

    public function label(): string
    {
        return match ($this) {
            self::Referee => 'Árbitro',
            self::AssistantReferee1 => '1º assistente',
            self::AssistantReferee2 => '2º assistente',
            self::FourthOfficial => 'Quarto árbitro',
            self::Scorer => 'Mesário',
        };
    }
}
