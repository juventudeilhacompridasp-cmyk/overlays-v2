export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    total: number;
    from: number | null;
    to: number | null;
}

export interface Option {
    value: string;
    label: string;
}

export type RulesData = {
    points: { win: number; draw: number; loss: number; walkover_loss: number | null };
    fouls: { max_player: number | null; max_disqualifying: number };
    tiebreakers: string[];
    cards: {
        yellow_for_suspension: number;
        yellow_suspension_games: number;
        red_suspension_games: number;
        reset_yellows_between_stages: boolean;
        second_yellow_counts_for_series: boolean;
    };
    walkover: { winner_goals: number; loser_goals: number };
    substitutions: { max: number; max_windows: number; allow_reentry: boolean };
    lineup: { max_starters: number; max_bench: number; max_staff: number; manager_deadline_minutes: number };
    scoring: { model: string; sets_to_win: number; set_points: number; deciding_set_points: number; min_lead: number; points_narrow_win: number | null; points_narrow_loss: number | null };
};
