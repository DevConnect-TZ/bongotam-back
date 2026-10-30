<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportTicketFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'category' => 'payment', 'language' => 'en', 'message' => 'I paid but cannot watch my video.', 'status' => 'open'];
    }
}
