export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type TokenUsage = {
    input_text_tokens: number;
    input_image_tokens: number;
    output_tokens: number;
    reasoning_tokens: number;
    total_tokens?: number;
};

export type Generation = {
    id: string;
    status: GenerationStatus;
    waiting_for_capacity: boolean;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    estimated_charge: string;
    max_charge: string;
    actual_charge: string | null;
    usage: TokenUsage | null;
    image_url: string | null;
    image_url_expired: boolean;
    error: { code: string | null; message: string | null } | null;
    next_attempt_at: string | null;
    created_at: string;
    completed_at: string | null;
};

export type Estimate = {
    tokens: TokenUsage;
    min_charge: string;
    expected_charge: string;
    max_charge: string;
    rates: Record<Exclude<keyof TokenUsage, 'total_tokens'>, string>;
    reasoning_range: { min: number; max: number };
    text_token_variance: number;
};

export type StudioOptions = {
    sizes: string[];
    qualities: string[];
    max_prompt_length: number;
    max_reference_images: number;
    markup_percent: number;
};
