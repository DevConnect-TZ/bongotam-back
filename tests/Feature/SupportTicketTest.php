<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Video;
use App\Services\ApiSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(?User $user = null): User
    {
        $user ??= User::factory()->create();
        $token = app(ApiSessionService::class)->issueSession($user, Request::create('/'))['access_token'];
        $this->withToken($token);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return [...['category' => 'payment', 'message' => 'I paid. Please help me watch.', 'language' => 'en'], ...$overrides];
    }

    public function test_customers_can_create_sanitized_tickets_with_owned_payment_and_video_context(): void
    {
        $user = $this->signIn();
        $transaction = Transaction::create(['user_id' => (string) $user->id, 'user_email' => $user->email]);
        $video = Video::create(['title' => 'My video', 'zone' => 'connection', 'price' => 400]);
        $this->postJson('/api/support/tickets', $this->payload([
            'message' => "  Nime\u{0001}lipa.\r\nNaomba msaada\u{200B}  ",
            'language' => 'sw', 'transaction_id' => $transaction->id, 'video_id' => $video->id,
            'media_error_code' => 2, 'playback_seconds' => 35,
            'user_id' => 999, 'status' => 'resolved', 'reply' => 'Forged reply',
        ]))->assertCreated()->assertJsonPath('user_id', $user->id)->assertJsonPath('status', 'open')->assertJsonMissingPath('reply');
        $ticket = SupportTicket::firstOrFail();
        $this->assertSame("Nimelipa.\nNaomba msaada", $ticket->message);
        $this->assertSame($transaction->id, $ticket->transaction_id);
        $this->assertNull($ticket->reply);
    }

    #[DataProvider('unsafeMessages')]
    public function test_backend_rejects_links_and_markup_even_without_frontend_validation(string $message): void
    {
        $this->signIn();
        $this->postJson('/api/support/tickets', $this->payload(['message' => $message]))->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->assertDatabaseCount('support_tickets', 0);
    }

    public static function unsafeMessages(): array
    {
        return array_map(fn ($message) => [$message], [
            'See https://example.com', 'www.example.com', 'example.com/path', '//example.com',
            'ftp://example.com', 'mailto:customer@example.com', 'javascript:alert(1)',
            '<img src=x onerror=alert(1)>', '&lt;script&gt;alert(1)&lt;/script&gt;',
            'https&#58;//example.com', "ex\u{200B}ample.com", 'https://127.0.0.1',
            'Visit mfano.co.tz', 'https://例子.中国', 'ｈｔｔｐｓ：／／example.com',
            '[click](https://example.com)',
        ]);
    }

    public function test_message_limits_and_context_are_validated(): void
    {
        $this->signIn();
        foreach (['', '   ', "\u{200B}", str_repeat('a', 2001)] as $message) {
            $this->postJson('/api/support/tickets', $this->payload(['message' => $message]))->assertUnprocessable()->assertJsonValidationErrors('message');
        }
        $this->postJson('/api/support/tickets', $this->payload(['category' => 'unknown', 'language' => 'xx', 'media_error_code' => 5, 'playback_seconds' => -1]))->assertUnprocessable()->assertJsonValidationErrors(['category', 'language', 'media_error_code', 'playback_seconds']);
        $this->travel(61)->seconds();
        $this->signIn();
        $this->postJson('/api/support/tickets', $this->payload(['message' => str_repeat('a', 2000)]))->assertCreated();
    }

    public function test_customers_cannot_attach_another_customers_payment_or_change_ticket_ownership(): void
    {
        $other = User::factory()->create();
        $transaction = Transaction::create(['user_id' => (string) $other->id, 'user_email' => $other->email]);
        $this->signIn();
        $this->postJson('/api/support/tickets', $this->payload(['transaction_id' => $transaction->id]))->assertUnprocessable()->assertJsonValidationErrors('transaction_id');
    }

    public function test_customers_only_see_their_tickets_and_cannot_read_or_update_admin_inbox(): void
    {
        $own = SupportTicket::factory()->create();
        SupportTicket::factory()->create();
        $this->signIn($own->user);
        $this->getJson('/api/support/tickets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/admin/support/tickets')->assertForbidden();
        $this->patchJson('/api/admin/support/tickets/'.$own->id, ['status' => 'resolved'])->assertForbidden();
    }

    public function test_admin_can_filter_reply_and_resolve_and_customer_can_read_reply(): void
    {
        $ticket = SupportTicket::factory()->create();
        SupportTicket::factory()->create(['status' => 'resolved']);
        $this->signIn(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/support/tickets?status=open')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.email', $ticket->user->email);
        $this->patchJson('/api/admin/support/tickets/'.$ticket->id, ['status' => 'in_progress', 'reply' => " Checking your payment.\u{0001} "])->assertOk()->assertJsonPath('reply', 'Checking your payment.');
        $this->patchJson('/api/admin/support/tickets/'.$ticket->id, ['status' => 'resolved', 'reply' => 'Your access is ready.'])->assertOk();
        $this->signIn($ticket->user);
        $this->getJson('/api/support/tickets')->assertJsonPath('data.0.reply', 'Your access is ready.')->assertJsonPath('data.0.status', 'resolved');
    }

    public function test_admin_reply_rejects_links_and_invalid_status(): void
    {
        $ticket = SupportTicket::factory()->create();
        $this->signIn(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/admin/support/tickets/'.$ticket->id, ['status' => 'invalid', 'reply' => 'https://example.com'])->assertUnprocessable()->assertJsonValidationErrors(['status', 'reply']);
        $this->assertNull($ticket->fresh()->reply);
    }

    public function test_guests_cannot_read_or_submit_tickets(): void
    {
        $this->getJson('/api/support/tickets')->assertUnauthorized();
        $this->postJson('/api/support/tickets', $this->payload())->assertUnauthorized();
        $this->getJson('/api/admin/support/tickets')->assertUnauthorized();
    }

    public function test_ticket_submission_is_rate_limited(): void
    {
        $this->signIn();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/support/tickets', $this->payload())->assertCreated();
        }
        $this->postJson('/api/support/tickets', $this->payload())->assertTooManyRequests();
    }

    public function test_ticket_lists_are_paginated_and_user_deletion_removes_private_messages(): void
    {
        $user = $this->signIn();
        SupportTicket::factory()->count(21)->create(['user_id' => $user->id]);
        $this->getJson('/api/support/tickets')->assertJsonCount(20, 'data')->assertJsonPath('last_page', 2);
        $this->getJson('/api/support/tickets?page=2')->assertJsonCount(1, 'data');
        $user->delete();
        $this->assertDatabaseCount('support_tickets', 0);
    }
}
