@php
    $siteName = config('app.name', 'VexaHost WA Gateway');
    $defaultTitle = 'VexaHost WA Gateway — Layanan WhatsApp API Terpercaya untuk Aplikasi & Bisnis';
    $defaultDescription = 'Gateway WhatsApp terpusat untuk aplikasi Anda lewat satu REST API. Multi-nomor, webhook pesan masuk, riwayat pengiriman, dan sesi handal tanpa putus.';
    $defaultKeywords = 'whatsapp gateway, wa gateway indonesia, whatsapp api murah, rest api whatsapp, wa blast resmi, webhook whatsapp, kirim wa otomatis, vexahost wa gateway, api wa indonesia';

    $finalTitle = $seoTitle ?? ($title ?? $defaultTitle);
    $finalDescription = $seoDescription ?? ($description ?? $defaultDescription);
    $finalKeywords = $seoKeywords ?? ($keywords ?? $defaultKeywords);
    $finalRobots = $seoRobots ?? ($robots ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
    $finalImage = $seoImage ?? ($image ?? asset('images/vexahost-wa.png'));
    $canonicalUrl = $canonicalUrl ?? url()->current();
    $googleSiteVerification = config('services.google.site_verification');

    // Schema.org Structured Data
    $schemaData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => url('/') . '#organization',
                'name' => 'VexaHost WA Gateway',
                'url' => url('/'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('images/vexahost-wa.png'),
                    'caption' => 'VexaHost WA Gateway Logo',
                ],
                'description' => 'Penyedia infrastruktur WhatsApp Gateway API berkinerja tinggi di Indonesia untuk integrasi aplikasi, OTP, dan pesan transaksi.',
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'telephone' => '+6285808749131',
                    'contactType' => 'customer support',
                    'areaServed' => 'ID',
                    'availableLanguage' => ['Indonesian', 'English'],
                ],
                'parentOrganization' => [
                    '@type' => 'Organization',
                    'name' => 'RZ Digital Creative',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/') . '#website',
                'url' => url('/'),
                'name' => 'VexaHost WA Gateway',
                'description' => 'Gateway WhatsApp untuk Aplikasi Anda — REST API, Webhook, dan Multi-Session',
                'publisher' => [
                    '@id' => url('/') . '#organization',
                ],
                'inLanguage' => 'id-ID',
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => 'VexaHost WA Gateway REST API',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'All',
                'image' => asset('images/vexahost-wa.png'),
                'description' => 'Layanan REST API WhatsApp Gateway multi-perangkat untuk pengiriman notifikasi, tagihan, dan pesan pelanggan otomatis.',
                'brand' => [
                    '@type' => 'Brand',
                    'name' => 'VexaHost',
                ],
                'offers' => [
                    '@type' => 'AggregateOffer',
                    'priceCurrency' => 'IDR',
                    'lowPrice' => '0',
                    'highPrice' => '500000',
                    'offerCount' => '4',
                    'priceValidUntil' => '2028-12-31',
                    'availability' => 'https://schema.org/InStock',
                    'url' => url('/#pricing'),
                ],
            ],
        ],
    ];
@endphp

<title>{{ $finalTitle }}</title>
<meta name="title" content="{{ $finalTitle }}">
<meta name="description" content="{{ $finalDescription }}">
<meta name="keywords" content="{{ $finalKeywords }}">
<meta name="robots" content="{{ $finalRobots }}">
<meta name="author" content="VexaHost WA Gateway — RZ Digital Creative">
<link rel="canonical" href="{{ $canonicalUrl }}">

@if(!empty($googleSiteVerification))
<meta name="google-site-verification" content="{{ $googleSiteVerification }}">
@endif

<!-- Geo Targeting (Dominasi Pencarian Lokal Indonesia) -->
<meta name="geo.region" content="ID-JK">
<meta name="geo.placename" content="Jakarta, Indonesia">
<meta name="geo.position" content="-6.2088;106.8456">
<meta name="ICBM" content="-6.2088, 106.8456">

<!-- Open Graph / Facebook / WhatsApp Preview -->
<meta property="og:type" content="website">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $finalTitle }}">
<meta property="og:description" content="{{ $finalDescription }}">
<meta property="og:image" content="{{ $finalImage }}">
<meta property="og:image:alt" content="{{ $siteName }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="id_ID">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ $canonicalUrl }}">
<meta name="twitter:title" content="{{ $finalTitle }}">
<meta name="twitter:description" content="{{ $finalDescription }}">
<meta name="twitter:image" content="{{ $finalImage }}">

<!-- Schema.org JSON-LD Structured Data -->
<script type="application/ld+json">
{!! json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
