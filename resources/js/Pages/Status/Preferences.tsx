import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    components: { key: string; name: string; selected: boolean }[];
    save_url: string;
    status_url: string;
}

/** Escolha dos componentes da status page que a pessoa quer acompanhar (o token do link é o acesso). Nunca indexada. */
export default function Preferences({ components, save_url, status_url }: Props) {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ components: components.filter((component) => component.selected).map((component) => component.key) });

    const toggle = (key: string, on: boolean) => setData('components', on ? [...data.components, key] : data.components.filter((item) => item !== key));
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(save_url, { preserveScroll: true });
    };

    return (
        <div className="grid min-h-screen place-items-center bg-gray-50 px-4 text-gray-900">
            <Head title="Preferências dos avisos de status">
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <main className="w-full max-w-md rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h1 className="text-xl font-extrabold">O que você quer acompanhar</h1>
                <p className="mt-2 text-sm text-gray-600">Você só recebe aviso de incidentes e manutenções que afetem os componentes marcados. Avisos gerais (sem componente) chegam sempre.</p>
                {flash?.success && <p role="status" className="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-emerald-200">{flash.success}</p>}
                <form onSubmit={submit} className="mt-4 space-y-2">
                    {components.map((component) => (
                        <label key={component.key} className="flex items-center gap-2 text-sm text-gray-800">
                            <input type="checkbox" className="rounded border-gray-300 text-emerald-600" checked={data.components.includes(component.key)} onChange={(event) => toggle(component.key, event.target.checked)} />
                            {component.name}
                        </label>
                    ))}
                    {errors.components && <p className="text-sm text-red-600">{errors.components}</p>}
                    <button type="submit" disabled={processing} className="mt-3 rounded-md bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">
                        Salvar
                    </button>
                </form>
                <p className="mt-5 text-sm">
                    <Link href={status_url} className="font-medium text-emerald-700 hover:underline">
                        Voltar para a status page
                    </Link>
                </p>
            </main>
        </div>
    );
}
