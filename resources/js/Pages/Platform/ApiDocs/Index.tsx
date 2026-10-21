import DataTable from '@/Components/DataTable';
import Modal from '@/Components/Modal';
import { Badge, Button, Card, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

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
    version: string;
    changelog: { version: string; date: string; changes: string[] }[];
}

const methodTone = (method: string): 'green' | 'blue' | 'amber' | 'red' | 'gray' =>
    method === 'GET' ? 'green' : method === 'POST' ? 'blue' : method === 'DELETE' ? 'red' : 'amber';

function Json({ value }: { value: unknown }) {
    return <pre className="max-h-80 overflow-auto rounded-md bg-neutral-900 p-3 text-xs text-neutral-100">{JSON.stringify(value, null, 2)}</pre>;
}

function Detail({ operation, onClose }: { operation: Operation; onClose: () => void }) {
    return (
        <Modal show onClose={onClose} maxWidth="2xl">
            <div className="p-6">
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <Badge tone={methodTone(operation.method)}>{operation.method}</Badge>
                    <code className="break-all text-sm text-gray-900">{operation.path}</code>
                </div>
                <p className="mb-3 text-sm text-gray-600">{operation['x-documented'] ? operation.summary : <em className="text-gray-400">sem descrição</em>}</p>
        <div className="space-y-3 text-sm text-gray-800">
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
                <div className="mt-4 flex justify-end">
                    <Button variant="secondary" onClick={onClose}>
                        Fechar
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

export default function Index({ tags, operations, documented, version, changelog }: Props) {
    const [detail, setDetail] = useState<Operation | null>(null);
    const group = (operation: Operation) => operation.tags[0] ?? 'Sem grupo';

    return (
        <PanelLayout>
            <Head title="Documentação das APIs" />
            <PageHeader
                title="Documentação das APIs"
                subtitle={`Referência interna gerada das rotas registradas, versão ${version} (${operations.length} operações; ${documented} descritas). Só o Super Admin vê esta página.`}
                actions={
                    <>
                        <LinkButton href={route('platform.api-docs.openapi')}>Baixar OpenAPI (JSON)</LinkButton>
                        <LinkButton href={route('platform.api-docs.postman')}>Baixar coleção do Postman</LinkButton>
                    </>
                }
            />

            <div className="max-w-6xl space-y-4">
                <div data-testid="operations">
                    <DataTable
                        caption="Rotas da plataforma"
                        searchPlaceholder="Buscar por caminho, nome ou descrição"
                        rows={operations}
                        rowKey={(operation) => `${operation.method} ${operation.path}`}
                        pageSize={25}
                        persistKey="platform:api-docs"
                        empty="Nenhuma rota encontrada."
                        columns={[
                            {
                                key: 'path',
                                header: 'Caminho',
                                primary: true,
                                value: (operation) => `${operation.path} ${operation['x-name'] ?? ''}`,
                                cell: (operation) => (
                                    <span data-testid="operation">
                                        <code className="break-all text-sm text-gray-900">{operation.path}</code>
                                    </span>
                                ),
                            },
                            { key: 'method', header: 'Método', filter: true, value: (operation) => operation.method, cell: (operation) => <Badge tone={methodTone(operation.method)}>{operation.method}</Badge> },
                            { key: 'group', header: 'Grupo', filter: tags.map((tag) => ({ value: tag, label: tag })), hideBelow: 'lg', value: group, cell: (operation) => <span className="text-gray-600">{group(operation)}</span> },
                            { key: 'access', header: 'Acesso', filter: true, hideBelow: 'xl', value: (operation) => operation['x-access'], cell: (operation) => <span className="text-gray-600">{operation['x-access']}</span> },
                            {
                                key: 'summary',
                                header: 'Descrição',
                                filter: [
                                    { value: 'sim', label: 'Descritas' },
                                    { value: 'nao', label: 'Sem descrição' },
                                ],
                                value: (operation) => (operation['x-documented'] ? 'sim' : 'nao'),
                                searchable: false,
                                cell: (operation) => (operation['x-documented'] ? <span className="text-sm text-gray-700">{operation.summary}</span> : <em className="text-gray-400">sem descrição</em>),
                            },
                            {
                                key: 'details',
                                header: 'Detalhes',
                                align: 'right',
                                mobileLabel: false,
                                cell: (operation) => (
                                    <Button small variant="secondary" onClick={() => setDetail(operation)}>
                                        Detalhes
                                    </Button>
                                ),
                            },
                        ]}
                    />
                </div>

                <Card title="Histórico de mudanças">
                    <ol className="space-y-3">
                        {changelog.map((entry) => (
                            <li key={entry.version}>
                                <p className="text-sm font-semibold text-gray-900">
                                    {entry.version} <span className="font-normal text-gray-500">· {entry.date}</span>
                                </p>
                                <ul className="mt-1 list-disc space-y-0.5 pl-5 text-sm text-gray-700">
                                    {entry.changes.map((change) => (
                                        <li key={change}>{change}</li>
                                    ))}
                                </ul>
                            </li>
                        ))}
                    </ol>
                </Card>
            </div>

            {detail && <Detail operation={detail} onClose={() => setDetail(null)} />}
        </PanelLayout>
    );
}
