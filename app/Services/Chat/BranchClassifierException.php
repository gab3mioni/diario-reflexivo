<?php

namespace App\Services\Chat;

use RuntimeException;

/**
 * Exceção lançada quando o classificador de ramificação da IA falha ao produzir uma classificação válida.
 */
class BranchClassifierException extends RuntimeException
{
}
