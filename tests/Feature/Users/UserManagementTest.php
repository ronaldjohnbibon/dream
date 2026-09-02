<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_regular_users_cannot_access_user_management(): void
    {
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden();
    }

    public function test_administrators_can_search_filter_sort_and_paginate_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['name' => 'Alice Admin', 'is_admin' => true]);
        User::factory()->count(16)->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->get('/users?search=Alice&account_type=admin&sort=name&direction=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/users/Index', false)
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Alice Admin')
                ->where('filters.account_type', 'admin')
                ->where('filters.sort', 'name'));

        $this->actingAs($admin)
            ->get('/users')
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 15));
    }

    public function test_administrators_can_create_and_update_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/users', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'is_admin' => false,
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertFalse($user->is_admin);

        $this->actingAs($admin)
            ->put("/users/{$user->id}", [
                'name' => 'Updated User',
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
                'is_admin' => true,
            ])
            ->assertRedirect("/users/{$user->id}");

        $this->assertTrue($user->refresh()->is_admin);
        $this->assertSame('Updated User', $user->name);
    }

    public function test_administrators_cannot_delete_themselves_or_remove_the_final_administrator(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertForbidden();

        $actor = User::factory()->make(['is_admin' => true]);
        $this->actingAs($actor)
            ->from("/users/{$admin->id}")
            ->delete("/users/{$admin->id}")
            ->assertSessionHasErrors('user');

        $this->actingAs($actor)
            ->from("/users/{$admin->id}/edit")
            ->put("/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'password' => '',
                'password_confirmation' => '',
                'is_admin' => false,
            ])
            ->assertSessionHasErrors('is_admin');
    }

    public function test_the_create_admin_command_creates_an_administrator(): void
    {
        $this->artisan('app:create-admin --name="First Admin" --email=admin@example.com --password=password')
            ->expectsOutput('Administrator account created.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'name' => 'First Admin',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);
    }
}
