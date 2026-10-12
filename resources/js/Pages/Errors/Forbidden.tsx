import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link } from '@inertiajs/react';

export default function Forbidden() {
    return (
        <PanelLayout>
            <Head title="Sem permissão" />
            <div className="mx-auto max-w-md rounded-lg bg-white p-8 text-center shadow-sm ring-1 ring-gray-200">
                <h1 className="text-xl font-bold text-gray-900">Você não tem permissão para isto</h1>
                <p className="mt-3 text-sm text-gray-600">Seu papel não inclui esta área. Se precisar dela, fale com um administrador.</p>
                <Link href={route('panel.dashboard')} className="mt-6 inline-block text-sm font-medium text-emerald-700 hover:underline">
                    Voltar ao painel
                </Link>
            </div>
        </PanelLayout>
    );
}
