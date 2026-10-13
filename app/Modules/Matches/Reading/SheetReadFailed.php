<?php

namespace App\Modules\Matches\Reading;

use RuntimeException;

/** A leitura não deu certo por um motivo que o usuário entende (mensagem em português, mostrada na tela). */
final class SheetReadFailed extends RuntimeException {}
