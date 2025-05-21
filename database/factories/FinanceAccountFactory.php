<?php
namespace Database\Factories;
use App\Models\FinanceAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinanceAccountFactory extends Factory
{
    protected $model = FinanceAccount::class;

    public function definition(): array
    {
        $accountName = $this->faker->unique()->company . ' ' . $this->faker->randomElement(['Account', 'Fund', 'Ledger']);
        return [
            'name' => $accountName,
            'debit' => $this->faker->randomElement(['debit', 'credit']),
            'description' => $this->faker->sentence,
            'code' => strtoupper($this->faker->bothify('???###')), // Example ACC123
            'type' => $this->faker->randomElement(['revenue', 'cost', null]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}