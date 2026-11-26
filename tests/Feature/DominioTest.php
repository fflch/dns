<?php

namespace Tests\Feature;

use App\Mail\DominioExcluidoMail;
use App\Mail\DominioMail;
use App\Models\Dominio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DominioTest extends TestCase
{
    use RefreshDatabase;

    private mixed $sitesResposta;

    private const SITES = "abc.fflch.usp.br. IN A 200.144.244.59\nwww.abc.fflch.usp.br. IN A 200.144.244.59\nwww.sowww.fflch.usp.br. IN A 200.144.255.47\n";

    protected function setUp(): void
    {
        parent::setUp();

        foreach (array_merge(User::$permissoesHierarquia, User::$permissoesVinculo) as $permissao) {
            Permission::findOrCreate($permissao, User::$hierarquiaNs);
        }

        config(['dns.sufixo' => 'fflch.usp.br', 'dns.sites_url' => 'https://sites.test/domains']);
        $this->sites(Http::response(self::SITES));
        Mail::fake();
    }

    /**
     * Resposta de sites.fflch.usp.br/domains (pode ser trocada no meio do teste)
     */
    private function sites(mixed $resposta): void
    {
        $this->sitesResposta = $resposta;
        Http::fake(['sites.test/*' => fn () => $this->sitesResposta]);
    }

    private function usuario(string ...$permissoes): User
    {
        $user = User::factory()->create();
        foreach ($permissoes as $permissao) {
            $user->givePermissionTo(Permission::findByName($permissao, User::$hierarquiaNs));
        }

        return $user;
    }

    /**
     * @param  array<string, string>  $dados
     * @return array<string, string>
     */
    private function pedido(array $dados = []): array
    {
        return array_merge(['nome' => 'novo', 'ip' => '200.144.244.10', 'motivo' => 'Site do projeto de pesquisa'], $dados);
    }

    public function test_servidor_solicita_dominio_e_todos_os_envolvidos_recebem_email(): void
    {
        $admin = $this->usuario('admin');
        $servidor = $this->usuario('Servidor');

        $this->actingAs($servidor)->post('/dominios', $this->pedido(['nome' => 'Proj.Letras.fflch.usp.br.']))
            ->assertRedirect();

        $dominio = Dominio::sole();
        $this->assertSame('proj.letras', $dominio->nome);
        $this->assertSame('pendente', $dominio->status);
        $this->assertSame($servidor->id, $dominio->user_id);

        Mail::assertQueued(DominioMail::class, fn (DominioMail $mail) => $mail->acao == 'solicitado'
            && $mail->hasTo($servidor->email) && $mail->hasCc($admin->email));
    }

    public function test_docente_pode_solicitar(): void
    {
        $this->actingAs($this->usuario('Docente'))->post('/dominios', $this->pedido())->assertSessionHasNoErrors();

        $this->assertSame(1, Dominio::count());
    }

    public function test_quem_nao_e_servidor_nem_docente_nao_solicita(): void
    {
        $aluno = $this->usuario('Alunogr');

        $this->actingAs($aluno)->get('/dominios/create')->assertForbidden();
        $this->actingAs($aluno)->post('/dominios', $this->pedido())->assertForbidden();
        $this->assertSame(0, Dominio::count());
    }

    public function test_visitante_sem_login_e_redirecionado(): void
    {
        $this->get('/dominios/create')->assertRedirect('/login');
    }

    public function test_motivo_e_obrigatorio(): void
    {
        $this->actingAs($this->usuario('Servidor'))->post('/dominios', $this->pedido(['motivo' => '']))
            ->assertSessionHasErrors('motivo');
    }

    public function test_nome_e_ip_invalidos_sao_recusados(): void
    {
        $servidor = $this->usuario('Servidor');

        foreach (['-abc', 'abc-', 'a_b', 'a..b', 'ab c', 'çã'] as $nome) {
            $this->actingAs($servidor)->post('/dominios', $this->pedido(['nome' => $nome]))
                ->assertSessionHasErrors('nome');
        }
        $this->actingAs($servidor)->post('/dominios', $this->pedido(['ip' => '300.1.1.1']))
            ->assertSessionHasErrors('ip');

        $this->assertSame(0, Dominio::count());
    }

    public function test_nao_aceita_www_pois_e_publicado_automaticamente(): void
    {
        $this->actingAs($this->usuario('Servidor'))->post('/dominios', $this->pedido(['nome' => 'www.novo']))
            ->assertSessionHasErrors('nome');
    }

    public function test_dominio_em_uso_em_sites_fflch_e_recusado(): void
    {
        $servidor = $this->usuario('Servidor');

        // Igual ao da lista e só a versão www na lista
        foreach (['abc', 'sowww'] as $nome) {
            $this->actingAs($servidor)->post('/dominios', $this->pedido(['nome' => $nome]))
                ->assertSessionHasErrors(['nome' => "O domínio {$nome}.fflch.usp.br já está em uso em sites.fflch.usp.br."]);
        }
        $this->assertSame(0, Dominio::count());
    }

    public function test_sites_fora_do_ar_ou_em_manutencao_pede_para_tentar_mais_tarde(): void
    {
        $servidor = $this->usuario('Servidor');

        foreach ([Http::response('Erro', 502), Http::response('<h1>Em manutenção</h1>', 200)] as $resposta) {
            $this->sites($resposta);

            $this->actingAs($servidor)->post('/dominios', $this->pedido())
                ->assertSessionHasErrors(['nome' => 'O sistema de verificação de domínios (sites.fflch.usp.br) está fora do ar no momento. Tente novamente mais tarde.']);
        }

        $this->assertSame(0, Dominio::count());
    }

    public function test_nome_com_solicitacao_em_qualquer_status_nao_aceita_novo_pedido(): void
    {
        $servidor = $this->usuario('Servidor');
        Dominio::factory()->comStatus('pendente')->create(['nome' => 'pendente']);
        Dominio::factory()->comStatus('aprovado')->create(['nome' => 'aprovado']);
        Dominio::factory()->comStatus('rejeitado', 'não')->create(['nome' => 'rejeitado']);

        foreach (['pendente', 'aprovado', 'rejeitado'] as $nome) {
            $this->actingAs($servidor)->post('/dominios', $this->pedido(['nome' => $nome]))
                ->assertSessionHasErrors(['nome' => "O domínio {$nome}.fflch.usp.br já tem uma solicitação neste sistema. Para pedi-lo de novo, fale com os administradores."]);
        }
        $this->assertSame(3, Dominio::count());
    }

    public function test_admin_exclui_em_qualquer_status_avisa_envolvidos_e_libera_o_nome(): void
    {
        $admin = $this->usuario('admin');
        $servidor = $this->usuario('Servidor');

        foreach (['pendente', 'aprovado', 'rejeitado'] as $status) {
            $dominio = Dominio::factory()->comStatus($status, 'motivo')->create(['nome' => "excluir-{$status}", 'user_id' => $servidor->id]);

            $this->actingAs($admin)->delete("/admin/{$dominio->id}", ['mensagem' => 'Não é mais usado'])
                ->assertRedirect('/admin/todos');

            $this->assertModelMissing($dominio);
            $this->assertSame(0, $dominio->statuses()->count());
            Mail::assertQueued(DominioExcluidoMail::class, fn (DominioExcluidoMail $mail) => $mail->fqdn == "excluir-{$status}.fflch.usp.br"
                && $mail->status == $status && $mail->mensagem == 'Não é mais usado'
                && $mail->hasTo($servidor->email) && $mail->hasCc($admin->email));
        }

        $this->get('/domains')->assertDontSee('excluir-aprovado', false);
        $this->actingAs($servidor)->post('/dominios', $this->pedido(['nome' => 'excluir-aprovado']))->assertSessionHasNoErrors();
    }

    public function test_so_admin_exclui(): void
    {
        $servidor = $this->usuario('Servidor');
        $dominio = Dominio::factory()->comStatus('pendente')->create(['user_id' => $servidor->id]);

        $this->actingAs($servidor)->delete("/admin/{$dominio->id}")->assertForbidden();

        $this->assertModelExists($dominio);
    }

    public function test_admin_aprova_sem_explicacao_e_todos_recebem_email(): void
    {
        $admin = $this->usuario('admin');
        $dominio = Dominio::factory()->comStatus('pendente')->create();

        $this->actingAs($admin)->patch("/admin/{$dominio->id}/aprovar")->assertSessionHasNoErrors();

        $dominio->refresh();
        $this->assertSame('aprovado', $dominio->status);
        $this->assertSame($admin->id, $dominio->avaliador_id);
        Mail::assertQueued(DominioMail::class, fn (DominioMail $mail) => $mail->acao == 'aprovado'
            && $mail->hasTo($dominio->user->email) && $mail->hasCc($admin->email));
    }

    public function test_aprovacao_confere_de_novo_sites_fflch(): void
    {
        $admin = $this->usuario('admin');
        $dominio = Dominio::factory()->comStatus('pendente')->create(['nome' => 'abc']);

        $this->actingAs($admin)->patch("/admin/{$dominio->id}/aprovar")->assertSessionHasErrors('mensagem');

        $this->sites(Http::response('', 503));
        $outro = Dominio::factory()->comStatus('pendente')->create();
        $this->actingAs($admin)->patch("/admin/{$outro->id}/aprovar")->assertSessionHasErrors('mensagem');

        $this->assertSame('pendente', $dominio->fresh()->status);
        $this->assertSame('pendente', $outro->fresh()->status);
    }

    public function test_rejeitar_exige_explicacao(): void
    {
        $dominio = Dominio::factory()->comStatus('pendente')->create();

        $this->actingAs($this->usuario('admin'))->patch("/admin/{$dominio->id}/rejeitar", ['mensagem' => ''])
            ->assertSessionHasErrors('mensagem');

        $this->assertSame('pendente', $dominio->fresh()->status);
    }

    public function test_aprovado_pode_ser_rejeitado_a_qualquer_momento_e_sai_da_lista_publica(): void
    {
        $admin = $this->usuario('admin');
        $dominio = Dominio::factory()->comStatus('aprovado')->create(['nome' => 'revogar']);

        $this->get('/domains')->assertSee('revogar.fflch.usp.br.', false);

        $this->actingAs($admin)->patch("/admin/{$dominio->id}/rejeitar", ['mensagem' => 'Servidor desativado'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rejeitado', $dominio->fresh()->status);
        $this->get('/domains')->assertDontSee('revogar.fflch.usp.br.', false);
        Mail::assertQueued(DominioMail::class, fn (DominioMail $mail) => $mail->acao == 'rejeitado'
            && $mail->revogado && $mail->mensagem == 'Servidor desativado');
    }

    public function test_so_admin_aprova_ou_rejeita(): void
    {
        $servidor = $this->usuario('Servidor');
        $dominio = Dominio::factory()->comStatus('pendente')->create(['user_id' => $servidor->id]);

        $this->actingAs($servidor)->patch("/admin/{$dominio->id}/aprovar")->assertForbidden();
        $this->actingAs($servidor)->patch("/admin/{$dominio->id}/rejeitar", ['mensagem' => 'qualquer'])->assertForbidden();
        $this->actingAs($servidor)->get('/admin/pendentes')->assertForbidden();

        $this->assertSame('pendente', $dominio->fresh()->status);
    }

    public function test_solicitante_nao_ve_pedido_de_outra_pessoa(): void
    {
        $dominio = Dominio::factory()->comStatus('pendente')->create();

        $this->actingAs($this->usuario('Servidor'))->get("/dominios/{$dominio->id}")->assertForbidden();
        $this->actingAs($dominio->user)->get("/dominios/{$dominio->id}")->assertOk();
        $this->actingAs($this->usuario('admin'))->get("/dominios/{$dominio->id}")->assertOk()->assertSee('Histórico');
    }

    public function test_rota_publica_lista_somente_aprovados_com_e_sem_www(): void
    {
        Dominio::factory()->comStatus('aprovado')->create(['nome' => 'zeta', 'ip' => '200.144.255.47']);
        Dominio::factory()->comStatus('aprovado')->create(['nome' => 'anc.aaa', 'ip' => '200.144.244.59']);
        Dominio::factory()->comStatus('pendente')->create(['nome' => 'pendente']);
        Dominio::factory()->comStatus('rejeitado', 'não')->create(['nome' => 'rejeitado']);

        $response = $this->get('/domains')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $this->assertSame(
            "anc.aaa.fflch.usp.br. IN A 200.144.244.59\n"
            ."www.anc.aaa.fflch.usp.br. IN A 200.144.244.59\n"
            ."zeta.fflch.usp.br. IN A 200.144.255.47\n"
            ."www.zeta.fflch.usp.br. IN A 200.144.255.47\n",
            $response->getContent()
        );
    }

    public function test_telas_de_listagem_e_formulario_abrem(): void
    {
        $admin = $this->usuario('admin', 'Servidor');
        $pendente = Dominio::factory()->comStatus('pendente')->create(['user_id' => $admin->id, 'nome' => 'fila']);
        Dominio::factory()->comStatus('aprovado')->create(['nome' => 'publicado']);

        $this->actingAs($admin)->get('/')->assertRedirect('/admin/pendentes');
        $this->actingAs($admin)->get('/admin/pendentes')->assertOk()->assertSee('fila.fflch.usp.br')->assertDontSee('publicado.fflch.usp.br');
        $this->actingAs($admin)->get('/admin/todos?status=aprovado')->assertOk()->assertSee('publicado.fflch.usp.br')->assertDontSee('fila.fflch.usp.br');
        $this->actingAs($admin)->get('/dominios')->assertOk()->assertSee($pendente->fqdn());
        $this->actingAs($admin)->get('/dominios/create')->assertOk()->assertSee('.fflch.usp.br');
    }

    public function test_solicitante_troca_ip_enquanto_pendente_com_mensagem_e_todos_recebem_email(): void
    {
        $admin = $this->usuario('admin');
        $servidor = $this->usuario('Servidor');
        $dominio = Dominio::factory()->comStatus('pendente')->create(['user_id' => $servidor->id, 'ip' => '200.144.244.1']);

        $this->actingAs($servidor)->patch("/dominios/{$dominio->id}/ip", ['ip' => '200.144.244.2', 'mensagem' => 'Servidor novo'])
            ->assertSessionHasNoErrors();

        $dominio->refresh();
        $this->assertSame('200.144.244.2', $dominio->ip);
        $this->assertSame('pendente', $dominio->status);
        Mail::assertQueued(DominioMail::class, fn (DominioMail $mail) => $mail->acao == 'ip alterado'
            && $mail->ipAnterior == '200.144.244.1' && $mail->mensagem == 'Servidor novo' && ! $mail->voltouPendente
            && $mail->hasTo($servidor->email) && $mail->hasCc($admin->email));

        $this->actingAs($servidor)->get("/dominios/{$dominio->id}")
            ->assertSee('IP alterado')->assertSee('200.144.244.1 → 200.144.244.2. Mensagem: Servidor novo');
    }

    public function test_solicitante_que_troca_ip_de_aprovado_faz_voltar_para_pendente(): void
    {
        $servidor = $this->usuario('Servidor');
        $dominio = Dominio::factory()->comStatus('aprovado')->create(['user_id' => $servidor->id, 'nome' => 'trocado']);

        $this->actingAs($servidor)->patch("/dominios/{$dominio->id}/ip", ['ip' => '200.144.244.99'])->assertSessionHasNoErrors();

        $this->assertSame('pendente', $dominio->fresh()->status);
        $this->get('/domains')->assertDontSee('trocado.fflch.usp.br', false);
        Mail::assertQueued(DominioMail::class, fn (DominioMail $mail) => $mail->acao == 'ip alterado' && $mail->voltouPendente);
    }

    public function test_admin_troca_ip_a_qualquer_momento_sem_mudar_status(): void
    {
        $admin = $this->usuario('admin');

        foreach (['pendente', 'aprovado', 'rejeitado'] as $status) {
            $dominio = Dominio::factory()->comStatus($status, 'motivo')->create();

            $this->actingAs($admin)->patch("/dominios/{$dominio->id}/ip", ['ip' => '200.144.255.10'])->assertSessionHasNoErrors();

            $dominio->refresh();
            $this->assertSame('200.144.255.10', $dominio->ip);
            $this->assertSame($status, $dominio->status);
        }

        $this->get('/domains')->assertSee('IN A 200.144.255.10', false);
    }

    public function test_solicitante_nao_troca_ip_de_rejeitado_nem_de_outra_pessoa(): void
    {
        $servidor = $this->usuario('Servidor');
        $rejeitado = Dominio::factory()->comStatus('rejeitado', 'não')->create(['user_id' => $servidor->id, 'ip' => '200.144.244.1']);
        $deOutro = Dominio::factory()->comStatus('pendente')->create(['ip' => '200.144.244.1']);

        $this->actingAs($servidor)->patch("/dominios/{$rejeitado->id}/ip", ['ip' => '200.144.244.2'])->assertForbidden();
        $this->actingAs($servidor)->patch("/dominios/{$deOutro->id}/ip", ['ip' => '200.144.244.2'])->assertForbidden();

        $this->assertSame('200.144.244.1', $rejeitado->fresh()->ip);
        $this->assertSame('200.144.244.1', $deOutro->fresh()->ip);
    }

    public function test_troca_de_ip_exige_ipv4_diferente_do_atual(): void
    {
        $servidor = $this->usuario('Servidor');
        $dominio = Dominio::factory()->comStatus('pendente')->create(['user_id' => $servidor->id, 'ip' => '200.144.244.1']);

        foreach (['200.144.244.1', 'abc', ''] as $ip) {
            $this->actingAs($servidor)->patch("/dominios/{$dominio->id}/ip", ['ip' => $ip])->assertSessionHasErrors('ip');
        }
        Mail::assertNothingQueued();
    }
}
