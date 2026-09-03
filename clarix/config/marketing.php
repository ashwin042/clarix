<?php

/*
|--------------------------------------------------------------------------
| Marketing site navigation
|--------------------------------------------------------------------------
|
| One definition of the public site's menus, shared by the header (which
| renders it twice — as desktop dropdowns and as the flattened mobile panel)
| and by the footer. Both used to carry their own copy inside home.blade.php,
| which meant every nav change had to be made in two places.
|
| Each item names its destination with exactly one of:
|
|   'route' => a route name, resolved through route()
|   'url'   => a literal href, used as written
|
| A 'url' starting with http is treated as leaving the site: both menus give
| it target="_blank" and rel="noopener noreferrer". A bare '#' means the page
| has not been built yet — MarketingNavTest reads that as pending rather than
| as a typo, so an item is never silently shipped pointing at nothing.
|
*/

return [

    // Dropdown menus, in the order they sit in the bar. 'width' is the desktop
    // panel's width; Resources is narrower because its rows carry no
    // description and a wide panel would leave them stranded.
    'menus' => [

        'Product' => [
            'width' => 'w-[332px]',
            'items' => [
                ['label' => 'Task Boards',            'description' => 'Plan and track work in one place',     'route' => 'marketing.product.boards'],
                ['label' => 'Client Portal',          'description' => 'Give clients a live view of delivery',  'route' => 'marketing.product.portal'],
                // AXOKAI has its own site, but the menu now goes to our page
                // for it — which carries the outbound link as its main CTA.
                ['label' => 'AI Automation (AXOKAI)', 'description' => 'Let AI handle the busywork',            'route' => 'marketing.product.ai'],
                ['label' => 'File Management',        'description' => 'Keep every file attached to its task',  'route' => 'marketing.product.files'],
                ['label' => 'Credits & Billing',      'description' => 'Track spend as work moves',             'route' => 'marketing.product.credits'],
            ],
        ],

        'Solutions' => [
            'width' => 'w-[300px]',
            'items' => [
                ['label' => 'For Agencies',    'description' => 'Manage multiple clients and teams', 'route' => 'marketing.solutions.agencies'],
                ['label' => 'For Freelancers', 'description' => 'Simplify solo project tracking',    'route' => 'marketing.solutions.freelancers'],
                ['label' => 'For Enterprises', 'description' => 'Scale delivery across departments', 'route' => 'marketing.solutions.enterprises'],
            ],
        ],

        'Resources' => [
            'width' => 'w-[210px]',
            'items' => [
                ['label' => 'Blog',             'description' => null, 'route' => 'marketing.blog'],
                ['label' => 'Documentation',    'description' => null, 'route' => 'marketing.docs'],
                ['label' => 'Help Center',      'description' => null, 'route' => 'marketing.help'],
            ],
        ],
    ],

    // Bar entries with no menu behind them. Pricing used to be an anchor
    // into the homepage; it has had its own page since /pricing was built,
    // and the homepage section is now a preview that links to it.
    'links' => [
        ['label' => 'Pricing', 'route' => 'marketing.pricing'],
    ],

    'footer' => [
        'Product' => [
            ['label' => 'Task Boards',     'route' => 'marketing.product.boards'],
            ['label' => 'Client Portal',   'route' => 'marketing.product.portal'],
            ['label' => 'AI Automation',   'route' => 'marketing.product.ai'],
            ['label' => 'File Management', 'route' => 'marketing.product.files'],
            ['label' => 'Pricing',         'route' => 'marketing.pricing'],
            ['label' => 'Changelog',       'url' => '#'],
        ],
        'Learn' => [
            ['label' => 'Blog',             'route' => 'marketing.blog'],
            ['label' => 'Documentation',    'route' => 'marketing.docs'],
            ['label' => 'Alternatives',     'url' => '#'],
            ['label' => 'Community',        'url' => '#'],
        ],
        'Company' => [
            ['label' => 'Legal',                 'url' => '#'],
            ['label' => 'Privacy Policy',        'url' => '#'],
            ['label' => 'Security & Compliance', 'url' => '#'],
            ['label' => 'Careers',               'url' => '#'],
            ['label' => 'Status',                'url' => '#'],
        ],
    ],

    // Brand glyphs on a 24x24 viewBox, filled rather than stroked.
    'social' => [
        ['name' => 'LinkedIn', 'href' => '#', 'path' => 'M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0Z'],
        ['name' => 'X',        'href' => '#', 'path' => 'M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.41l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.59l5.24 6.93 6.07-6.93Zm-1.29 19.5h2.04L6.49 3.24H4.3l13.31 17.41Z'],
        ['name' => 'Facebook', 'href' => '#', 'path' => 'M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.09 24 18.1 24 12.07Z'],
        ['name' => 'YouTube',  'href' => '#', 'path' => 'M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.5A3.02 3.02 0 0 0 .5 6.19C0 8.08 0 12 0 12s0 3.92.5 5.81a3.02 3.02 0 0 0 2.12 2.14c1.88.5 9.38.5 9.38.5s7.5 0 9.38-.5a3.02 3.02 0 0 0 2.12-2.14C24 15.92 24 12 24 12s0-3.92-.5-5.81ZM9.55 15.57V8.43L15.82 12l-6.27 3.57Z'],
        ['name' => 'Slack',    'href' => '#', 'path' => 'M5.04 15.17a2.53 2.53 0 0 1-2.52 2.52A2.53 2.53 0 0 1 0 15.17a2.53 2.53 0 0 1 2.52-2.52h2.52v2.52Zm1.27 0a2.53 2.53 0 0 1 2.52-2.52 2.53 2.53 0 0 1 2.52 2.52v6.31A2.53 2.53 0 0 1 8.83 24a2.53 2.53 0 0 1-2.52-2.52v-6.31ZM8.83 5.04a2.53 2.53 0 0 1-2.52-2.52A2.53 2.53 0 0 1 8.83 0a2.53 2.53 0 0 1 2.52 2.52v2.52H8.83Zm0 1.27a2.53 2.53 0 0 1 2.52 2.52 2.53 2.53 0 0 1-2.52 2.52H2.52A2.53 2.53 0 0 1 0 8.83a2.53 2.53 0 0 1 2.52-2.52h6.31ZM18.96 8.83a2.53 2.53 0 0 1 2.52-2.52A2.53 2.53 0 0 1 24 8.83a2.53 2.53 0 0 1-2.52 2.52h-2.52V8.83Zm-1.27 0a2.53 2.53 0 0 1-2.52 2.52 2.53 2.53 0 0 1-2.52-2.52V2.52A2.53 2.53 0 0 1 15.17 0a2.53 2.53 0 0 1 2.52 2.52v6.31ZM15.17 18.96a2.53 2.53 0 0 1 2.52 2.52A2.53 2.53 0 0 1 15.17 24a2.53 2.53 0 0 1-2.52-2.52v-2.52h2.52Zm0-1.27a2.53 2.53 0 0 1-2.52-2.52 2.53 2.53 0 0 1 2.52-2.52h6.31A2.53 2.53 0 0 1 24 15.17a2.53 2.53 0 0 1-2.52 2.52h-6.31Z'],
    ],
];
