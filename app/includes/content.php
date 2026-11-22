<?php
/**
 * Editable website content: page text (About, Contact, policies), contact
 * details and social links, and placeholder photos for empty sections.
 *
 * Everything is stored in the settings table, so the admin preview (drafts)
 * works the same way for every page.
 */

/* ------------------------------------------------------------------ *
 *  Placeholder photos (Unsplash CDN). Shown only where you have not
 *  uploaded a picture yet; the admin marks them as placeholders.
 * ------------------------------------------------------------------ */
const PH_PHOTOS = [
    'hero'       => 'photo-1706808849780-7a04fbac83ef',
    'house'      => 'photo-1600596542815-ffad4c1539a9',
    'house2'     => 'photo-1580587771525-78b9dba3b914',
    'villa'      => 'photo-1613490493576-7fde63acd811',
    'apartment'  => 'photo-1545324418-cc1a3fa10c00',
    'apartment2' => 'photo-1479839672679-a46483c0e7c8',
    'townhouse'  => 'photo-1530877872966-40bb5529f558',
    'townhouse2' => 'photo-1511452885600-a3d2c9148a31',
    'commercial' => 'photo-1583009640887-eafd1a994d30',
    'office'     => 'photo-1694702740570-0a31ee1525c7',
    'airbnb'     => 'photo-1782846484501-d4b1c5b57953',
    'stay'       => 'photo-1768578927442-24a68408c0c7',
    'holiday'    => 'photo-1651108066220-f61c22fc281f',
    'beach'      => 'photo-1720161263981-84281892ee4b',
    'living'     => 'photo-1704040686413-2c607dbd2f06',
    'interior'   => 'photo-1705326701287-346fc37a2c86',
    'land'       => 'photo-1507064396389-c7452ac64f1c',
];

function ph_url(string $key, int $w = 1200): string
{
    $id = PH_PHOTOS[$key] ?? PH_PHOTOS['house'];
    return 'https://images.unsplash.com/' . $id . '?auto=format&fit=crop&w=' . $w . '&q=72';
}

function is_placeholder(?string $src): bool
{
    return !$src || str_contains($src, 'images.unsplash.com/') || str_contains($src, 'placeholder.jpg');
}

/** Best placeholder for a property type / purpose name. */
function ph_key_for(string $text): string
{
    $t = strtolower($text);
    return match (true) {
        (bool) preg_match('/land|plot|acre|farm/', $t)                         => 'land',
        (bool) preg_match('/commercial|office|shop|warehouse|retail|godown/', $t) => 'commercial',
        (bool) preg_match('/airbnb|short|stay|serviced|furnished/', $t)          => 'airbnb',
        (bool) preg_match('/holiday|beach|cottage|resort|coast/', $t)           => 'holiday',
        (bool) preg_match('/penthouse|studio|bedsitter|apartment|flat|condo/', $t) => 'apartment',
        (bool) preg_match('/town ?house|maisonette|duplex|terrace/', $t)        => 'townhouse',
        (bool) preg_match('/villa|mansion|luxury|estate/', $t)                  => 'villa',
        default                                                                => 'house',
    };
}

/** A listing photo, or a fitting placeholder when it has none. */
function listing_image(?string $path, string $hint = ''): string
{
    return $path ? (string) $path : ph_url(ph_key_for($hint), 1080);
}

/** srcset for Unsplash URLs (their CDN resizes on the fly). */
function ph_srcset(string $url, array $widths): string
{
    if (!str_contains($url, 'images.unsplash.com/')) return '';
    return implode(', ', array_map(fn($w) => preg_replace('/([?&])w=\d+/', '$1w=' . $w, $url) . ' ' . $w . 'w', $widths));
}

/* ------------------------------------------------------------------ *
 *  Page content
 * ------------------------------------------------------------------ */

/** Editable text for a page: saved value, else the built-in default. */
function c(string $key): string
{
    $v = trim(setting('c_' . $key, ''));
    if ($v !== '') return $v;
    return (string) (content_defaults()[$key] ?? '');
}

/** The editor's field list per page (labels, kind, help). Defaults live in content_defaults(). */
function content_pages(): array
{
    $policy = fn(string $slug, string $label) => [
        'label' => $label, 'url' => '/' . $slug, 'group' => 'Policies',
        'fields' => [
            ["{$slug}_title", 'Page title', 'text'],
            ["{$slug}_updated", 'Last updated', 'date'],
            ["{$slug}_intro", 'Short summary (shown under the title)', 'textarea'],
            ["{$slug}_body", 'Policy text', 'rich', 'Use ## for headings, - for bullet points and **bold**. Keep it accurate for how you actually work.'],
        ],
    ];
    return [
        'about' => [
            'label' => 'About us', 'url' => '/about', 'group' => 'Pages',
            'fields' => [
                ['about_eyebrow', 'Small heading', 'text'],
                ['about_title', 'Page title', 'text'],
                ['about_intro', 'Introduction', 'textarea'],
                ['about_image', 'Banner photo', 'image', 'Shown beside the introduction.'],
                ['about_lead', 'Main paragraph', 'textarea'],
                ['about_stat1_value', 'Stat 1 — number', 'text'], ['about_stat1_label', 'Stat 1 — label', 'text'],
                ['about_stat2_value', 'Stat 2 — number', 'text'], ['about_stat2_label', 'Stat 2 — label', 'text'],
                ['about_stat3_value', 'Stat 3 — number', 'text'], ['about_stat3_label', 'Stat 3 — label', 'text'],
                ['about_stat4_value', 'Stat 4 — number', 'text'], ['about_stat4_label', 'Stat 4 — label', 'text'],
                ['about_values_title', 'Values — heading', 'text'],
                ['about_v1_title', 'Value 1 — title', 'text'], ['about_v1_text', 'Value 1 — text', 'textarea'],
                ['about_v2_title', 'Value 2 — title', 'text'], ['about_v2_text', 'Value 2 — text', 'textarea'],
                ['about_v3_title', 'Value 3 — title', 'text'], ['about_v3_text', 'Value 3 — text', 'textarea'],
                ['about_v4_title', 'Value 4 — title', 'text'], ['about_v4_text', 'Value 4 — text', 'textarea'],
                ['about_cta_title', 'Closing banner — heading', 'text'],
                ['about_cta_text', 'Closing banner — text', 'textarea'],
            ],
        ],
        'contact' => [
            'label' => 'Contact page', 'url' => '/contact', 'group' => 'Pages',
            'fields' => [
                ['contact_eyebrow', 'Small heading', 'text'],
                ['contact_title', 'Page title', 'text'],
                ['contact_intro', 'Introduction', 'textarea'],
                ['contact_direct_title', 'Details heading', 'text'],
                ['contact_form_title', 'Form heading', 'text'],
                ['contact_hours', 'Office hours', 'textarea', 'One line per day range, e.g. “Mon – Fri: 8am – 6pm”.'],
            ],
        ],
        'privacy'    => $policy('privacy', 'Privacy policy'),
        'terms'      => $policy('terms', 'Terms of use'),
        'cookies'    => $policy('cookies', 'Cookie policy'),
        'disclaimer' => $policy('disclaimer', 'Listing disclaimer'),
    ];
}

function content_defaults(): array
{
    static $d = null;
    if ($d !== null) return $d;
    $brand = 'Reside Kenya';
    $email = setting('contact_email', defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'residekenya@gmail.com');
    $phone = setting('contact_phone', '+254 117 959 105');
    $d = [
        'about_eyebrow' => 'Our story',
        'about_title' => 'Property, done properly',
        'about_intro' => "$brand was built on a simple belief: finding a home, an investment, or the perfect stay should feel effortless, transparent, and genuinely premium.",
        'about_image' => '',
        'about_lead' => 'We connect people across Kenya with quality homes, rentals, commercial spaces, land, and curated Airbnb stays — pairing a refined, editorial browsing experience with the honesty and responsiveness that property decisions deserve.',
        'about_stat1_value' => setting('about_stat1_value', '300+'), 'about_stat1_label' => setting('about_stat1_label', 'Listings'),
        'about_stat2_value' => setting('about_stat2_value', '20+'), 'about_stat2_label' => setting('about_stat2_label', 'Counties covered'),
        'about_stat3_value' => setting('about_stat3_value', '20+'), 'about_stat3_label' => setting('about_stat3_label', 'Consultants'),
        'about_stat4_value' => setting('about_stat4_value', '100%'), 'about_stat4_label' => setting('about_stat4_label', 'Verified process'),
        'about_values_title' => 'Our values',
        'about_v1_title' => 'Trust & transparency', 'about_v1_text' => 'Every listing is presented honestly, with clear pricing and accurate detail — no surprises, no inflated promises.',
        'about_v2_title' => 'People first', 'about_v2_text' => 'Behind every search is a real goal — a first home, a smart investment, a memorable stay. We treat each one with care.',
        'about_v3_title' => 'Nationwide reach', 'about_v3_text' => 'From Nairobi to the coast and beyond, we bring quality property from across Kenya into one refined platform.',
        'about_v4_title' => 'Premium standard', 'about_v4_text' => 'A considered, elegant experience — because browsing property should feel as good as owning it.',
        'about_cta_title' => 'Ready to find your next address?',
        'about_cta_text' => "Browse our curated collection or list your own property with $brand today.",

        'contact_eyebrow' => "We'd love to hear from you",
        'contact_title' => 'Get in touch',
        'contact_intro' => "Whether you're buying, renting, booking a stay, or listing a property — our team is here to help you every step of the way.",
        'contact_direct_title' => 'Reach us directly',
        'contact_form_title' => 'Send us a message',
        'contact_hours' => "Mon – Fri: 8:00am – 6:00pm\nSaturday: 9:00am – 4:00pm\nSunday & public holidays: by appointment",

        'privacy_title' => 'Privacy policy',
        'privacy_updated' => '2026-11-22',
        'privacy_intro' => "How $brand collects, uses and protects your personal information, in line with Kenya's Data Protection Act, 2019.",
        'privacy_body' => <<<MD
## Who we are
$brand ("we", "us") runs this website to help people buy, rent, sell and list property and book short stays in Kenya. For any privacy question you can reach us at $email or $phone.

## What we collect
- **Details you give us** — your name, phone number, email address and message when you send an enquiry, request a viewing, book a stay, list a property or subscribe to updates.
- **Property details** — information and photos you send us about a property you want to list.
- **Technical information** — basic data your browser sends (such as device type and pages visited) so the site works and stays secure.

## How we use it
- To reply to your enquiry and arrange viewings, bookings and listings.
- To match you with suitable properties and keep you updated if you asked us to.
- To run, secure and improve this website.
- To meet our legal and regulatory obligations.

We do not sell your personal information.

## Who we share it with
We share your details only where needed to help you — for example with the property owner, landlord or agent for a listing you asked about — and with trusted service providers who host this website and our records under confidentiality obligations. We may disclose information where the law requires it.

## How long we keep it
We keep enquiry and transaction records only for as long as we need them for the purposes above, or as required by law, and then delete or anonymise them.

## Your rights
Under the Data Protection Act, 2019 you may ask to access, correct or delete your personal data, object to its use, or withdraw consent for marketing at any time. Contact us at $email. You may also complain to the Office of the Data Protection Commissioner.

## Security
We use reasonable technical and organisational measures to protect your information. No online service is completely secure, so please contact us immediately if you think your information has been misused.

## Changes
We may update this policy from time to time. The date at the top shows when it last changed.
MD,

        'terms_title' => 'Terms of use',
        'terms_updated' => '2026-11-22',
        'terms_intro' => "The rules for using the $brand website. By using the site you agree to them.",
        'terms_body' => <<<MD
## Using this website
You may browse listings, send enquiries, request viewings, book stays and submit properties for listing. Please use the site lawfully and do not misuse it, copy it in bulk, or try to disrupt it.

## Listings and information
We work hard to keep listings accurate and up to date, but details such as price, availability, size and features are provided by owners and agents and can change. Please confirm all details — including ownership, title and approvals — before you pay or sign anything. See our Listing disclaimer.

## Enquiries, viewings and bookings
Sending an enquiry or booking request does not create a binding agreement until it is confirmed by us or the property owner. Viewing times are subject to availability.

## Listing your property
When you submit a property you confirm that you own it or are authorised to market it, that the information and photos are accurate, and that you have the right to share them. We may edit, decline or remove any listing.

## Payments
Never send money to anyone claiming to represent $brand without confirming through our official contacts: $phone or $email. We will never ask you to pay into a personal account without written confirmation.

## Intellectual property
The website design, text and branding belong to $brand. Listing photos remain the property of their owners.

## Liability
To the extent the law allows, we are not liable for losses arising from reliance on listing information or from dealings between you and third parties.

## Governing law
These terms are governed by the laws of Kenya.
MD,

        'cookies_title' => 'Cookie policy',
        'cookies_updated' => '2026-11-22',
        'cookies_intro' => 'What cookies and similar storage this website uses, and why.',
        'cookies_body' => <<<MD
## What are cookies?
Cookies are small files a website stores in your browser so it can remember things between visits.

## What we use
- **Essential** — keeps forms secure and, for our staff, keeps them signed in. These cannot be switched off.
- **Preferences** — remembers your chosen colour theme on this device. Stored only in your browser.
- **Saved properties** — remembers listings you marked as favourites, in your browser.

We do not use advertising cookies.

## Managing cookies
You can delete or block cookies in your browser settings. If you block essential cookies some forms may not work.
MD,

        'disclaimer_title' => 'Listing disclaimer',
        'disclaimer_updated' => '2026-11-22',
        'disclaimer_intro' => 'Important information about the property details shown on this website.',
        'disclaimer_body' => <<<MD
## Information is a guide
Property details, prices, measurements, photos and availability are provided by owners, landlords and agents and are a guide only. They do not form part of any offer or contract.

## Do your due diligence
Before paying a deposit or signing an agreement, please view the property, confirm the seller or landlord's identity and authority, and carry out the usual searches on title, land rates, approvals and any charges. We recommend using an advocate for sales.

## Prices
Prices are in Kenya shillings unless stated and may change without notice. Rent is per month unless stated (short stays are per night).

## Photos
Some images may be illustrative. Where a listing shows a sample photo, ask us for current pictures.
MD,
    ];
    return $d;
}

/** Tiny Markdown subset → safe HTML (## headings, - lists, **bold**, *italic*, links, paragraphs). */
function md(string $text): string
{
    $out = []; $list = false; $para = [];
    $inline = function (string $s): string {
        $s = e($s);
        $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<em>$1</em>', $s);
        $s = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*|mailto:[^\s)]+|tel:[^\s)]+)\)/', '<a href="$2">$1</a>', $s);
        return $s;
    };
    $flush = function () use (&$para, &$out, $inline) { if ($para) { $out[] = '<p>' . $inline(implode(' ', $para)) . '</p>'; $para = []; } };
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $t = trim($line);
        if ($t === '') { $flush(); if ($list) { $out[] = '</ul>'; $list = false; } continue; }
        if (preg_match('/^(#{2,3})\s+(.+)$/', $t, $m)) { $flush(); if ($list) { $out[] = '</ul>'; $list = false; } $h = strlen($m[1]); $out[] = "<h$h>" . $inline($m[2]) . "</h$h>"; continue; }
        if (preg_match('/^[-*•]\s+(.+)$/', $t, $m)) { $flush(); if (!$list) { $out[] = '<ul>'; $list = true; } $out[] = '<li>' . $inline($m[1]) . '</li>'; continue; }
        if ($list) { $out[] = '</ul>'; $list = false; }
        $para[] = $t;
    }
    $flush(); if ($list) $out[] = '</ul>';
    return implode("\n", $out);
}

/* ------------------------------------------------------------------ *
 *  Contact details & social links
 * ------------------------------------------------------------------ */
const SOCIAL_NETWORKS = [
    'instagram' => ['Instagram', 'https://instagram.com/', '<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>'],
    'tiktok'    => ['TikTok', 'https://tiktok.com/@', '<path fill="currentColor" stroke="none" d="M16 3c.3 2 1.6 3.6 3.6 3.9v2.6c-1.3.1-2.6-.3-3.6-1v6.1c0 3-2.4 5.4-5.4 5.4S5.2 17.6 5.2 14.6 7.6 9.2 10.6 9.2c.3 0 .6 0 .9.1v2.7c-.3-.1-.6-.2-.9-.2-1.4 0-2.6 1.2-2.6 2.7s1.2 2.7 2.6 2.7 2.7-1.2 2.7-2.7V3H16z"/>'],
    'facebook'  => ['Facebook', 'https://facebook.com/', '<path fill="currentColor" stroke="none" d="M14 8h3V4h-3c-2.8 0-4.5 1.8-4.5 4.6V11H7v4h2.5v6h4v-6h3l.5-4h-3.5V8.8c0-.5.3-.8.5-.8z"/>'],
    'x'         => ['X', 'https://x.com/', '<path fill="currentColor" stroke="none" d="M17.7 3h3.1l-6.8 7.8L22 21h-6.3l-4.9-6.4L5.2 21H2.1l7.3-8.3L1.8 3h6.4l4.4 5.9L17.7 3zm-1.1 16.2h1.7L7.5 4.7H5.7l10.9 14.5z"/>'],
    'youtube'   => ['YouTube', 'https://youtube.com/@', '<rect x="2" y="5" width="20" height="14" rx="4"/><path fill="currentColor" stroke="none" d="m10 9 5 3-5 3z"/>'],
    'linkedin'  => ['LinkedIn', 'https://linkedin.com/company/', '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v7M8 7v.01M12 17v-4a2 2 0 0 1 4 0v4M12 10v7"/>'],
    'threads'   => ['Threads', 'https://threads.net/@', '<path d="M17 8.5C16 6 14.2 5 12 5c-3.9 0-6 3-6 7s2.1 7 6 7c3 0 5-1.6 5-4 0-2-1.6-3.2-4.2-3.2-2.2 0-3.3 1-3.3 2.2 0 1.1 1 1.9 2.3 1.9 2 0 3.2-1.6 3.2-4.4"/>'],
    'pinterest' => ['Pinterest', 'https://pinterest.com/', '<circle cx="12" cy="12" r="9.5"/><path d="M11 8.5c2.8-.8 5 .8 4.5 3.5-.4 2.2-2.4 3.4-4 2.4M11.5 10 9 20"/>'],
    'whatsapp'  => ['WhatsApp channel', '', '<path fill="currentColor" stroke="none" d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.4A10 10 0 1 0 12 2zm0 2a8 8 0 1 1-4.1 14.9l-.3-.2-2.8.8.8-2.7-.2-.3A8 8 0 0 1 12 4z"/>'],
];

/** Social links the admin chose to show: [[network, label, url, icon], …] */
function site_socials(): array
{
    $rows = json_decode(setting('socials', ''), true);
    if (!is_array($rows)) {
        $rows = [];
        foreach (['instagram' => 'reside254', 'tiktok' => 'reside254', 'facebook' => setting('facebook', ''), 'x' => setting('twitter', '')] as $n => $v) {
            $v = $n === 'instagram' || $n === 'tiktok' ? setting($n, $v) : $v;
            if ($v !== '') $rows[] = ['network' => $n, 'handle' => $v, 'show' => true];
        }
    }
    $out = [];
    foreach ($rows as $r) {
        $n = $r['network'] ?? ''; $h = trim((string) ($r['handle'] ?? ''));
        if (!isset(SOCIAL_NETWORKS[$n]) || $h === '' || empty($r['show'])) continue;
        [$label, $base, $icon] = SOCIAL_NETWORKS[$n];
        $url = preg_match('#^https?://#', $h) ? $h : $base . ltrim($h, '@/');
        $out[] = ['network' => $n, 'label' => $label, 'handle' => preg_match('#^https?://#', $h) ? $label : '@' . ltrim($h, '@'), 'url' => $url, 'icon' => $icon];
    }
    return $out;
}

function social_icon_svg(string $icon, int $size = 18): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icon . '</svg>';
}

/** Public contact details with sensible defaults. */
function site_contact(): array
{
    $phone = setting('contact_phone', '+254 117 959 105') ?: '+254 117 959 105';
    return [
        'phone'    => $phone,
        'phone2'   => setting('contact_phone2', ''),
        'email'    => setting('contact_email', defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'residekenya@gmail.com'),
        'whatsapp' => preg_replace('/\D/', '', setting('whatsapp_number', '254117959105') ?: '254117959105'),
        'address'  => setting('office_address', 'Nairobi, Kenya') ?: 'Nairobi, Kenya',
        'map'      => setting('office_map_embed', ''),
    ];
}
