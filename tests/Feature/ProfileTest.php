<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create([
            'surname' => 'Иванов',
            'name' => 'Иван',
            'patronymic' => 'Иванович',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'surname' => 'Петров',
                'name' => 'Пётр',
                'patronymic' => 'Петрович',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Петров', $user->surname);
        $this->assertSame('Пётр', $user->name);
        $this->assertSame('Петрович', $user->patronymic);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_profile_rejects_fio_longer_than_database_column(): void
    {
        $user = User::factory()->create();

        $tooLong = str_repeat('а', 46);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'surname' => $tooLong,
                'name' => $user->name,
                'patronymic' => $user->patronymic,
                'email' => $user->email,
            ]);

        $response->assertSessionHasErrors('surname');
        $response->assertRedirect('/profile');
    }

    public function test_user_cannot_delete_account_via_profile(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertForbidden();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
    }
}
