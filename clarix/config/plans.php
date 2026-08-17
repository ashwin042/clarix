<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plan Order
    |--------------------------------------------------------------------------
    |
    | Cheapest first. Everything else here is a comparison against this list,
    | so a plan's position is its meaning: anything at or above a feature's
    | minimum includes that feature.
    |
    */

    'order' => ['base', 'standard', 'pro'],

    /*
    |--------------------------------------------------------------------------
    | Fallback Plan
    |--------------------------------------------------------------------------
    |
    | Used for an organization with no subscription row, an unrecognised plan
    | name, and a null organization. Deliberately the cheapest tier: an agency
    | whose plan cannot be determined is given the least, never the most.
    |
    */

    'default' => 'base',

    /*
    |--------------------------------------------------------------------------
    | Minimum Plan Per Feature
    |--------------------------------------------------------------------------
    |
    | Expressed as minimums rather than as three lists of included features.
    | "Standard is everything in Base plus ERP" is then structurally true,
    | instead of true only while somebody remembers to copy the Base entries
    | into the Standard list.
    |
    | A feature that is not named here is denied on every plan. Adding a gated
    | feature means adding a line; forgetting leaves the feature unreachable
    | rather than silently free, which is the safer way to be wrong.
    |
    */

    'minimum' => [
        'tasks'      => 'base',
        'files'      => 'base',
        'erp'        => 'standard',
        'ai_chat'    => 'standard',
        'calendar'   => 'standard',
        'automation' => 'pro',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Labels
    |--------------------------------------------------------------------------
    |
    | How each feature is named to somebody who has just been refused it.
    | Written as a plain noun phrase so it reads in the refusal sentence
    | without further shaping.
    |
    */

    'labels' => [
        'tasks'      => 'task boards',
        'files'      => 'file uploads',
        'erp'        => 'ERP tools (attendance, leave and payroll)',
        'ai_chat'    => 'the AI chatbot',
        'calendar'   => 'the calendar',
        'automation' => 'MCP, plugins and automation',
    ],

];
