<?php

namespace Tests\Feature\Accessibility;

use App\Models\User;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\TenantMembership;
use App\Modules\Order\Models\Order;
use Database\Seeders\UatDemoSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WebAccessibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_pages_expose_landmarks_named_controls_and_captioned_tables(): void
    {
        $this->seed(UatDemoSeeder::class);
        config(['identity.mfa.mode' => 'disabled']);

        $owner = User::query()->where('email', 'owner@uat.netclean.id')->sole();
        $tenant = Tenant::query()->where('name', 'NetClean UAT')->sole();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->where('code', 'UAT-MAIN')->sole();
        $order = Order::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $role = Role::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $membership = TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', '!=', $owner->id)->firstOrFail();
        $session = ['tenant.public_id' => $tenant->public_id];

        $pages = [
            '/orders',
            '/orders/create',
            '/orders/'.$order->public_id,
            '/production',
            '/finance',
            '/finance/cash',
            '/finance/payments',
            '/finance/expenses',
            '/finance/refunds',
            '/reports',
            '/reports/imports',
            '/reports/outlet?'.http_build_query(['branch_id' => $branch->public_id, 'from' => '2026-11-01', 'to' => '2026-11-16']),
            '/reports/receivables?'.http_build_query(['branch_id' => $branch->public_id, 'as_of' => '2026-11-16']),
            '/customers',
            '/catalog',
            '/branches/'.$branch->public_id,
            '/organization',
            '/roles',
            '/roles/'.$role->public_id,
            '/staff/'.$membership->public_id,
        ];

        $issues = [];

        foreach ($pages as $path) {
            $response = $this->actingAs($owner)->withSession($session)->get($path);
            $response->assertOk();

            $issues = [...$issues, ...$this->accessibilityIssues($response->getContent(), $path)];
        }

        $this->assertSame([], $issues, implode("\n", $issues));
    }

    /**
     * @return list<string>
     */
    private function accessibilityIssues(string $html, string $path): array
    {
        $issues = [];

        if (! str_contains($html, 'href="#main"')) {
            $issues[] = 'Missing skip link on '.$path;
        }
        if (! str_contains($html, 'id="main"')) {
            $issues[] = 'Missing main landmark on '.$path;
        }
        if (! str_contains($html, 'lang="id"')) {
            $issues[] = 'Missing document language on '.$path;
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        if ($xpath->query('//nav[@aria-label]')->length === 0) {
            $issues[] = 'Missing named navigation on '.$path;
        }

        foreach ($xpath->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button") and not(@type="reset")] | //select | //textarea') as $control) {
            if (! $this->hasAccessibleName($xpath, $control)) {
                $issues[] = 'Unnamed form control name='.$control->getAttribute('name').' type='.$control->getAttribute('type').' placeholder='.$control->getAttribute('placeholder').' on '.$path;
            }
        }

        foreach ($xpath->query('//input[not(@type="hidden") and not(@type="checkbox") and not(@type="radio") and not(@type="submit") and not(@type="button") and not(@type="reset")] | //select') as $control) {
            if (! str_contains($control->getAttribute('class'), 'min-h-12')) {
                $issues[] = 'Touch target below 48px name='.$control->getAttribute('name').' on '.$path;
            }
        }

        foreach ($xpath->query('//button') as $button) {
            if (! $this->hasAccessibleName($xpath, $button)) {
                $issues[] = 'Unnamed button aria='.$button->getAttribute('aria-label').' text='.trim($button->textContent).' on '.$path;
            }
        }

        foreach ($xpath->query('//table') as $table) {
            $caption = $xpath->query('./caption', $table)->length > 0;
            $labelled = trim($table->getAttribute('aria-label')) !== '' || trim($table->getAttribute('aria-labelledby')) !== '';
            if (! $caption && ! $labelled) {
                $issues[] = 'Uncaptioned table on '.$path;
            }
        }

        return $issues;
    }

    private function hasAccessibleName(DOMXPath $xpath, DOMElement $element): bool
    {
        if (trim($element->getAttribute('aria-label')) !== '') {
            return true;
        }

        if ($element->nodeName === 'button' && trim($element->textContent) !== '') {
            return true;
        }

        $labelledBy = trim($element->getAttribute('aria-labelledby'));
        if ($labelledBy !== '') {
            foreach (preg_split('/\s+/', $labelledBy) ?: [] as $id) {
                if ($xpath->query('//*[@id="'.$id.'"]')->length > 0) {
                    return true;
                }
            }
        }

        $id = $element->getAttribute('id');
        if ($id !== '' && $xpath->query('//label[@for="'.$id.'"]')->length > 0) {
            return true;
        }

        for ($parent = $element->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if ($parent->nodeName === 'label' && trim($parent->textContent) !== '') {
                return true;
            }
        }

        return trim($element->getAttribute('title')) !== '';
    }
}
