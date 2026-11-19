<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Every seeded account uses the password "password".
     */
    public function run(): void
    {
        // Same fake names and amounts on every run.
        fake()->seed(20261117);

        User::factory()->admin()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
        ]);

        // Known bidders for trying the app out.
        foreach (['alice', 'bob', 'carol'] as $name) {
            User::factory()->create([
                'name' => ucfirst($name),
                'email' => "{$name}@example.com",
            ]);
        }

        // Fake bidders who fill out the bid histories.
        User::factory()->count(12)->create();

        $this->call(AuctionSeeder::class);

        // Demo "last login" times: most users logged in during the past week, a few never.
        User::query()->orderBy('id')->get()->each(function (User $user, int $i) {
            $user->timestamps = false;
            $user->forceFill([
                'last_login_at' => $i % 5 === 4 ? null : now()->subMinutes(fake()->numberBetween(5, 7 * 24 * 60)),
            ])->saveQuietly();
        });
    }
}
