import { Badge, Button, Card, Field, Input, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Media { id: string; title: string | null; url: string; provider: string; type: 'video' | 'image'; image_url: string | null; published: boolean }

export default function MediaPage({ game, media }: { game: { id: string; home: string | null; away: string | null }; media: Media[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ title: '', url: '' });
    const imageForm = useForm<{ title: string; file: File | null }>({ title: '', file: null });
    const submit = (event: FormEvent) => { event.preventDefault(); post(route('panel.games.media.store', game.id), { preserveScroll: true, onSuccess: () => reset() }); };

    return <PanelLayout>
        <Head title="Mídia da partida" />
        <PageHeader title={`Mídia · ${game.home ?? 'A definir'} x ${game.away ?? 'A definir'}`} actions={<Link href={route('panel.dashboard')} className="text-sm text-gray-600 hover:underline">Voltar</Link>} />
        <div className="grid gap-6 lg:grid-cols-2">
            <Card title="Adicionar vídeo ou foto">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Título" htmlFor="media-title" error={errors.title}><Input id="media-title" value={data.title} onChange={(event) => setData('title', event.target.value)} placeholder="Replay completo" /></Field>
                    <Field label="Link HTTPS" htmlFor="media-url" error={errors.url} hint="YouTube, Vimeo ou Instagram. O vídeo só carrega depois que o visitante clicar."><Input id="media-url" type="url" value={data.url} onChange={(event) => setData('url', event.target.value)} placeholder="https://youtu.be/..." /></Field>
                    <Button type="submit" disabled={processing || data.url.trim() === ''}>Adicionar para revisão</Button>
                </form>
                <form onSubmit={(event) => { event.preventDefault(); if (imageForm.data.file) imageForm.post(route('panel.games.media.image', game.id), { forceFormData: true, preserveScroll: true, onSuccess: () => imageForm.reset() }); }} className="mt-6 space-y-3 border-t border-gray-200 pt-4">
                    <Field label="Título da foto" htmlFor="media-image-title" error={imageForm.errors.title}><Input id="media-image-title" value={imageForm.data.title} onChange={(event) => imageForm.setData('title', event.target.value)} /></Field>
                    <input type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => imageForm.setData('file', event.target.files?.[0] ?? null)} className="block w-full text-sm" />
                    <Button type="submit" variant="secondary" disabled={imageForm.processing || imageForm.data.file === null}>Adicionar foto para revisão</Button>
                </form>
            </Card>
            <Card title="Mídias da partida">
                {media.length === 0 ? <p className="text-sm text-gray-500">Nenhuma mídia cadastrada.</p> : <ul className="space-y-3">{media.map((item) => <li key={item.id} className="rounded-lg border border-gray-200 p-3"><div className="flex items-start justify-between gap-3">{item.image_url && <img src={item.image_url} alt="" className="h-16 w-24 rounded object-cover" />}<div><strong>{item.title || item.provider}</strong><p className="mt-1 break-all text-xs text-gray-500">{item.type === 'image' ? 'Foto enviada' : item.url}</p></div><Badge tone={item.published ? 'green' : 'amber'}>{item.published ? 'Publicada' : 'Em revisão'}</Badge></div><div className="mt-3 flex gap-2"><Button small variant="secondary" onClick={() => router.patch(route('panel.games.media.toggle', item.id), {}, { preserveScroll: true })}>{item.published ? 'Retirar' : 'Publicar'}</Button><Button small variant="danger" onClick={() => window.confirm('Remover esta mídia?') && router.delete(route('panel.games.media.destroy', item.id), { preserveScroll: true })}>Remover</Button></div></li>)}</ul>}
            </Card>
        </div>
    </PanelLayout>;
}
