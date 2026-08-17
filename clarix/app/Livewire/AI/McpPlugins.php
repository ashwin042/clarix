<?php

namespace App\Livewire\AI;

use App\Livewire\Traits\RequiresPlan;
use Livewire\Component;

/**
 * MCP & Plugins.
 *
 * The plugin library is presentational: none of these integrations exist yet,
 * so every card's configuration panel renders its fields disabled and there is
 * nothing to persist. The shape of the data is the part that matters — when an
 * integration goes live it needs a logo, a category, and its own field list,
 * which is exactly what each entry carries here.
 *
 * 'fields' is [label, placeholder, type]; type is only used to pick the input
 * type, and every input is disabled regardless.
 */
class McpPlugins extends Component
{
    use RequiresPlan;

    /** Category => the tint its pill uses. */
    public const CATEGORY_TINT = [
        'Communication' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',
        'Data'          => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'Storage'       => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'Productivity'  => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400',
        'Payments'      => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
    ];

    /**
     * The brand mark for one plugin, or null if it is not in the library.
     * Static so the rest of the AI section can draw the same logos without
     * copying the paths — the Scheduled Tasks automations use this.
     *
     * @return array{name: string, colour: string, logo: string}|null
     */
    public static function brand(string $name): ?array
    {
        foreach (self::plugins() as $plugin) {
            if ($plugin['name'] === $name) {
                return [
                    'name'   => $plugin['name'],
                    'colour' => $plugin['colour'],
                    'logo'   => $plugin['logo'],
                ];
            }
        }

        return null;
    }

    public static function plugins(): array
    {
        return [
            [
                'name' => 'Slack', 'category' => 'Communication', 'colour' => '#4A154B',
                'blurb' => 'Get task updates and alerts in Slack',
                'fields' => [
                    ['Workspace URL', 'https://your-agency.slack.com', 'url'],
                    ['Default channel', '#client-updates', 'text'],
                ],
                'logo' => 'M5.04 15.17a2.53 2.53 0 0 1-2.52 2.52A2.53 2.53 0 0 1 0 15.17a2.53 2.53 0 0 1 2.52-2.52h2.52v2.52Zm1.27 0a2.53 2.53 0 0 1 2.52-2.52 2.53 2.53 0 0 1 2.52 2.52v6.31A2.53 2.53 0 0 1 8.83 24a2.53 2.53 0 0 1-2.52-2.52v-6.31ZM8.83 5.04a2.53 2.53 0 0 1-2.52-2.52A2.53 2.53 0 0 1 8.83 0a2.53 2.53 0 0 1 2.52 2.52v2.52H8.83Zm0 1.27a2.53 2.53 0 0 1 2.52 2.52 2.53 2.53 0 0 1-2.52 2.52H2.52A2.53 2.53 0 0 1 0 8.83a2.53 2.53 0 0 1 2.52-2.52h6.31ZM18.96 8.83a2.53 2.53 0 0 1 2.52-2.52A2.53 2.53 0 0 1 24 8.83a2.53 2.53 0 0 1-2.52 2.52h-2.52V8.83Zm-1.27 0a2.53 2.53 0 0 1-2.52 2.52 2.53 2.53 0 0 1-2.52-2.52V2.52A2.53 2.53 0 0 1 15.17 0a2.53 2.53 0 0 1 2.52 2.52v6.31ZM15.17 18.96a2.53 2.53 0 0 1 2.52 2.52A2.53 2.53 0 0 1 15.17 24a2.53 2.53 0 0 1-2.52-2.52v-2.52h2.52Zm0-1.27a2.53 2.53 0 0 1-2.52-2.52 2.53 2.53 0 0 1 2.52-2.52h6.31A2.53 2.53 0 0 1 24 15.17a2.53 2.53 0 0 1-2.52 2.52h-6.31Z',
            ],
            [
                'name' => 'WhatsApp', 'category' => 'Communication', 'colour' => '#25D366',
                'blurb' => 'Send client updates over WhatsApp',
                'fields' => [['Business phone number', '+977 98XXXXXXXX', 'tel']],
                'logo' => 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z',
            ],
            [
                'name' => 'Telegram', 'category' => 'Communication', 'colour' => '#26A5E4',
                'blurb' => 'Notify your team through Telegram',
                'fields' => [
                    ['Bot token', '123456789:AAE...', 'text'],
                    ['Chat ID', '-1001234567890', 'text'],
                ],
                'logo' => 'M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z',
            ],
            [
                'name' => 'Gmail', 'category' => 'Communication', 'colour' => '#EA4335',
                'blurb' => 'Sync briefs and replies from Gmail',
                'fields' => [
                    ['Connected account', 'briefs@your-agency.com', 'email'],
                    ['Label to watch', 'Clarix/Briefs', 'text'],
                ],
                'logo' => 'M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.91 1.528-1.145C21.69 2.28 24 3.434 24 5.457z',
            ],
            [
                'name' => 'Google Sheets', 'category' => 'Data', 'colour' => '#34A853',
                'blurb' => 'Sync tasks and credits to a live spreadsheet',
                'fields' => [
                    ['Sheet link', 'https://docs.google.com/spreadsheets/d/...', 'url'],
                    ['Tab name', 'Tasks', 'text'],
                ],
                'logo' => 'M11.318 12.545H7.909v-1.909h3.409v1.909zm4.773 0h-3.409v-1.909h3.409v1.909zm-4.773 3.273H7.909v-1.91h3.409v1.91zm4.773 0h-3.409v-1.91h3.409v1.91zM14.727 0v6h6l-6-6zM19.5 24H4.5A1.5 1.5 0 0 1 3 22.5v-21A1.5 1.5 0 0 1 4.5 0h9.75L21 6.75V22.5A1.5 1.5 0 0 1 19.5 24zm-13-15.5v9h11v-9h-11z',
            ],
            [
                'name' => 'Google Drive', 'category' => 'Storage', 'colour' => '#4285F4',
                'blurb' => 'Attach and sync files from Drive',
                'fields' => [['Folder link', 'https://drive.google.com/drive/folders/...', 'url']],
                'logo' => 'M7.71 3.5 1.15 15l3.43 5.94L11.14 9.44 7.71 3.5zm1.75 11.44L6.03 20.94h13.72l3.43-5.94H9.46zm6.83-11.44H9.44l6.86 11.88h6.86L16.29 3.5z',
            ],
            [
                'name' => 'Cloudflare R2', 'category' => 'Storage', 'colour' => '#F38020',
                'blurb' => 'Connect your own storage bucket',
                'fields' => [
                    ['Account ID', 'a1b2c3d4e5f6...', 'text'],
                    ['Bucket name', 'clarix-task-files', 'text'],
                    ['Access key ID', 'Stored encrypted once saved', 'password'],
                ],
                'logo' => 'M16.51 15.53c.15-.52.09-1-.16-1.36-.23-.33-.62-.52-1.09-.54l-8.92-.11a.17.17 0 0 1-.14-.07.18.18 0 0 1-.02-.16c.02-.08.1-.14.19-.15l9-.11c1.07-.05 2.23-.92 2.63-1.97l.51-1.34a.3.3 0 0 0 .01-.17A5.94 5.94 0 0 0 12.8 5.1a5.93 5.93 0 0 0-5.7 4.13 2.67 2.67 0 0 0-4.19 2.8A3.79 3.79 0 0 0 0 15.75c0 .18.01.36.04.53.01.08.08.14.16.14h16.03a.2.2 0 0 0 .19-.14l.09-.75zM19.4 9.9c-.08 0-.17 0-.25.01a.15.15 0 0 0-.13.1l-.35 1.2c-.15.52-.09 1 .16 1.36.23.33.62.52 1.09.54l1.9.11c.06 0 .11.03.14.07a.18.18 0 0 1 .02.16c-.02.08-.1.14-.19.15l-1.98.11c-1.07.05-2.23.92-2.63 1.97l-.14.36a.1.1 0 0 0 .09.14h6.8a.16.16 0 0 0 .16-.12c.12-.42.18-.87.18-1.33a4.62 4.62 0 0 0-4.87-4.83z',
            ],
            [
                'name' => 'Notion', 'category' => 'Productivity', 'colour' => '#111111',
                'blurb' => 'Mirror project docs to Notion',
                'fields' => [
                    ['Database link', 'https://notion.so/your-agency/...', 'url'],
                    ['Internal integration token', 'secret_...', 'password'],
                ],
                'logo' => 'M4.459 4.208c.746.606 1.026.56 2.428.466l13.215-.793c.28 0 .047-.28-.046-.326L17.86 1.968c-.42-.326-.981-.7-2.055-.607L3.01 2.295c-.466.046-.56.28-.374.466zm.793 3.08v13.904c0 .747.373 1.027 1.214.98l14.523-.84c.841-.046.935-.56.935-1.167V6.354c0-.606-.233-.933-.748-.887l-15.177.887c-.56.047-.747.327-.747.933zm14.337.745c.093.42 0 .84-.42.888l-.7.14v10.264c-.608.327-1.168.514-1.635.514-.748 0-.935-.234-1.495-.933l-4.577-7.186v6.952l1.448.327s0 .84-1.168.84l-3.222.186c-.093-.186 0-.653.327-.746l.84-.233V9.854l-1.166-.094c-.094-.42.14-1.026.793-1.073l3.456-.233 4.764 7.279v-6.44l-1.215-.139c-.093-.514.28-.887.747-.933zM1.936 1.035l13.31-.98c1.634-.14 2.055-.047 3.082.7l4.249 2.986c.7.513.934.653.934 1.213v16.378c0 1.026-.373 1.634-1.68 1.726l-15.458.934c-.98.047-1.448-.093-1.962-.747l-3.129-4.06c-.56-.747-.793-1.306-.793-1.96V2.667c0-.839.374-1.54 1.447-1.632z',
            ],
            [
                'name' => 'Zoom', 'category' => 'Communication', 'colour' => '#0B5CFF',
                'blurb' => 'Schedule and log client calls',
                'fields' => [['Account email', 'calls@your-agency.com', 'email']],
                'logo' => 'M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zM6.4 8.8h6.53c.66 0 1.2.54 1.2 1.2v5.2c0 .66-.54 1.2-1.2 1.2H6.4c-.66 0-1.2-.54-1.2-1.2V10c0-.66.54-1.2 1.2-1.2zm12.06.36c.24-.14.54.03.54.31v5.06c0 .28-.3.45-.54.31l-3.2-1.86v-1.96l3.2-1.86z',
            ],
            [
                'name' => 'Stripe', 'category' => 'Payments', 'colour' => '#635BFF',
                'blurb' => 'Sync invoices and payments',
                'fields' => [
                    ['Secret API key', 'sk_live_...', 'password'],
                    ['Webhook signing secret', 'whsec_...', 'password'],
                ],
                'logo' => 'M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.315 22.99 8.44 24 11.977 24c2.638 0 4.836-.623 6.318-1.813 1.634-1.305 2.468-3.226 2.468-5.732 0-4.128-2.524-5.851-6.787-7.305z',
            ],
        ];
    }

    public function render()
    {
        $this->assertPlanIncludes('automation');

        return view('livewire.ai.mcp-plugins', [
            'plugins' => self::plugins(),
            'tints'   => self::CATEGORY_TINT,
        ])->layout('layouts.app', ['pageTitle' => 'MCP & Plugins']);
    }
}
