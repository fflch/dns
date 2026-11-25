<?php

namespace App\Services;

use App\Exceptions\SitesIndisponivelException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Domínios já em uso em sites.fflch.usp.br/domains. Cada linha:
 *   abc.fflch.usp.br. IN A 200.144.244.59
 */
class SitesFflch
{
    /**
     * Domínios em uso (minúsculos, sem o ponto final).
     *
     * @return array<int, string>
     *
     * @throws SitesIndisponivelException fora do ar, erro HTTP ou resposta sem a lista
     */
    public function dominios(): array
    {
        try {
            $response = Http::timeout(config('dns.sites_timeout'))->get(config('dns.sites_url'));
        } catch (ConnectionException) {
            throw new SitesIndisponivelException;
        }

        if (! $response->successful()) {
            throw new SitesIndisponivelException;
        }

        preg_match_all('/^\s*(\S+?)\.?\s+IN\s+A\s+\S+\s*$/mi', $response->body(), $matches);

        // Página de manutenção ou qualquer coisa que não seja a lista
        if (empty($matches[1])) {
            throw new SitesIndisponivelException;
        }

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * O domínio (FQDN) ou a versão com www já está em uso em sites.fflch.usp.br?
     *
     * @throws SitesIndisponivelException
     */
    public function emUso(string $fqdn): bool
    {
        $dominios = $this->dominios();

        return in_array($fqdn, $dominios) || in_array('www.'.$fqdn, $dominios);
    }
}
