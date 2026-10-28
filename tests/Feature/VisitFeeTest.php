<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\RegistryStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_count_each_fee_amount_and_keep_free_apart_from_not_recorded()
    {
        $patient = Patient::factory()->create();
        Visit::factory()->for($patient)->create(['fee' => 100000, 'visit_date' => '2026-05-03']);
        Visit::factory()->for($patient)->create(['fee' => 100000, 'visit_date' => '2026-06-10']);
        Visit::factory()->for($patient)->create(['fee' => 75000, 'visit_date' => '2026-06-20']);
        Visit::factory()->for($patient)->create(['fee' => 0, 'visit_date' => '2026-06-21']);
        Visit::factory()->for($patient)->create(['fee' => 50000, 'visit_date' => null]);
        Visit::factory()->for($patient)->create(['fee' => null]);
        Visit::factory()->for(Patient::factory()->create(['unverified' => true]))->create(['fee' => 100000]);

        $fees = app(RegistryStatistics::class)->calculate(null, null)['fees'];

        $this->assertSame(325000, $fees['total']);
        $this->assertSame([6, 5, 1], [$fees['visits'], $fees['recorded'], $fees['free']]);
        $this->assertSame([
            ['fee' => 100000, 'visits' => 2, 'total' => 200000],
            ['fee' => 75000, 'visits' => 1, 'total' => 75000],
            ['fee' => 50000, 'visits' => 1, 'total' => 50000],
            ['fee' => 0, 'visits' => 1, 'total' => 0],
        ], $fees['amounts']);
        $this->assertSame([
            ['month' => '2026-06', 'visits' => 3, 'total' => 175000],
            ['month' => '2026-05', 'visits' => 1, 'total' => 100000],
            ['month' => null, 'visits' => 1, 'total' => 50000],
        ], $fees['months']);
    }

    public function test_the_visit_list_totals_the_fees_of_all_matching_visits()
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['name' => 'Baby Fee']);
        Visit::factory()->for($patient)->create(['fee' => 100000, 'visit_date' => '2026-05-03']);
        Visit::factory()->for($patient)->create(['fee' => 75000, 'visit_date' => '2026-06-10']);
        Visit::factory()->for($patient)->create(['fee' => null, 'visit_date' => '2026-06-11']);

        $this->actingAs($user)->get(route('visits.index', ['per_page' => 10, 'sort' => 'fee', 'direction' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('fees', ['total' => 175000, 'recorded' => 2])
                ->where('visits.data.0.fee', 100000)
                ->where('visits.data.2.fee', null));

        $this->actingAs($user)->get(route('visits.index', ['from' => '2026-06-01']))
            ->assertInertia(fn (Assert $page) => $page->where('fees', ['total' => 75000, 'recorded' => 1]));
    }
}
