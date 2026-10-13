<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Portal\Support\PublicTheme;
use App\Modules\Tenancy\Enums\LegalType;
use App\Modules\Tenancy\Support\LegalDocuments;
use Inertia\Inertia;
use Inertia\Response;

/** Páginas públicas de termos de uso e política de privacidade (a versão publicada ou o texto-modelo). */
class LegalPageController extends Controller
{
    public function terms(): Response
    {
        return $this->page(LegalType::Terms);
    }

    public function privacy(): Response
    {
        return $this->page(LegalType::Privacy);
    }

    private function page(LegalType $type): Response
    {
        $theme = PublicTheme::current();
        $view = LegalDocuments::view($type);

        return Inertia::render('Portal/Legal', [
            'theme' => $theme,
            'seo' => ['title' => "{$type->label()} · {$theme['name']}", 'description' => "{$type->label()} de {$theme['name']}.", 'url' => url($type->path()), 'image' => null],
            'document' => ['title' => $view['title'], 'body' => $view['body'], 'version' => $view['version'], 'published_at' => $view['published_at']],
            'contact_email' => LegalDocuments::settings()['contact_email'],
        ]);
    }
}
