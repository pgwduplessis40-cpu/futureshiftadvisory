export type UserType =
    | 'super_admin'
    | 'advisor'
    | 'junior_advisor'
    | 'entrepreneur_mentor'
    | 'client_primary'
    | 'client_team'
    | 'entrepreneur'
    | 'broker'
    | 'coach';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    user_type?: UserType | string | null;
    primary_role?: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
