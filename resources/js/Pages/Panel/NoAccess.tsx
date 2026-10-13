import { Link, usePage } from '@inertiajs/react';

export default function NoAccess() {
    const { auth } = usePage().props;

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
            <div className="max-w-md rounded-lg bg-white p-8 text-center shadow-sm ring-1 ring-gray-200">
                <h1 className="text-xl font-bold text-gray-900">Sem acesso ao painel</h1>
                {auth.role === 'manager' ? (
                    <p className="mt-3 text-sm text-gray-600">
                        Esta área é do organizador. Como gestor de equipe, use a <Link href={route('manager.dashboard')} className="font-medium text-emerald-700 underline">área do gestor</Link>.
                    </p>
                ) : (
                    <p className="mt-3 text-sm text-gray-600">
                        A conta <strong>{auth.user.email}</strong> ainda não tem permissão nesta organização. Peça a um administrador para liberar o seu acesso.
                    </p>
                )}
                <Link href={route('logout')} method="post" as="button" className="mt-6 rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                    Sair
                </Link>
            </div>
        </div>
    );
}
