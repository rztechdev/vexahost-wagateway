<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_full_seo_metadata(): void
    {
        $response = $this->get(route('welcome'));

        $response->assertStatus(200);

        // Standard Meta
        $response->assertSee('<title>VexaHost WA Gateway — Kirim Notifikasi WhatsApp Lewat Satu REST API</title>', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('name="keywords"', false);
        $response->assertSee('name="robots" content="index, follow', false);
        $response->assertSee('rel="canonical"', false);

        // Geo targeting
        $response->assertSee('name="geo.region" content="ID-JK"', false);
        $response->assertSee('name="geo.placename" content="Jakarta, Indonesia"', false);
        $response->assertSee('name="geo.position" content="-6.2088;106.8456"', false);
        $response->assertSee('name="ICBM" content="-6.2088, 106.8456"', false);

        // Open Graph
        $response->assertSee('property="og:type" content="website"', false);
        $response->assertSee('property="og:site_name" content="VexaHost WA Gateway"', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('property="og:description"', false);
        $response->assertSee('property="og:image"', false);
        $response->assertSee('property="og:locale" content="id_ID"', false);

        // Twitter Card
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);
        $response->assertSee('name="twitter:title"', false);
        $response->assertSee('name="twitter:description"', false);
        $response->assertSee('name="twitter:image"', false);
    }

    public function test_homepage_renders_google_site_verification_when_configured(): void
    {
        config(['services.google.site_verification' => 'test-google-wa-verification-token-98765']);

        $response = $this->get(route('welcome'));

        $response->assertStatus(200);
        $response->assertSee('name="google-site-verification" content="test-google-wa-verification-token-98765"', false);
    }

    public function test_homepage_renders_valid_json_ld_schema(): void
    {
        $response = $this->get(route('welcome'));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('application/ld+json', $content);

        // Extract JSON inside ld+json tag
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);
        $this->assertNotEmpty($matches, 'JSON-LD schema tag found');

        $schema = json_decode(trim($matches[1]), true);
        $this->assertIsArray($schema, 'JSON-LD is valid JSON');
        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertArrayHasKey('@graph', $schema);

        $types = array_column($schema['@graph'], '@type');
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('SoftwareApplication', $types);
    }

    public function test_sitemap_xml_endpoint_returns_valid_xml(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $content = $response->getContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', trim($content));
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);
        $this->assertStringContainsString('<loc>', $content);
        $this->assertStringContainsString('<lastmod>', $content);
        $this->assertStringContainsString('<changefreq>', $content);
        $this->assertStringContainsString('<priority>', $content);

        // SimpleXML validation
        $xml = simplexml_load_string($content);
        $this->assertNotFalse($xml, 'Sitemap is valid XML structure');
        $this->assertGreaterThanOrEqual(25, count($xml->url));
    }

    public function test_robots_txt_contains_sitemap_and_disallows(): void
    {
        $robotsPath = public_path('robots.txt');
        $this->assertFileExists($robotsPath);

        $robotsContent = file_get_contents($robotsPath);
        $this->assertStringContainsString('User-agent: *', $robotsContent);
        $this->assertStringContainsString('Allow: /', $robotsContent);
        $this->assertStringContainsString('Disallow: /admin/', $robotsContent);
        $this->assertStringContainsString('Disallow: /dashboard/', $robotsContent);
        $this->assertStringContainsString('Disallow: /api/', $robotsContent);
        $this->assertStringContainsString('Sitemap: https://wa.vexahostcloud.my.id/sitemap.xml', $robotsContent);
    }

    public function test_public_subpages_render_seo_metadata(): void
    {
        $pages = [
            route('docs.index'),
            route('status'),
            route('mitra.landing'),
            route('ai.index'),
            route('enterprise'),
        ];

        foreach ($pages as $url) {
            $res = $this->get($url);
            $res->assertStatus(200);
            $res->assertSee('name="description"', false);
            $res->assertSee('rel="canonical"', false);
            $res->assertSee('property="og:title"', false);
            $res->assertSee('name="twitter:card"', false);
        }
    }
}
