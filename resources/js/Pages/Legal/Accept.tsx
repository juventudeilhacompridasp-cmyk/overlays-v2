import LegalText from '@/Components/LegalText';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    documents: { title: string; version: number; published_at: string; body: string }[];
}

export default function Accept({ documents }: Props) {
    const { data, setData, post, processing, errors } = useForm({ accept: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('legal.accept.store'));
    };

    return (
        <GuestLayout>
            <Head title="Aceite dos termos" />
            <h1 className="mb-1 text-lg font-semibold text-gray-900">Termos atualizados</h1>
            <p className="mb-4 text-sm text-gray-600">Para continuar usando o painel, leia e aceite {documents.length === 1 ? 'o documento abaixo' : 'os documentos abaixo'}.</p>
            <div className="max-h-80 space-y-6 overflow-y-auto rounded-md border border-gray-200 p-4">
                {documents.map((document) => (
                    <section key={document.title}>
                        <h2 className="text-base font-bold text-gray-900">{document.title}</h2>
                        <p className="mb-2 text-xs text-gray-500">
                            Versão {document.version} · {document.published_at}
                        </p>
                        <LegalText body={document.body} />
                    </section>
                ))}
            </div>
            <form onSubmit={submit} className="mt-4 space-y-3">
                <label className="flex items-start gap-2 text-sm text-gray-800">
                    <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-emerald-600" checked={data.accept} onChange={(e) => setData('accept', e.target.checked)} />
                    Li e aceito {documents.length === 1 ? 'o documento acima' : 'os documentos acima'}.
                </label>
                {errors.accept && <p className="text-sm text-red-600">{errors.accept}</p>}
                <div className="flex items-center justify-between">
                    <button type="submit" disabled={processing || !data.accept} className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                        Aceitar e continuar
                    </button>
                    <Link href={route('logout')} method="post" as="button" className="text-sm text-gray-500 hover:text-gray-700">
                        Sair
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
