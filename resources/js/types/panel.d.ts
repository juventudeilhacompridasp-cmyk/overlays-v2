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
    current_page: number;
    last_page: number;
    per_page: number;
}

/** Estado de uma lista dinâmica no servidor (`App\Support\Tables\TableQuery::props`). */
export interface TableFilters {
    q: string;
    sort: string;
    dir: 'asc' | 'desc';
    per_page: number;
    values: Record<string, string>;
}

export interface Option {
    value: string;
    label: string;
}

/** O que o perfil do esporte define e a edição pode ajustar (duração dos períodos, faltas de equipe, pedidos de tempo). */
export type SportRules = {
    period_minutes: number[];
    period_labels: string[];
    team_foul_limit: number | null;
    timeout_limits: Record<string, number> | null;
    timeout_labels: Record<string, string>;
};

export type RulesData = {
    points: { win: number; draw: number; loss: number; walkover_loss: number | null };
    fouls: { max_player: number | null; max_disqualifying: number; team_limit: number | null };
    periods: { minutes: number[] | null };
    results: { quick_allowed: boolean };
    timeouts: { limits: Record<string, number> | null };
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
    schedule: { venue_minutes: number; team_rest_minutes: number };
    documents: { medical_required: boolean; warn_days: number };
    scoring: { model: string; sets_to_win: number; set_points: number; deciding_set_points: number; min_lead: number; points_narrow_win: number | null; points_narrow_loss: number | null };
};
