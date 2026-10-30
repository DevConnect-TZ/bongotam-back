<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use Illuminate\Database\Seeder;

class SupportTicketSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local')) {
            SupportTicket::factory()->count(3)->create();
        }
    }
}
