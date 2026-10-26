<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\LoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class AuthAndSessionTest extends TestCase
{
    use RefreshDatabase, CreatesPosData;

    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    // A3: setelah password benar user BELUM login sampai outlet dipilih
    public function test_user_is_not_logged_in_until_outlet_is_chosen(): void
    {
        $outlet = $this->makeOutlet();
        User::factory()->create(['username' => 'elga']);

        $component = Volt::test('auth.login')
            ->set('username', 'elga')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSet('pendingRole', 'staff');

        $this->assertGuest();

        $component->set('selected_outlet_id', $outlet->id)
            ->call('enterPos')
            ->assertRedirect('/pos');

        $this->assertAuthenticated();
        $this->assertEquals($outlet->id, session('current_outlet_id'));
        $this->assertNull(session(LoginService::PENDING_KEY));
    }

    public function test_owner_is_redirected_to_dashboard(): void
    {
        $outlet = $this->makeOutlet();
        User::factory()->owner()->create(['username' => 'owner']);

        Volt::test('auth.login')
            ->set('username', 'owner')
            ->set('password', 'password')
            ->call('authenticate')
            ->set('selected_outlet_id', $outlet->id)
            ->call('enterPos')
            ->assertRedirect('/dashboard');
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['username' => 'elga']);

        Volt::test('auth.login')
            ->set('username', 'elga')
            ->set('password', 'salah')
            ->call('authenticate')
            ->assertHasErrors('username')
            ->assertSet('step', 1);

        $this->assertGuest();
    }

    // F3: dibatasi 5 percobaan gagal per menit
    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['username' => 'elga']);

        for ($i = 0; $i < 5; $i++) {
            Volt::test('auth.login')->set('username', 'elga')->set('password', 'salah')->call('authenticate');
        }

        Volt::test('auth.login')
            ->set('username', 'elga')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors('username')
            ->assertSee('Terlalu banyak percobaan login')
            ->assertSet('step', 1);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create(['username' => 'elga', 'is_active' => false]);

        Volt::test('auth.login')
            ->set('username', 'elga')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors('username')
            ->assertSee('dinonaktifkan');
    }

    public function test_logged_in_user_without_outlet_is_sent_back_to_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/pos')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_deactivated_user_is_kicked_out_of_active_session(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create(['is_active' => false]);

        $this->loginAt($user, $outlet)->get('/pos')->assertRedirect('/login');
        $this->assertGuest();
    }

    // A4: dulu error 500 karena FK transactions.user_id
    public function test_owner_can_deactivate_staff_who_has_transactions(): void
    {
        $outlet = $this->makeOutlet();
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        Transaction::create([
            'order_id' => 'TRX-1', 'outlet_id' => $outlet->id, 'user_id' => $staff->id,
            'subtotal' => 1000, 'total' => 1000, 'payment_method' => 'tunai',
        ]);
        $this->loginAt($owner, $outlet);

        Volt::test('staff.index')->call('toggleActive', $staff->id);
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertSame(1, Transaction::where('user_id', $staff->id)->count());

        Volt::test('staff.index')->call('toggleActive', $staff->id);
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_owner_cannot_deactivate_self(): void
    {
        $outlet = $this->makeOutlet();
        $owner = User::factory()->owner()->create();
        $this->loginAt($owner, $outlet);

        Volt::test('staff.index')->call('toggleActive', $owner->id);

        $this->assertTrue($owner->fresh()->is_active);
    }
}
