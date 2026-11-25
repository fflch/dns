<?php

namespace Database\Factories;

use App\Models\Dominio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dominio>
 */
class DominioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->lexify('??????'),
            'ip' => fake()->ipv4(),
            'motivo' => fake()->sentence(8),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Cria já com status (pendente, aprovado ou rejeitado)
     */
    public function comStatus(string $status, ?string $motivo = null): static
    {
        return $this->afterCreating(fn (Dominio $dominio) => $dominio->setStatus($status, $motivo));
    }
}
