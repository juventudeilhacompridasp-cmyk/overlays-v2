<?php

namespace App\Modules\Status\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Status\Actions\SubscriberActions;
use App\Modules\Status\Http\Requests\SubscribeRequest;
use App\Modules\Status\Support\StatusBadge;
use App\Modules\Status\Support\StatusSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/** Página pública de status da plataforma (sem login, sem organização no conteúdo): HTML, JSON e feed Atom. */
class StatusPageController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Status/Index', ['status' => StatusSummary::get(), 'json_url' => route('status.json')]);
    }

    /** Pedido de inscrição nos avisos por e-mail. A resposta é sempre a mesma: não revela se o e-mail já está na lista. */
    public function subscribe(SubscribeRequest $request, SubscriberActions $subscribers): RedirectResponse
    {
        $subscribers->request($request->validated('email'));

        return redirect()->route('status.index')->with('success', 'Se o e-mail for válido, enviamos um link de confirmação. Os avisos só começam depois que você confirmar.');
    }

    public function confirm(string $token, SubscriberActions $subscribers): InertiaResponse
    {
        return $subscribers->confirm($token)
            ? $this->notice('Inscrição confirmada', 'Pronto! Você vai receber um e-mail quando houver incidente ou manutenção. Pelos links no final de cada aviso dá para escolher quais componentes acompanhar ou cancelar.')
            : $this->notice('Link inválido ou expirado', 'Não encontramos esta inscrição. Faça o pedido de novo na status page.');
    }

    /** Cancelar é em duas etapas (GET mostra o botão, POST cancela) para que leitores de e-mail não cancelem sozinhos ao abrir o link. */
    public function unsubscribeShow(string $token, SubscriberActions $subscribers): InertiaResponse
    {
        return $subscribers->exists($token)
            ? $this->notice('Cancelar avisos de status', 'Clique no botão para parar de receber avisos de incidentes e manutenções.', ['label' => 'Cancelar avisos', 'url' => route('status.unsubscribe', $token)])
            : $this->notice('Inscrição não encontrada', 'Este link já foi usado ou a inscrição não existe mais.');
    }

    public function unsubscribe(string $token, SubscriberActions $subscribers): InertiaResponse
    {
        $subscribers->cancel($token);

        return $this->notice('Avisos cancelados', 'Seu e-mail foi removido da lista. Você pode voltar a se inscrever quando quiser na status page.');
    }

    /** Escolha dos componentes que a pessoa acompanha (link no final de cada aviso; o token é o acesso). */
    public function preferencesShow(string $token, SubscriberActions $subscribers): InertiaResponse
    {
        $components = $subscribers->preferences($token);

        if ($components === null) {
            return $this->notice('Inscrição não encontrada', 'Este link não vale mais ou a inscrição ainda não foi confirmada.');
        }

        return Inertia::render('Status/Preferences', ['components' => $components, 'save_url' => route('status.preferences', $token), 'status_url' => route('status.index')]);
    }

    public function preferencesUpdate(string $token, Request $request, SubscriberActions $subscribers): RedirectResponse
    {
        $validated = $request->validate(['components' => ['required', 'array', 'min:1'], 'components.*' => ['string', 'max:40']], ['components.required' => 'Escolha pelo menos um componente.', 'components.min' => 'Escolha pelo menos um componente.']);

        abort_unless($subscribers->savePreferences($token, array_values($validated['components'])), 404);

        return redirect()->route('status.preferences.show', $token)->with('success', 'Preferências salvas.');
    }

    /** Selo "status | operacional" em SVG, para <img> em qualquer site (cache curto, aberto a qualquer origem). */
    public function badge(): Response
    {
        return response(StatusBadge::svg((string) StatusSummary::get()['overall']), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param  array{label: string, url: string}|null  $action */
    private function notice(string $title, string $message, ?array $action = null): InertiaResponse
    {
        return Inertia::render('Status/Notice', ['title' => $title, 'message' => $message, 'action' => $action, 'status_url' => route('status.index')]);
    }

    public function json(): JsonResponse
    {
        return response()->json(StatusSummary::get(), 200, [
            'Cache-Control' => 'public, max-age=30',
            'Access-Control-Allow-Origin' => '*',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Feed Atom dos incidentes e manutenções recentes (um item por incidente; o conteúdo é a linha do tempo). */
    public function atom(): Response
    {
        $status = StatusSummary::get();
        $items = [...$status['incidents'], ...$status['maintenance']];
        usort($items, fn (array $a, array $b) => strcmp($b['updates'][0]['at'] ?? $b['starts_at'], $a['updates'][0]['at'] ?? $a['starts_at']));

        $e = fn (string $value) => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<feed xmlns="http://www.w3.org/2005/Atom">'."\n";
        $xml .= '  <title>'.$e(config('app.name').' · Status').'</title>'."\n";
        $xml .= '  <id>'.$e(route('status.index')).'</id>'."\n";
        $xml .= '  <link rel="self" href="'.$e(route('status.atom')).'"/>'."\n";
        $xml .= '  <link rel="alternate" href="'.$e(route('status.index')).'"/>'."\n";
        $xml .= '  <updated>'.$e($items[0]['updates'][0]['at'] ?? $status['updated_at']).'</updated>'."\n";

        foreach ($items as $item) {
            $body = implode("\n", array_map(fn (array $update) => '['.$update['state_label'].'] '.$update['body'], $item['updates']));
            $xml .= "  <entry>\n";
            $xml .= '    <title>'.$e($item['title'].' — '.$item['state_label']).'</title>'."\n";
            $xml .= '    <id>'.$e(route('status.index').'#'.$item['id']).'</id>'."\n";
            $xml .= '    <link rel="alternate" href="'.$e(route('status.index')).'"/>'."\n";
            $xml .= '    <updated>'.$e($item['updates'][0]['at'] ?? $item['starts_at']).'</updated>'."\n";
            $xml .= '    <summary type="text">'.$e($body).'</summary>'."\n";
            $xml .= "  </entry>\n";
        }

        return response($xml.'</feed>', 200, ['Content-Type' => 'application/atom+xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=60']);
    }
}
