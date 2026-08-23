<?php

namespace Tests\Unit\Services;

use App\Services\TaskCreationService;
use Tests\TestCase;

/**
 * TaskCreationService::rules() — specifically the unit the task_code has to be
 * unique within, which is the one part of the rule set built from an argument.
 *
 * Worth a test of its own because the argument is now genuinely nullable. Every
 * caller before the task bot's admin branch had a unit by the time it got here,
 * so "what does this render with no unit" had never been asked, and the answer
 * it gave was a rule whose behaviour depended on the database driver.
 */
class TaskCreationRulesTest extends TestCase
{
    private function uniqueRuleFor(?int $unitId): string
    {
        $rules = TaskCreationService::rules($unitId);

        foreach ((array) $rules['task_code'] as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'unique:')) {
                return $rule;
            }
        }

        $this->fail('task_code carries no unique rule.');
    }

    public function test_the_code_is_unique_within_the_given_unit(): void
    {
        $this->assertSame(
            'unique:tasks,task_code,NULL,id,unit_id,7',
            $this->uniqueRuleFor(7)
        );
    }

    /**
     * With no unit named the rule has to say NULL, which Laravel turns into
     * whereNull. Interpolating the null instead rendered a trailing empty
     * parameter — `unit_id,` — and an empty string is not a missing value: it
     * reaches the driver as `where unit_id = ''`, which sqlite quietly matches
     * nothing and MySQL coerces to 0 with a truncation warning. Two drivers
     * agreeing by accident is not the same as a rule that means something, and
     * tasks.unit_id is NOT NULL, so whereNull is also the honest answer —
     * nothing can collide with a code filed against no unit.
     */
    public function test_no_unit_renders_a_null_the_query_builder_understands(): void
    {
        $this->assertSame(
            'unique:tasks,task_code,NULL,id,unit_id,NULL',
            $this->uniqueRuleFor(null)
        );
    }
}
