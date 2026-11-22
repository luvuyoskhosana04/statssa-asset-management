<?php

namespace Tests\Feature\Branding;

use App\Models\User;
use App\Notifications\MailTest;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    public function test_application_and_email_sender_use_stats_sa(): void
    {
        $this->assertSame('Stats SA Asset Management', config('app.name'));
        $this->assertSame('Stats SA Asset Management', config('mail.from.name'));
        $this->actingAs(User::factory()->admin()->create())->get(route('projects.index'))->assertOk()
            ->assertSee('stats-sa-favicon.svg')->assertDontSee('Snipe-IT')->assertDontSee('snipeitapp.com')->assertDontSee('snipe-it.readme.io');
        $message = (new MailTest)->toMail();
        $this->assertStringContainsString('Stats SA Asset Management', $message->subject);
        $this->assertStringNotContainsString('Snipe-IT', $message->render()->toHtml());
    }

    public function test_user_guide_renders_and_requires_sign_in(): void
    {
        User::factory()->admin()->create();
        $this->get(route('statssa.guide'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('statssa.guide'))->assertOk()
            ->assertSee(trans('branding.guide'))->assertSee(trans('branding.projects_help'))->assertDontSee('Snipe-IT');
    }

    public function test_open_source_notices_remain_available_without_sign_in(): void
    {
        User::factory()->admin()->create();
        $this->get(route('statssa.notices'))->assertOk()->assertSee('Grokability')->assertSee('GNU Affero General Public License');
    }
}
