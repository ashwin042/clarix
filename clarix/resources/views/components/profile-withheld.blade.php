@props(['reason' => 'permission'])

{{--
    What a profile section shows in place of data the viewer may not read.

    Two reasons, deliberately worded apart. "Your role does not have this
    turned on" is something an administrator can fix in the Authorization
    panel; "not included in your plan" is a commercial fact they cannot. A
    single shared sentence would send people to the wrong person.
--}}
<div class="px-6 py-8 text-center">
    @if($reason === 'plan')
        <p class="text-sm text-gray-500 dark:text-slate-400">
            Not available — this isn't included in your plan.
        </p>
        <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">
            Ask an administrator about upgrading.
        </p>
    @else
        <p class="text-sm text-gray-500 dark:text-slate-400">
            Not available — your role does not have this turned on.
        </p>
        <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">
            Ask an administrator if you think you should see it.
        </p>
    @endif
</div>
