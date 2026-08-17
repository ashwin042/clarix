<?php

namespace App\Livewire\AI;

use App\Livewire\Traits\RequiresPlan;
use Livewire\Component;

/**
 * Scheduled Tasks & Automations.
 *
 * Nothing here runs. There is no trigger engine behind this page and no table
 * to read from, so rather than an empty screen that says only "coming soon",
 * the page shows the empty state above a dimmed preview of the automations a
 * live account would hold. Everything interactive is disabled: the create
 * button, and the enable/disable switch on each card.
 *
 * Each automation is a trigger -> integrations -> output chain, and the card
 * draws that chain as a small node graph. The previews are deliberately drawn
 * from work Clarix already tracks — completions, credit allocations, stalled
 * tasks, delivery deadlines — so they read as a plan rather than as filler.
 * When the engine lands, AUTOMATIONS is the only thing that goes.
 *
 * Integration logos are not defined here: they come from McpPlugins::brand(),
 * so the two pages cannot drift to different marks for the same product.
 */
class ScheduledTasks extends Component
{
    use RequiresPlan;

    /**
     * Trigger kind => the tint its pill wears. The colour carries meaning:
     * schedules are sky, and the event triggers take a tint that matches what
     * the event means (green for done, amber for stalling, rose for blocked,
     * violet for something a person sent in).
     */
    public const TRIGGER_TINT = [
        'done'     => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/25',
        'schedule' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-400/25',
        'stall'    => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/25',
        'flag'     => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/25',
        'chat'     => 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-400 dark:ring-violet-400/25',
    ];

    /**
     * Brand colours that are too dark to sit on the graph's near-black node
     * circles. Slack's own guidance is to go white on dark, so that is what
     * the node uses; the chip below the flow still shows the true colour on
     * its light background.
     */
    private const INK_ON_DARK = ['Slack' => '#E9E4EC', 'Notion' => '#E8E8E8'];

    /** The generic mark for MCP itself, which is not a plugin in the library. */
    private const MCP_BRAND = [
        'name'   => 'MCP',
        'colour' => '#6366F1',
        'logo'   => 'M12 2 21 7v10l-9 5-9-5V7l9-5Zm0 4.2L7 9v6l5 2.8L17 15V9l-5-2.8Z',
    ];

    /**
     * The six preview automations.
     *
     * 'kind' keys into TRIGGER_TINT. 'trigger_icon' and 'output_icon' are SVG
     * paths drawn at 24x24 with a 1.75 stroke, matching the rest of the AI
     * section. 'integrations' are looked up through integration().
     *
     * @var array<int, array<string, mixed>>
     */
    public const AUTOMATIONS = [
        [
            'kind'         => 'done',
            'trigger'      => 'Task Completed',
            'trigger_icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'title'        => 'Send delivered files to client',
            'description'  => 'When a task moves to Completed, attached files are automatically sent via WhatsApp or Gmail.',
            'output'       => 'Deliver',
            'output_icon'  => 'M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z',
            'integrations' => ['WhatsApp', 'Gmail'],
        ],
        [
            'kind'         => 'schedule',
            'trigger'      => 'Weekly (Every Monday)',
            'trigger_icon' => 'M8 7V3m8 4V3M4 11h16M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            'title'        => 'Weekly task & credit summary',
            'description'  => 'A summary of active tasks, progress, and credit usage is generated and sent to your team every Monday morning.',
            'output'       => 'Report',
            'output_icon'  => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'integrations' => ['Slack', 'Gmail'],
        ],
        [
            'kind'         => 'stall',
            'trigger'      => 'Task Stalled 48hrs',
            'trigger_icon' => 'M12 8v4l3 1.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'title'        => 'Flag and notify PM',
            'description'  => "If a task hasn't moved in 48 hours, AXOKAI flags it and notifies the assigned PM instantly.",
            'output'       => 'Notify',
            'output_icon'  => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
            'integrations' => ['Slack', 'Gmail'],
        ],
        [
            'kind'         => 'flag',
            'trigger'      => 'Task Flagged by AI',
            // flag
            'trigger_icon' => 'M4 21V4.5a.5.5 0 01.3-.46C5.5 3.5 7 3.2 8.7 3.9c2.3.94 4.3 1.9 6.6.94 1-.42 1.9-.5 2.6-.42a.5.5 0 01.44.5v8.1a.5.5 0 01-.3.46c-1.2.54-2.7.84-4.4.14-2.3-.94-4.3-1.9-6.6-.94a4.9 4.9 0 00-1.04.56',
            'title'        => 'Missing information alert',
            'description'  => 'When AXOKAI flags a task for missing details needed to begin work, the assigned PM gets an instant alert on WhatsApp or Slack, whichever is connected.',
            'output'       => 'Alert',
            'output_icon'  => 'M12 9v3.5m0 3.5h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
            'integrations' => ['WhatsApp', 'Slack'],
        ],
        [
            'kind'         => 'schedule',
            'trigger'      => 'Monthly (1st of month)',
            'trigger_icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
            'title'        => 'Backup task and credit data',
            'description'  => 'All task records, files, and credit logs from the past month are backed up automatically to connected storage.',
            'output'       => 'Archive',
            'output_icon'  => 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M12 12v9m0 0l-3-3m3 3l3-3',
            'integrations' => ['Google Drive', 'Cloudflare R2'],
        ],
        [
            'kind'         => 'chat',
            'trigger'      => 'Task Update via Chat',
            // chat bubble
            'trigger_icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
            'title'        => 'Send task details straight to Clarix',
            'description'  => 'Message task details directly to our bot on WhatsApp or Slack. It uploads straight into the portal automatically, no need to log in and enter it manually.',
            'output'       => 'Upload',
            // tray with an arrow going up into it
            'output_icon'  => 'M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 15V3m0 0L8 7m4-4l4 4',
            'integrations' => ['MCP'],
        ],
    ];

    /**
     * A drawable integration: the brand mark plus the colour to use when it
     * sits on the graph's dark node circle.
     *
     * @return array{name: string, colour: string, logo: string, ink: string}
     */
    public static function integration(string $name): array
    {
        $brand = $name === self::MCP_BRAND['name']
            ? self::MCP_BRAND
            : McpPlugins::brand($name) ?? ['name' => $name, 'colour' => '#6B7280', 'logo' => ''];

        return $brand + ['ink' => self::INK_ON_DARK[$name] ?? $brand['colour']];
    }

    /**
     * The automations with their integrations already resolved to marks, so
     * the view never has to reach back into the plugin library.
     *
     * @return array<int, array<string, mixed>>
     */
    public function automations(): array
    {
        return array_map(function (array $automation): array {
            $automation['integrations'] = array_map(
                static fn (string $name): array => self::integration($name),
                $automation['integrations']
            );

            return $automation;
        }, self::AUTOMATIONS);
    }

    public function render()
    {
        $this->assertPlanIncludes('automation');

        return view('livewire.ai.scheduled-tasks', [
            'automations' => $this->automations(),
            'tints'       => self::TRIGGER_TINT,
        ])->layout('layouts.app', ['pageTitle' => 'Scheduled Tasks & Automations']);
    }
}
