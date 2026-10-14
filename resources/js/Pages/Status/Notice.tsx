import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    title: string;
    message: string;
    action: { label: string; url: string } | null;
    status_url: string;
}

/** Resposta simples da status page (confirmar ou cancelar avisos). Nunca indexada. */
export default function Notice({ title, message, action, status_url }: Props) {
    const { post, processing } = useForm({});
    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (action) post(action.url);
    };

    return (
        <div className="grid min-h-screen place-items-center bg-gray-50 px-4 text-gray-900">
            <Head title={title}>
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <main className="w-full max-w-md rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h1 className="text-xl font-extrabold">{title}</h1>
                <p className="mt-2 text-sm text-gray-600">{message}</p>
                {action && (
                    <form onSubmit={submit} className="mt-5">
                        <button type="submit" disabled={processing} className="rounded-md bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60">
                            {action.label}
                        </button>
                    </form>
                )}
                <p className="mt-5 text-sm">
                    <Link href={status_url} className="font-medium text-emerald-700 hover:underline">
                        Voltar para a status page
                    </Link>
                </p>
            </main>
        </div>
    );
}
