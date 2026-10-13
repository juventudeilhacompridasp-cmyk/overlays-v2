import { Badge, Card, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Parameter {
    name: string;
    description: string | null;
}

interface Response {
    description: string;
    schema?: unknown;
    example?: unknown;
}

interface Operation {
    method: string;
    path: string;
    summary: string;
    description?: string;
    tags: string[];
    parameters: Parameter[];
    requestBody?: { content: Record<string, { schema: unknown; example: unknown }> };
    responses: Record<string, Response>;
    'x-name': string | null;
    'x-documented': boolean;
    'x-access': string;
    'x-middleware': string[];
    'x-feature': string | null;
    'x-abilities': string[];
    'x-throttle': string | null;
}

interface Props {
    tags: string[];
    operations: Operation[];
    documented: number;
}

const methodTone = (method: string): 'green' | 'blue' | 'amber' | 'red' | 'gray' =>
    method === 'GET' ? 'green' : method === 'POST' ? 'blue' : method === 'DELETE' ? 'red' : 'amber';

function Json({ value }: { value: unknown }) {
    return <pre className="max-h-80 overflow-auto rounded-md bg-neutral-900 p-3 text-xs text-neutral-100">{JSON.stringify(value, null, 2)}</pre>;
}

function Row({ operation }: { operation: Operation }) {
    const [open, setOpen] = useState(false);

    return (
        <li className="py-2" data-testid="operation">
            <button type="button" onClick={() => setOpen(!open)} className="flex w-full flex-wrap items-center gap-2 text-left" aria-expanded={open}>
                <Badge tone={methodTone(operation.method)}>{operation.method}</Badge>
                <code className="break-all text-sm text-gray-900">{operation.path}</code>
                <span className="text-sm text-gray-600">{operation['x-documented'] ? operation.summary : <em className="text-gray-400">sem descrição</em>}</span>
            </button>

            {open && (
                <div className="mt-3 space-y-3 pl-1 text-sm text-gray-800">
                    {operation.description && <p>{operation.description}</p>}

                    <dl className="grid gap-x-4 gap-y-1 sm:grid-cols-[9rem_1fr]">
                        <dt className="text-gray-500">Acesso</dt>
                        <dd>{operation['x-access']}</dd>
                        {operation['x-feature'] && (
                            <>
                                <dt className="text-gray-500">Recurso do pacote</dt>
                                <dd><code>{operation['x-feature']}</code></dd>
                            </>
                        )}
                        {operation['x-abilities'].length > 0 && (
                            <>
                                <dt className="text-gray-500">Permissão</dt>
                                <dd>{operation['x-abilities'].map((ability) => <code key={ability} className="mr-1">{ability}</code>)}</dd>
                            </>
                        )}
                        {operation['x-throttle'] && (
                            <>
                                <dt className="text-gray-500">Limite</dt>
                                <dd>{operation['x-throttle']} (requisições, minutos)</dd>
                            </>
                        )}
                        {operation['x-name'] && (
                            <>
                                <dt className="text-gray-500">Nome da rota</dt>
                                <dd><code>{operation['x-name']}</code></dd>
                            </>
                        )}
                        {operation['x-middleware'].length > 0 && (
                            <>
                                <dt className="text-gray-500">Middleware</dt>
                                <dd className="break-all">{operation['x-middleware'].join(', ')}</dd>
                            </>
                        )}
                    </dl>

                    {operation.parameters.length > 0 && (
                        <div>
                            <h4 className="font-medium text-gray-900">Parâmetros de caminho</h4>
                            <ul className="list-disc pl-5">
                                {operation.parameters.map((parameter) => (
                                    <li key={parameter.name}>
                                        <code>{parameter.name}</code>
                                        {parameter.description ? ` — ${parameter.description}` : ''}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {operation.requestBody && (
                        <div>
                            <h4 className="font-medium text-gray-900">Corpo da requisição</h4>
                            {Object.entries(operation.requestBody.content).map(([type, body]) => (
                                <div key={type} className="space-y-2">
                                    <code className="text-xs text-gray-500">{type}</code>
                                    <Json value={body.example ?? body.schema} />
                                </div>
                            ))}
                        </div>
                    )}

                    <div>
                        <h4 className="font-medium text-gray-900">Respostas</h4>
                        <ul className="space-y-2">
                            {Object.entries(operation.responses).map(([status, response]) => (
                                <li key={status}>
                                    <code className="font-medium">{status}</code> — {response.description}
                                    {response.example !== undefined && response.example !== null && <Json value={response.example} />}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            )}
        </li>
    );
}

export default function Index({ tags, operations, documented }: Props) {
    const [query, setQuery] = useState('');
    const [onlyDocumented, setOnlyDocumented] = useState(false);

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        return operations.filter(
            (operation) =>
                (!onlyDocumented || operation['x-documented']) &&
                (term === '' || `${operation.method} ${operation.path} ${operation.summary} ${operation['x-name'] ?? ''}`.toLowerCase().includes(term)),
        );
    }, [operations, query, onlyDocumented]);

    return (
        <PanelLayout>
            <Head title="Documentação das APIs" />
            <PageHeader
                title="Documentação das APIs"
                subtitle={`Referência interna gerada das rotas registradas (${operations.length} operações; ${documented} descritas). Só o Super Admin vê esta página.`}
                actions={<LinkButton href={route('platform.api-docs.openapi')}>Baixar OpenAPI (JSON)</LinkButton>}
            />

            <div className="mb-4 flex max-w-3xl flex-wrap items-center gap-3">
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Buscar por caminho, nome ou descrição"
                    aria-label="Buscar rotas"
                    className="min-w-64 flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={onlyDocumented} onChange={(e) => setOnlyDocumented(e.target.checked)} />
                    Só as descritas
                </label>
            </div>

            <div className="max-w-5xl space-y-4">
                {tags.map((tag) => {
                    const items = filtered.filter((operation) => operation.tags[0] === tag);

                    return items.length === 0 ? null : (
                        <Card key={tag} title={`${tag} (${items.length})`}>
                            <ul className="divide-y divide-gray-100">
                                {items.map((operation) => (
                                    <Row key={`${operation.method} ${operation.path}`} operation={operation} />
                                ))}
                            </ul>
                        </Card>
                    );
                })}
                {filtered.length === 0 && <p className="text-sm text-gray-500">Nenhuma rota encontrada.</p>}
            </div>
        </PanelLayout>
    );
}
