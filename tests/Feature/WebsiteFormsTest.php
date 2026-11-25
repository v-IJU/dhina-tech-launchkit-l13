<?php

namespace Tests\Feature;

use App\Modules\Inbox\Mail\ContactReplyMail;
use App\Modules\Inbox\Models\ContactMessage;
use App\Modules\Inbox\Models\Subscriber;
use App\Notifications\AdminAlert;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebsiteFormsTest extends TestCase
{
    public function test_contact_form_saves_message_and_alerts_admins(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();

        $this->from('/contact')->post(route('contact.submit'), [
            'name' => 'Anita Sharma', 'email' => 'anita@example.com', 'subject' => 'Quote', 'message' => 'Please send a quote for a website.',
        ])->assertRedirect()->assertSessionHas('contact_sent');

        $this->assertDatabaseHas('contact_messages', ['email' => 'anita@example.com', 'read_at' => null]);
        Notification::assertSentTo($admin, AdminAlert::class);
    }

    public function test_honeypot_drops_bots(): void
    {
        $this->post(route('contact.submit'), ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap stuff now!!', 'website' => 'http://spam'])
            ->assertSessionHas('contact_sent');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_contact_form_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('contact.submit'), ['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hello there number '.$i]);
        }

        $this->post(route('contact.submit'), ['name' => 'A', 'email' => 'a@example.com', 'message' => 'One too many'])->assertStatus(429);
    }

    public function test_newsletter_signup_is_unique(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'Reader@Example.com'])->assertSessionHas('success');
        $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com'])->assertSessionHas('success');

        $this->assertSame(1, Subscriber::query()->count());
    }

    public function test_inbox_marks_read_and_reply_is_emailed(): void
    {
        Mail::fake();
        $message = ContactMessage::query()->create(['name' => 'Anita', 'email' => 'anita@example.com', 'message' => 'Hello there']);

        $this->actingAs($this->superAdmin())->get(route('admin.inbox.show', $message))->assertOk()->assertSee('Hello there');
        $this->assertNotNull($message->fresh()->read_at);

        $this->post(route('admin.inbox.reply', $message), ['body' => 'Thanks, we will call you.'])->assertSessionHas('success');
        Mail::assertQueued(ContactReplyMail::class, fn ($mail) => $mail->hasTo('anita@example.com'));
        $this->assertNotNull($message->fresh()->replied_at);
    }

    public function test_maintenance_mode_hides_website_from_visitors_only(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->put(route('admin.settings.update', 'maintenance'), ['maintenance_mode' => '1', 'maintenance_message' => 'Back at noon'])->assertRedirect();

        auth()->logout();
        $this->get('/blog')->assertStatus(503)->assertSee('Back at noon');
        $this->actingAs($admin)->get('/blog')->assertOk();
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/robots.txt')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $this->get(route('login'))->assertHeader('Content-Security-Policy');
    }
}
