/** Mostra um texto legal simples: linhas "# Título" viram subtítulos, "- item" viram lista e o resto vira parágrafo (nunca HTML). */
export default function LegalText({ body }: { body: string }) {
    const blocks = body.split(/\n{2,}/);

    return (
        <div className="space-y-3 text-sm leading-relaxed text-gray-800">
            {blocks.map((block, index) => {
                const lines = block.split('\n');
                const heading = lines[0].startsWith('# ') ? lines.shift()!.slice(2) : null;
                const items = lines.length > 0 && lines.every((line) => line.startsWith('- '));

                return (
                    <div key={index} className="space-y-1">
                        {heading && <h2 className="pt-2 text-base font-semibold text-gray-900">{heading}</h2>}
                        {items ? (
                            <ul className="list-inside list-disc space-y-0.5">
                                {lines.map((line, i) => (
                                    <li key={i}>{line.slice(2)}</li>
                                ))}
                            </ul>
                        ) : (
                            lines.length > 0 && <p className="whitespace-pre-line">{lines.join('\n')}</p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
