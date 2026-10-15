<?php

namespace App\Modules\Help\Support;

use RuntimeException;

/** O serviço de IA não respondeu ou respondeu algo que não dá para usar; a mensagem é segura para mostrar. */
final class HelpAnswerFailed extends RuntimeException {}
