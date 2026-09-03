<?php

namespace App\Support;

/**
 * The content behind the Customer Stories page.
 *
 * This sits in a class rather than in a @php block so that the one rule the
 * page must never break is testable at the level of the data instead of by
 * grepping rendered HTML: a story is illustrative — invented, and marked as
 * such on the page — unless it is demonstrably not.
 *
 * Two conditions have to hold before a story ships without that marker. It
 * must set 'real' => true, and it must name a company on the APPROVED list
 * below. Either one alone is not enough, so a typo, a copied array or an
 * enthusiastic edit cannot quietly turn an invented studio into a testimonial.
 * The default — a story with no 'real' key at all — is illustrative.
 */
class CustomerStories
{
    /**
     * Companies whose story on this page is true, and who have agreed to be
     * named. Adding a name here is a statement of fact about a real business;
     * it is not a formatting decision.
     *
     * Code Next Door built Clarix and runs on it, which is why it is the one
     * entry — but the story itself only ships once its details come from them
     * rather than from us. See the featured slot below.
     */
    public const APPROVED = [
        'Code Next Door',
    ];

    /**
     * Whether a story has to carry the illustrative marker.
     *
     * Written as "is it illustrative" rather than "is it real" on purpose:
     * the question the view asks should fail toward the disclosure, not away
     * from it, if the data is ever malformed.
     */
    public static function isIllustrative(array $story): bool
    {
        return ! self::isReal($story);
    }

    public static function isReal(array $story): bool
    {
        return ($story['real'] ?? false) === true
            && in_array($story['studio'] ?? null, self::APPROVED, true);
    }

    /**
     * The story in the featured slot.
     *
     * TODO — this slot is reserved for Code Next Door, which is a real
     * customer: it built Clarix and runs its own delivery on it. It is
     * standing in as an illustrative example until the actual details (team
     * size, unit structure, roles in use, plan tier, and a quote if one is
     * offered) come from Code Next Door themselves. Nothing about a real,
     * named company gets invented to fill this in.
     */
    public static function featured(): array
    {
        return [
            'studio'  => 'Himal Creative',
            'real'    => false,
            'shape'   => 'A 14-person creative studio in Kathmandu, four units, eleven active clients',
            'heading' => 'One board above four units, and a client view instead of a Friday email.',
            'body'    => 'Content, Design, Development and Accounts each work their own queue. A project manager briefs across all four and sets the credit amount at brief time, so the month-end number is assembled as the work moves rather than reconstructed afterwards. Clients open the task rather than waiting on a status round-up.',
            'quote'   => 'The change was not the board. It was that nobody has to ask what is happening any more.',
            'byline'  => 'Operations lead',
            'setup'   => [
                ['Units', 'Content, Design, Development, Accounts'],
                ['Roles in use', 'Admin, PM, Supervisor, Writer'],
                ['Plan', 'Pro — multiple teams and clients'],
            ],
            'units' => [
                ['name' => 'Content',     'people' => 5, 'flight' => 7],
                ['name' => 'Design',      'people' => 4, 'flight' => 5],
                ['name' => 'Development', 'people' => 3, 'flight' => 4],
                ['name' => 'Accounts',    'people' => 2, 'flight' => 2],
            ],
        ];
    }

    /**
     * The three shorter examples. All invented, all marked, and all set where
     * Clarix's own customers are — an agency in Lalitpur reads as a worked
     * example here in a way that one in Portland does not.
     *
     * Same standard as before: names that are plainly a business rather than
     * a person, bylines that give a role and never a name, no photographs,
     * and no performance claims. What each describes is a configuration.
     */
    public static function others(): array
    {
        return [
            [
                'studio' => 'Baato Creative',
                'real'   => false,
                'shape'  => 'Solo practice in Lalitpur, three retained clients',
                'body'   => 'One board for all three clients, filtered when a single one needs attention. On hold carries the work waiting on a client answer, so a slow week reads as a blocked week rather than an unproductive one.',
                'quote'  => 'Invoicing stopped being a Saturday job.',
                'byline' => 'Founder',
                'plan'   => 'Base',
            ],
            [
                'studio' => 'Sagarmatha Group',
                'real'   => false,
                'shape'  => 'In-house team in Kathmandu, five departments',
                'body'   => 'Marketing briefs, Creative delivers, Finance reconciles and People staff it — each in its own unit, with permissions that stop a department seeing the next one\'s board. Attendance and leave sit against the same people the work is assigned to.',
                'quote'  => 'The audit question is answerable now, which it was not before.',
                'byline' => 'Head of delivery',
                'plan'   => 'Enterprise',
            ],
            [
                'studio' => 'Lipi Content',
                'real'   => false,
                'shape'  => 'Six-person content agency in Pokhara, two units',
                'body'   => 'Briefs arrive as tasks with a credit cost attached, files stay on the task that asked for them, and the AI layer flags anything that has sat in review past its due date.',
                'quote'  => 'We found out a task had stalled from the tool, not from the client.',
                'byline' => 'Managing editor',
                'plan'   => 'Standard',
            ],
        ];
    }

    /**
     * Every story on the page, featured first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return array_merge([self::featured()], self::others());
    }

}
