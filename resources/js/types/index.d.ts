import { Config } from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        role: string | null;
        role_label: string | null;
        can: Record<string, boolean>;
    };
    flash: {
        success?: string | null;
        error?: string | null;
    };
    ziggy: Config & { location: string };
};
