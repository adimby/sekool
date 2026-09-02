<?php

use App\Domain\Identity\Models\IdentityMerge;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school B read school A identity merges or approve them', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $first = $this->provisionEnrolledFamily($a, [
        'first_name' => 'Lala',
        'last_name' => 'Rabe',
    ]);
    $second = $this->provisionEnrolledFamily($a, [
        'first_name' => 'Lala',
        'last_name' => 'Rabe',
        'email' => 'lala.bis.'.uniqid().'@fanabe.test',
    ], [
        'first_name' => 'Nivo',
        'last_name' => 'Rabe',
    ]);

    $mergeId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/identity-merges", [
            'surviving_public_id' => $first['parent']->publicIdFormatted(),
            'duplicate_public_id' => $second['parent']->publicIdFormatted(),
            'reason' => 'Homonymie à vérifier.',
        ])
        ->assertCreated()
        ->json('data.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    expect(IdentityMerge::query()->pluck('id'))->not->toContain($mergeId)
        ->and(collect(DB::select('select id from identity_merges'))->pluck('id'))->not->toContain($mergeId);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/identity-merges")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/identity-merges")
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/identity-merges", [
            'surviving_public_id' => $first['parent']->publicIdFormatted(),
            'duplicate_public_id' => $second['parent']->publicIdFormatted(),
            'reason' => 'Intrusion',
        ])
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->postJson("/api/v1/platform/identity-merges/{$mergeId}/approve")
        ->assertNotFound();
});
