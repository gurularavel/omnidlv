<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SiteAndPasswordTest extends TestCase
{
    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super' => true])->save();

        return $user;
    }

    public function test_public_pages_footer_and_cookie_notice(): void
    {
        $this->get('/faq?lang=az')->assertOk()->assertSee('OmniDLV nədir?')->assertSee('© 2026 OmniDLV')->assertSee(__('site.cookie_banner.accept'));
        $this->get('/terms?lang=az')->assertOk()->assertSee('İstifadəçi qaydaları');
        $this->get('/cookies?lang=en')->assertOk()->assertSee('cookie_consent');

        // Once accepted, the notice is gone; the footer stays.
        $this->withUnencryptedCookie('cookie_consent', '1')->get('/login')
            ->assertOk()->assertSee('© 2026 OmniDLV')->assertDontSee('data-cookie-accept', false);

        // A language without questions falls back to Azerbaijani.
        $this->get('/faq?lang=tr')->assertOk()->assertSee('OmniDLV nədir?');
    }

    public function test_the_admin_edits_the_faq(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->get('/admin/faq')->assertOk()->assertSee('OmniDLV nədir?');
        $this->actingAs($admin)->post('/admin/faq', ['locale' => 'az', 'question' => 'Kuryer neçə sifariş götürə bilər?', 'answer' => 'Limit yoxdur.', 'sort' => 0, 'is_active' => '1'])
            ->assertRedirect(route('admin.faq.index', ['locale' => 'az']));
        $faq = Faq::query()->where('question', 'Kuryer neçə sifariş götürə bilər?')->firstOrFail();

        $this->get('/faq?lang=az')->assertSee('Kuryer neçə sifariş götürə bilər?');

        $this->actingAs($admin)->put("/admin/faq/{$faq->id}", ['locale' => 'az', 'question' => $faq->question, 'answer' => 'Limit yoxdur.', 'sort' => 0, 'is_active' => '0']);
        $this->get('/faq?lang=az')->assertDontSee('Kuryer neçə sifariş götürə bilər?');
    }

    public function test_forgotten_password_is_reset_by_email_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'disp@example.test']);

        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => 'Disp@Example.test'])->assertSessionHas('status');
        // An unknown address gets the same answer.
        $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertSessionHas('status')->assertSessionHasNoErrors();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=disp@example.test")->assertOk();
        $this->post('/reset-password', ['token' => 'wrong', 'email' => 'disp@example.test', 'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9'])
            ->assertSessionHasErrors('email');
        $this->post('/reset-password', ['token' => $token, 'email' => 'disp@example.test', 'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9'])
            ->assertRedirect(route('login'));

        $this->assertTrue(password_verify('new-secret-9', $user->fresh()->password));
    }

    public function test_the_admin_sets_the_smtp_server_and_the_mailer_uses_it(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->get('/admin/mail')->assertOk();
        $this->actingAs($admin)->put('/admin/mail', [
            'mailer' => 'smtp', 'host' => 'smtp.example.test', 'port' => 465, 'encryption' => 'ssl',
            'username' => 'noreply@omnidlv.app', 'password' => 's3cret', 'from_address' => 'noreply@omnidlv.app', 'from_name' => 'OmniDLV',
        ])->assertSessionHasNoErrors();

        // Stored encrypted, read back in clear.
        $this->assertNotSame('s3cret', \DB::table('platform_settings')->where('key', 'mail.password')->value('value'));
        $this->assertSame('s3cret', PlatformSettings::get('mail.password'));

        // An empty password field keeps the saved password.
        $this->actingAs($admin)->put('/admin/mail', [
            'mailer' => 'smtp', 'host' => 'smtp2.example.test', 'port' => 587, 'encryption' => 'tls',
            'username' => 'noreply@omnidlv.app', 'password' => '', 'from_address' => 'noreply@omnidlv.app', 'from_name' => 'OmniDLV',
        ]);
        $this->assertSame('s3cret', PlatformSettings::get('mail.password'));

        $this->app->forgetInstance('mail.manager');
        app('mail.manager');
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp2.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame('noreply@omnidlv.app', config('mail.from.address'));

        $this->actingAs($this->makeStaff($this->makeCompany()))->get('/admin/mail')->assertForbidden();
    }
}
