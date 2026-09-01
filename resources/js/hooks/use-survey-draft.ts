import { useCallback, useEffect, useState } from 'react';

const STORAGE_PREFIX = 'survey_draft_';
const DRAFT_MAX_AGE_MS = 14 * 24 * 60 * 60 * 1000;
const DRAFT_DEBOUNCE_MS = 400;

type DraftAnswers = Record<string, any>;

type DraftPayload = {
    version: string;
    savedAt: number;
    answers: DraftAnswers;
};

const readStorage = (storageKey: string): DraftPayload | null => {
    try {
        const raw = window.localStorage.getItem(storageKey);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as Partial<DraftPayload> | null;
        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        const version = typeof parsed.version === 'string' ? parsed.version : '';
        const savedAt = typeof parsed.savedAt === 'number' ? parsed.savedAt : 0;
        const answers = parsed.answers && typeof parsed.answers === 'object' ? parsed.answers : {};

        if (!version || savedAt === 0) {
            return null;
        }

        if (Date.now() - savedAt > DRAFT_MAX_AGE_MS) {
            window.localStorage.removeItem(storageKey);
            return null;
        }

        return {
            version,
            savedAt,
            answers,
        };
    } catch {
        return null;
    }
};

const writeStorage = (storageKey: string, payload: DraftPayload) => {
    try {
        window.localStorage.setItem(storageKey, JSON.stringify(payload));
    } catch {
        // Modo privado, cuota llena o almacenamiento bloqueado: no rompe la encuesta.
    }
};

const removeStorage = (storageKey: string) => {
    try {
        window.localStorage.removeItem(storageKey);
    } catch {
        // Ignorado por seguridad, la encuesta sigue funcionando.
    }
};

export function useSurveyDraft({
    surveyId,
    version,
    initialAnswers = {},
}: {
    surveyId: number | string | null;
    version?: string | null;
    initialAnswers?: DraftAnswers;
}) {
    const storageKey = surveyId !== null && surveyId !== undefined ? `${STORAGE_PREFIX}${surveyId}` : null;
    const [answers, setAnswers] = useState<DraftAnswers>(initialAnswers);
    const [hydrated, setHydrated] = useState(false);
    const [restored, setRestored] = useState(false);

    const clearDraft = useCallback(() => {
        if (!storageKey) {
            setAnswers({});
            setRestored(false);
            return;
        }

        removeStorage(storageKey);
        setAnswers({});
        setRestored(false);
    }, [storageKey]);

    const discardDraft = useCallback(() => {
        clearDraft();
    }, [clearDraft]);

    useEffect(() => {
        if (!storageKey) {
            setHydrated(true);
            return;
        }

        const saved = readStorage(storageKey);

        if (saved && saved.version === version) {
            setAnswers(saved.answers || {});
            setRestored(true);
        } else {
            if (saved && saved.version !== version) {
                removeStorage(storageKey);
            }
            setAnswers(initialAnswers);
            setRestored(false);
        }

        setHydrated(true);
    }, [initialAnswers, storageKey, version]);

    useEffect(() => {
        if (!hydrated || !storageKey) {
            return;
        }

        const timer = window.setTimeout(() => {
            const payload: DraftPayload = {
                version: version || '',
                savedAt: Date.now(),
                answers,
            };

            if (!version) {
                return;
            }

            if (Object.keys(answers).length === 0) {
                removeStorage(storageKey);
                return;
            }

            writeStorage(storageKey, payload);
        }, DRAFT_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [answers, hydrated, storageKey, version]);

    return {
        answers,
        hydrated,
        restored,
        setAnswers,
        clearDraft,
        discardDraft,
    };
}
