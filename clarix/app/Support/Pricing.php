<?php

namespace App\Support;

/**
 * The plans, and everything the site says about them.
 *
 * These arrays used to live in a @php block inside home.blade.php. They moved
 * here when /pricing was built, for one reason: two pages quoting prices from
 * two copies of the same array is a drift waiting to happen, and the page a
 * visitor compares plans on is the worst possible place to find a stale
 * number. The homepage section and the dedicated page now read the same
 * source, and PricingPageTest asserts they agree.
 *
 * Nothing here was retyped — the arrays were moved across verbatim.
 */
class Pricing
{
    /**
     * The plan cards, in display order.
     *
     * A feature line ending in ':' is a lead-in ("Everything in Pro, plus:")
     * rather than a feature, and renders without a checkmark.
     *
     * 'priceAnnual' is the same plan at 20% off, quoted as a per-month figure
     * because that is what the card compares against. A plan without the key
     * ignores the billing toggle entirely — which is how Base and Enterprise
     * behave, Base carrying no annual discount and Enterprise no list price
     * at all.
     *
     * \@return array<int, array<string, mixed>>
     */
    public static function plans(): array
    {
    return [
            [
                'name'  => 'Base',
                'price' => 'Rs 1,250',
                // No 'priceAnnual': Base is not discounted annually, and a plan
                // without the key ignores the billing toggle rather than
                // showing the same figure struck through against itself.
                'period' => 'per month',
                'blurb' => 'For small teams getting organized',
                'cta'   => 'Get Started with Base',
                'href'  => route('login'),
                'features' => [
                    'Up to 5 teams/units',
                    'Task boards & file attachments',
                    'Upload/download files',
                    '5GB file storage',
                    'Email support',
                ],
            ],
            [
                'name'  => 'Standard',
                'price' => 'Rs 2,000',
                'priceAnnual' => 'Rs 1,600',
                'period' => 'per month',
                'blurb' => 'For growing agencies with real client load',
                'cta'   => 'Get Started with Standard',
                'href'  => route('login'),
                'popular' => true,
                'features' => [
                    'Up to 15 teams/units',
                    'Everything in Base, plus:',
                    'Full ERP access (Attendance, Leave, Payroll)',
                    'AI Chatbot',
                    'Gantt charts',
                    '50GB file storage',
                    'Priority email support',
                ],
            ],
            [
                'name'  => 'Pro',
                'price' => 'Rs 4,000',
                'priceAnnual' => 'Rs 3,200',
                'period' => 'per month',
                'blurb' => 'For agencies running multiple teams and clients',
                'cta'   => 'Get Started with Pro',
                'href'  => route('login'),
                'features' => [
                    'Up to 50 teams/units',
                    'Everything in Standard, plus:',
                    'MCP & Plugins / Automation (full access)',
                    '100GB file storage (+Rs 1,000 per extra 100GB on request)',
                    'Priority chat support',
                ],
            ],
            [
                'name'  => 'Enterprise',
                'price' => 'Let\'s talk',
                'period' => 'Custom pricing',
                'blurb' => 'For agencies at scale',
                'cta'   => 'Talk to Sales',
                'href'  => '#schedule-demo',
                'dark'  => true,
                'features' => [
                    'Unlimited teams/units',
                    'Everything in Pro, plus:',
                    'Dedicated account manager',
                    'Custom integrations & API access',
                    'SLA-backed uptime guarantee',
                    'Onboarding & migration support',
                ],
            ],
        ];
    }

    /**
     * The comparison table's column headers. Order matches plans(), so the
     * table reads left to right in the same order as the cards above it.
     *
     * \@return array<int, array<string, string>>
     */
    public static function compareHeads(): array
    {
    return [
            ['name' => 'Base',       'cta' => 'Get Started',       'href' => route('login')],
            ['name' => 'Standard',   'cta' => 'Get Started',       'href' => route('login')],
            ['name' => 'Pro',        'cta' => 'Talk to an expert', 'href' => '#schedule-demo'],
            ['name' => 'Enterprise', 'cta' => 'Talk to an expert', 'href' => '#schedule-demo'],
        ];
    }

    /**
     * The comparison rows, grouped.
     *
     * [label, [4 values], hint?]. A value of true renders a check, false an
     * X, and a string renders as-is. The optional third element is tooltip
     * copy for the '?' beside the label.
     *
     * \@return array<string, array<int, array>>
     */
    public static function comparison(): array
    {
    return [
            'Core' => [
                ['Teams/Units', ['5', '15', '50', 'Unlimited']],
                ['Task boards', [true, true, true, true]],
                ['Create projects/tasks', [true, true, true, true]],
                ['Upload/download files', [true, true, true, true]],
                ['Seats', ['10', '25', '50', 'Unlimited']],
            ],
            'Storage' => [
                ['File storage', ['5GB', '50GB', '100GB (+Rs 1,000/extra 100GB)', 'Custom']],
            ],
            'Support' => [
                ['Support type', ['Email', 'Priority email', 'Priority chat', 'Dedicated manager']],
                ['SLA guarantee', [false, false, false, true],
                 'A contractual uptime commitment with agreed response times.'],
                ['Onboarding & migration support', [false, false, false, true]],
            ],
            'Advanced' => [
                ['ERP (Attendance/Leave/Payroll)', [false, 'Full access', 'Full access', 'Full access']],
                ['AI Chatbot', [false, true, true, true]],
                ['Gantt charts', [false, true, true, true]],
                ['MCP & Plugins / Automation', [false, false, 'Full access', 'Full access'],
                 'Connect Clarix to Excel, Google Sheets and your own tools.'],
                ['API access', [false, false, false, true],
                 'Programmatic access to your Clarix data for custom tooling.'],
                ['Custom integrations', [false, false, false, true]],
            ],
        ];
    }

    /**
     * Plan names in order, which is what a parity check compares.
     *
     * \@return array<int, string>
     */
    public static function planNames(): array
    {
        return array_column(self::plans(), 'name');
    }
}
