<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_the_users_notifications(): void
    {
        $user = User::factory()->create(['role' => 'regular']);
        app(NotificationService::class)->notify($user->id, 'test', 'Session approved — ready for baseline');

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Session approved — ready for baseline');
    }

    public function test_mark_read_and_mark_all_read(): void
    {
        $user = User::factory()->create(['role' => 'regular']);
        $svc = app(NotificationService::class);
        $svc->notify($user->id, 'test', 'One');
        $svc->notify($user->id, 'test', 'Two');

        $first = Notification::where('user_id', $user->id)->first();
        $this->actingAs($user)->patch(route('notifications.read', $first))->assertOk();
        $this->assertTrue($first->fresh()->read);

        $this->actingAs($user)->post(route('notifications.mark-all-read'))->assertRedirect();
        $this->assertSame(0, Notification::where('user_id', $user->id)->where('read', false)->count());
    }

    public function test_cannot_mark_another_users_notification_read(): void
    {
        $owner = User::factory()->create(['role' => 'regular']);
        $other = User::factory()->create(['role' => 'regular']);
        app(NotificationService::class)->notify($owner->id, 'test', 'Private');
        $n = Notification::where('user_id', $owner->id)->first();

        $this->actingAs($other)->patch(route('notifications.read', $n))->assertForbidden();
        $this->assertFalse($n->fresh()->read);
    }
}
