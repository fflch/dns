<?php

namespace App\Http\Requests;

use App\Exceptions\SitesIndisponivelException;
use App\Models\Dominio;
use App\Services\SitesFflch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class DominioRequest extends FormRequest
{
    /**
     * Cada parte: letras, números e hífen (não no início nem no fim), até 63 caracteres
     */
    public const REGEX_NOME = '/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))*$/';

    public function authorize(): bool
    {
        return Gate::allows('solicitante');
    }

    /**
     * Aceita "ABC", "abc.fflch.usp.br" ou "abc.fflch.usp.br.": guarda só "abc"
     */
    protected function prepareForValidation(): void
    {
        $nome = Str::of((string) $this->nome)->trim()->lower()->rtrim('.');
        $nome = $nome->replaceEnd('.'.config('dns.sufixo'), '');

        $this->merge([
            'nome' => (string) $nome,
            'ip' => trim((string) $this->ip),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'max:'.(253 - strlen('www..'.config('dns.sufixo'))), 'regex:'.self::REGEX_NOME],
            'ip' => ['required', 'ipv4'],
            'motivo' => ['required', 'min:10', 'max:1000'],
        ];
    }

    /**
     * Depois das regras básicas: sem www, não reservado aqui e não em uso em sites.fflch.usp.br
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('nome')) {
                    return;
                }

                $nome = $this->nome;
                $fqdn = $nome.'.'.config('dns.sufixo');

                if ($nome == 'www' || str_starts_with($nome, 'www.')) {
                    $validator->errors()->add('nome', 'Não inclua "www": o domínio é publicado automaticamente com e sem www.');

                    return;
                }

                if (Dominio::nomeJaSolicitado($nome)) {
                    $validator->errors()->add('nome', "O domínio {$fqdn} já tem uma solicitação neste sistema. Para pedi-lo de novo, fale com os administradores.");

                    return;
                }

                try {
                    if (app(SitesFflch::class)->emUso($fqdn)) {
                        $validator->errors()->add('nome', "O domínio {$fqdn} já está em uso em sites.fflch.usp.br.");
                    }
                } catch (SitesIndisponivelException $e) {
                    $validator->errors()->add('nome', $e->getMessage());
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o domínio.',
            'nome.max' => 'O domínio é longo demais.',
            'nome.regex' => 'Domínio inválido. Use letras minúsculas, números, hífen e ponto (ex.: abc ou abc.departamento).',
            'ip.required' => 'Informe o IP de apontamento.',
            'ip.ipv4' => 'Informe um endereço IPv4 válido (ex.: 200.144.244.59).',
            'motivo.required' => 'O motivo é obrigatório.',
            'motivo.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo.max' => 'O motivo deve ter no máximo 1000 caracteres.',
        ];
    }
}
