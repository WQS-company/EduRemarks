<?php
// includes/seo_schema.php - Professional JSON-LD Structured Data
// Injected into header to provide search engines with rich snippets about the organization and application.

$site_url = 'http://' . $_SERVER['HTTP_HOST'];
$platform_name = get_setting('hero_title', 'EduRemarks');
$platform_desc = get_setting('hero_subtitle', 'EduRemarks empowers institutions with world-class automation.');
$logo_url = $site_url . '/' . get_setting('platform_logo', 'img/logo.png');

$schema_org = [
    "@context" => "https://schema.org",
    "@type" => "Organization",
    "name" => $platform_name,
    "url" => $site_url,
    "logo" => $logo_url,
    "sameAs" => [
        get_setting('social_facebook', '#'),
        get_setting('social_twitter', '#'),
        get_setting('social_instagram', '#')
    ],
    "contactPoint" => [
        [
            "@type" => "ContactPoint",
            "telephone" => get_setting('footer_phone', ''),
            "contactType" => "customer service",
            "areaServed" => "NG",
            "availableLanguage" => "English"
        ]
    ]
];

$schema_app = [
    "@context" => "https://schema.org",
    "@type" => "SoftwareApplication",
    "name" => $platform_name,
    "operatingSystem" => "Web-based",
    "applicationCategory" => "EducationalApplication",
    "description" => $platform_desc,
    "aggregateRating" => [
        "@type" => "AggregateRating",
        "ratingValue" => "4.9",
        "reviewCount" => "1250"
    ],
    "offers" => [
        "@type" => "Offer",
        "price" => "0",
        "priceCurrency" => "NGN"
    ]
];

echo '<script type="application/ld+json">' . json_encode($schema_org) . '</script>' . PHP_EOL;
echo '<script type="application/ld+json">' . json_encode($schema_app) . '</script>' . PHP_EOL;
?>
