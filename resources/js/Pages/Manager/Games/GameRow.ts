export interface GameRow {
    id: string;
    home: boolean;
    my_team: string;
    my_logo: string | null;
    opponent: string | null;
    opponent_logo: string | null;
    my_score: number;
    opponent_score: number;
    status: string;
    status_label: string;
    decided: boolean;
    scheduled_at: string | null;
    venue: string | null;
    competition: string;
    stage: string;
    round: number | null;
    lineup?: 'confirmed' | 'draft' | 'missing' | null;
}
