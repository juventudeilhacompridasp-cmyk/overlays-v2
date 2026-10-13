import LegalText from '@/Components/LegalText';
import PortalLayout, { Seo, Theme } from '@/Components/Portal/PortalLayout';

interface Props {
    theme: Theme;
    seo: Seo;
    document: { title: string; body: string; version: number | null; published_at: string | null };
    contact_email: string | null;
}

export default function Legal({ theme, seo, document, contact_email }: Props) {
    return (
        <PortalLayout theme={theme} seo={seo}>
            <article className="mx-auto max-w-3xl rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h1 className="text-2xl font-extrabold">{document.title}</h1>
                {document.version !== null && (
                    <p className="mt-1 text-xs text-gray-500">
                        Versão {document.version} · publicada em {document.published_at}
                    </p>
                )}
                <div className="mt-4">
                    <LegalText body={document.body} />
                </div>
                {contact_email && (
                    <p className="mt-6 text-sm text-gray-600">
                        Dúvidas ou pedidos sobre seus dados:{' '}
                        <a className="text-emerald-700 hover:underline" href={`mailto:${contact_email}`}>
                            {contact_email}
                        </a>
                    </p>
                )}
            </article>
        </PortalLayout>
    );
}
