<?php

namespace App\Exceptions;

use Exception;

/**
 * sites.fflch.usp.br/domains fora do ar, com erro ou sem a lista (ex.: página de manutenção).
 */
class SitesIndisponivelException extends Exception
{
    public const MENSAGEM = 'O sistema de verificação de domínios (sites.fflch.usp.br) está fora do ar no momento. Tente novamente mais tarde.';

    public function __construct(string $message = self::MENSAGEM)
    {
        parent::__construct($message);
    }
}
