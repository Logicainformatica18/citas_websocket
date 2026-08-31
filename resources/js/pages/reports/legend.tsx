type Question = { id: number; question: string };

export function ReportLegend({ questions, compact }: { questions: Question[]; compact: boolean }) {
    if (!compact || questions.length === 0) return null;

    return (
        <details className="w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-neutral-800 dark:bg-neutral-900/50">
            <summary className="cursor-pointer font-medium text-slate-700 select-none dark:text-neutral-200">
                Preguntas ({questions.length})
            </summary>

            <ol className="mt-4 grid min-w-0 gap-2 md:grid-cols-2">
                {questions.map((question, index) => (
                    <li key={question.id} className="flex min-w-0 gap-2">
                        <span className="w-8 shrink-0 font-semibold text-slate-500 tabular-nums dark:text-neutral-500">
                            P{index + 1}
                        </span>
                        <span className="min-w-0 break-words text-slate-700 dark:text-neutral-300">
                            {question.question}
                        </span>
                    </li>
                ))}
            </ol>
        </details>
    );
}