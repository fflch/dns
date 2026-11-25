<?php

return [

    // Sufixo fixo de todos os domínios pedidos: "abc" vira "abc.fflch.usp.br"
    'sufixo' => env('DNS_SUFIXO', 'fflch.usp.br'),

    // Lista de domínios já em uso pelo sistema de sites (mesmo formato de /domains)
    'sites_url' => env('DNS_SITES_URL', 'https://sites.fflch.usp.br/domains'),

    'sites_timeout' => (int) env('DNS_SITES_TIMEOUT', 10),

    // Proxies reversos confiáveis (ex.: Traefik), para registrar o IP real na auditoria.
    // "*" ou redes CIDR separadas por vírgula. Vazio = não confiar em proxy.
    'proxies_confiaveis' => env('TRUSTED_PROXIES') == '*'
        ? '*'
        : array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

];
