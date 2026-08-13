<?php

namespace App\Livewire\AI;

use App\Services\ChatQuota;
use Livewire\Component;

/**
 * Clarix AI Overview.
 *
 * The landing page for the AI & Automation section: what AXOKAI is, how much
 * of the monthly allowance is left, and where the rest of the section lives.
 *
 * The message allowance is real: it reads the same App\Services\ChatQuota the
 * Chatbot writes to, so the number here and the one under the composer cannot
 * disagree. The three counters below it are still honest zeroes — there is no
 * plugin store and no scheduler yet — and become real when those land.
 *
 * The model list is grouped by what each model is for in Clarix rather than by
 * capability, so the grouping reads the same way the Chatbot picker does.
 * Every name in Chatbot::MODELS must appear in exactly one group here; the
 * "Models Available" counter is taken from Chatbot::MODELS so the two cannot
 * drift apart silently.
 */
class Overview extends Component
{
    /**
     * Group label => [model name, ...]. Groups render in this order.
     *
     * @var array<string, array<int, string>>
     */
    public const MODEL_GROUPS = [
        'Task Automation'      => ['Titan 3.2', 'Gaia 2.0'],
        'Chat & Support'       => ['Kronos 1.5', 'Helios 4.0'],
        'Reporting & Insights' => ['Olympus Max'],
    ];

    /**
     * Group label => the badge every model in it wears. The icon says what the
     * group is for at a glance, so the three lists are told apart by shape and
     * not only by the heading above them.
     *
     * @var array<string, array{tint: string, icon: string}>
     */
    public const GROUP_STYLE = [
        'Task Automation' => [
            'tint' => 'bg-indigo-50 text-indigo-600 ring-indigo-600/15 dark:bg-indigo-500/10 dark:text-indigo-400 dark:ring-indigo-400/25',
            // sparkle
            'icon' => 'M12 3l1.7 4.8L18.5 9.5 13.7 11.2 12 16l-1.7-4.8L5.5 9.5l4.8-1.7L12 3zM18 15l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8L18 15z',
        ],
        'Chat & Support' => [
            'tint' => 'bg-sky-50 text-sky-600 ring-sky-600/15 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-400/25',
            // chat bubble
            'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
        ],
        'Reporting & Insights' => [
            'tint' => 'bg-amber-50 text-amber-600 ring-amber-600/15 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/25',
            // bar chart
            'icon' => 'M4 20h16M7.5 20v-6M12 20V8M16.5 20v-9',
        ],
    ];

    /**
     * The three summary cards. Each links through to the page it counts, which
     * is the only reason the count is worth showing at all.
     *
     * @return array<int, array{label: string, value: int|string, blurb: string, href: string, icon: string}>
     */
    public function stats(): array
    {
        return [
            [
                'label' => 'Plugins Connected',
                'value' => 0,
                'blurb' => 'Connect your tools to automate work',
                'href'  => route('ai.mcp'),
                // plug / connector
                'icon'  => 'M9 3v4M15 3v4M6 7h12v4a6 6 0 01-12 0V7zM12 17v4',
            ],
            [
                'label' => 'Scheduled Tasks',
                'value' => 0,
                'blurb' => 'Automations set to run on a schedule',
                'href'  => route('ai.scheduled-tasks'),
                // clock
                'icon'  => 'M12 8v4l3 1.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            ],
            [
                'label' => 'Models Available',
                'value' => count(Chatbot::MODELS),
                'blurb' => 'AI models ready to use in Chatbot',
                'href'  => route('ai.chatbot'),
                // sparkle
                'icon'  => 'M12 3l1.7 4.8L18.5 9.5 13.7 11.2 12 16l-1.7-4.8L5.5 9.5l4.8-1.7L12 3zM18 15l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8L18 15z',
            ],
        ];
    }

    public function render()
    {
        $quota = app(ChatQuota::class);

        return view('livewire.ai.overview', [
            'messagesRemaining' => $quota->remaining(auth()->user()),
            'messageLimit'      => $quota->limit(),
            'stats'             => $this->stats(),
            'modelGroups'       => self::MODEL_GROUPS,
            'groupStyles'       => self::GROUP_STYLE,
        ])->layout('layouts.app', ['pageTitle' => 'Clarix AI Overview']);
    }
}
