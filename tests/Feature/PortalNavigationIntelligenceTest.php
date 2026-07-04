<?php

namespace Tests\Feature;

use App\Services\PortalNavigationIntelligenceService;
use Tests\TestCase;

class PortalNavigationIntelligenceTest extends TestCase
{
    public function test_catalog_loads_with_entries(): void
    {
        $service = app(PortalNavigationIntelligenceService::class);
        $catalog = $service->loadCatalog();

        $this->assertArrayHasKey('entries', $catalog);
        $this->assertNotEmpty($catalog['entries']);
    }

    public function test_resolves_dashboard_url(): void
    {
        $service = app(PortalNavigationIntelligenceService::class);
        $entry = collect($service->loadCatalog()['entries'])->firstWhere('id', 'dashboard');

        $this->assertNotNull($entry);
        $this->assertSame(route('dashboard'), $service->resolveUrl($entry));
    }

    public function test_resolves_create_weekly_plan_url_with_query(): void
    {
        $service = app(PortalNavigationIntelligenceService::class);
        $entry = collect($service->loadCatalog()['entries'])->firstWhere('id', 'create_weekly_plan');

        $this->assertNotNull($entry);
        $this->assertSame(
            route('documents.create', ['type' => 'weekly_plan']),
            $service->resolveUrl($entry)
        );
    }

    public function test_find_by_intent_matches_weekly_plan_creation(): void
    {
        $service = app(PortalNavigationIntelligenceService::class);
        $matches = $service->findByIntent('How do I submit a weekly plan?');

        $this->assertNotEmpty($matches);
        $this->assertSame('create_weekly_plan', $matches[0]['id']);
    }

    public function test_format_prompt_includes_navigation_rules_without_urls(): void
    {
        $service = app(PortalNavigationIntelligenceService::class);
        $prompt = $service->formatPromptContext();

        $this->assertStringContainsString('NAVIGATION RULES', $prompt);
        $this->assertStringContainsString('create_weekly_plan', $prompt);
        $this->assertStringContainsString('Menu:', $prompt);

        // No URLs should ever be injected into the prompt.
        $this->assertStringNotContainsString('URL:', $prompt);
        $this->assertStringNotContainsString('http://', $prompt);
        $this->assertStringNotContainsString('https://', $prompt);

        // The forced permission caveat has been removed.
        $this->assertStringNotContainsString('suggest asking an administrator', $prompt);
    }
}
