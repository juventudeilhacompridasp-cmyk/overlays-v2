<?php

namespace App\Modules\Help\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Help\Models\HelpQuestion;
use App\Modules\Help\Support\HelpAccess;
use App\Modules\Help\Support\HelpAiSettings;
use App\Modules\Help\Support\HelpAnswerFailed;
use App\Modules\Help\Support\HelpAnswerer;
use App\Modules\Help\Support\HelpArticle;
use App\Modules\Help\Support\HelpLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Perguntar à ajuda" (ADR 0006, parte 3): resposta com IA a partir dos artigos que a pessoa pode ver. Recurso sob demanda,
 * interruptor da organização e limites diários/mensais; pergunta sem base na documentação vira lacuna para os administradores.
 */
class HelpAskController extends Controller
{
    public function __invoke(Request $request, HelpAnswerer $answerer): JsonResponse
    {
        $area = (string) $request->route('area');
        abort_unless(in_array($area, ['panel', 'manager'], true), 404);

        if (! HelpAiSettings::available()) {
            return response()->json(['message' => 'As respostas com IA não estão ativas nesta organização.'], 403);
        }

        $validated = $request->validate(['question' => ['required', 'string', 'min:5', 'max:'.HelpAnswerer::MAX_QUESTION]], [], ['question' => 'pergunta']);
        $user = $request->user();
        abort_if($user === null, 403);

        if (HelpAiSettings::remaining($user) === 0) {
            return response()->json(['message' => 'O limite de perguntas à ajuda foi atingido (por pessoa no dia ou da organização no mês). Use a busca por enquanto.', 'remaining' => 0], 429);
        }

        $visible = HelpAccess::visible([...HelpLibrary::all()], $user, $area);

        try {
            $result = $answerer->answer($validated['question'], $visible);
        } catch (HelpAnswerFailed $exception) {
            // A tentativa que chegou ao provedor conta nos limites, mesmo que a resposta tenha falhado.
            HelpQuestion::query()->create(['user_id' => $user->getKey(), 'charged' => true, 'answered' => false, 'question' => null]);

            return response()->json(['message' => $exception->getMessage(), 'remaining' => HelpAiSettings::remaining($user)], 502);
        }

        $answered = $result['answer'] !== null;

        HelpQuestion::query()->create([
            'user_id' => $user->getKey(),
            'charged' => $result['asked'],
            'answered' => $answered,
            'question' => $answered ? null : mb_substr(HelpAnswerer::scrub($validated['question']), 0, HelpAnswerer::MAX_QUESTION),
        ]);

        return response()->json([
            'answer' => $result['answer'],
            'sources' => array_map(fn (HelpArticle $article) => ['slug' => $article->slug, 'title' => $article->title, 'url' => route("{$area}.help.show", $article->slug)], $result['sources']),
            'remaining' => HelpAiSettings::remaining($user),
            'message' => $answered ? null : 'Não encontrei isso na documentação. Tente outras palavras na busca; sua pergunta foi anotada para melhorarmos a ajuda.',
        ]);
    }
}
