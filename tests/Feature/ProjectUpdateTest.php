<?php

use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MProject;
use App\Models\MSkillSet;
use App\Models\MUser;
use App\Models\TrInternProject;
use App\Models\TrProjectMentor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps project assignment when untouched multiselects submit only hidden empty values', function () {
    [$user, $intern, $mentor, $project] = createProjectAssignmentFixture();

    $this->withSession(['auth_user_id' => $user->intUser_ID])
        ->put(route('projects.update', $project->intProject_ID), projectUpdatePayload($project, [
            'intIntern_ID_touched' => '0',
            'intIntern_ID' => [''],
            'intMentor_ID_touched' => '0',
            'intMentor_ID' => [''],
            'floatProgress' => '50',
        ]))
        ->assertRedirect(route('projects.index'));

    $assignment = TrInternProject::where('intProject_ID', $project->intProject_ID)->where('bitActive', true)->first();
    $projectMentor = TrProjectMentor::where('intProject_ID', $project->intProject_ID)->where('bitActive', true)->first();

    expect($assignment->bitActive)->toBeTrue()
        ->and($assignment->intIntern_ID)->toBe($intern->intIntern_ID)
        ->and($assignment->intMentor_ID)->toBe($mentor->intMentor_ID)
        ->and((int) $assignment->floatProgress)->toBe(50)
        ->and($projectMentor->bitActive)->toBeTrue()
        ->and($projectMentor->intMentor_ID)->toBe($mentor->intMentor_ID);
});

it('recovers latest inactive assignment when saving an already emptied project edit form', function () {
    [$user, $intern, $mentor, $project] = createProjectAssignmentFixture(active: false);

    $this->withSession(['auth_user_id' => $user->intUser_ID])
        ->put(route('projects.update', $project->intProject_ID), projectUpdatePayload($project, [
            'intIntern_ID_touched' => '0',
            'intIntern_ID' => [''],
            'intMentor_ID_touched' => '0',
            'intMentor_ID' => [''],
            'floatProgress' => '75',
        ]))
        ->assertRedirect(route('projects.index'));

    $assignment = TrInternProject::where('intProject_ID', $project->intProject_ID)->where('bitActive', true)->first();
    $projectMentor = TrProjectMentor::where('intProject_ID', $project->intProject_ID)->where('bitActive', true)->first();

    expect($assignment->bitActive)->toBeTrue()
        ->and($assignment->intIntern_ID)->toBe($intern->intIntern_ID)
        ->and($assignment->intMentor_ID)->toBe($mentor->intMentor_ID)
        ->and((int) $assignment->floatProgress)->toBe(75)
        ->and($projectMentor->bitActive)->toBeTrue()
        ->and($projectMentor->intMentor_ID)->toBe($mentor->intMentor_ID);
});

it('rejects project creation without stages', function () {
    [$user, $intern, $mentor, $project] = createProjectAssignmentFixture();

    $this->withSession(['auth_user_id' => $user->intUser_ID])
        ->from(route('projects.create'))
        ->post(route('projects.store'), [
            'txtProjectName' => 'Project Without Stages',
            'txtProjectType' => 'Main',
            'intSkillSet_ID' => $project->intSkillSet_ID,
            'dtmProjectStartDate' => '2026-07-01',
            'dtmProjectEndDate' => '2026-07-31',
            'txtDescription' => 'Missing stages',
            'bitActive' => '1',
            'floatProgress' => '0',
        ])
        ->assertRedirect(route('projects.create'))
        ->assertSessionHasErrors(['stages' => 'Isi tahap project terlebih dahulu.']);
});

function createProjectAssignmentFixture(bool $active = true): array
{
    $now = now();
    $user = MUser::create([
        'txtEmail' => 'mentor@example.test',
        'txtPassword' => 'secret',
        'txtRole' => 'Mentor',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => $now,
    ]);
    $mentor = MMentor::create([
        'intUser_ID' => $user->intUser_ID,
        'txtMentorName' => 'Test Mentor',
        'txtRole' => 'Mentor',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => $now,
    ]);
    $internUser = MUser::create([
        'txtEmail' => 'intern@example.test',
        'txtPassword' => 'secret',
        'txtRole' => 'Intern',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => $now,
    ]);
    $intern = MIntern::create([
        'intUser_ID' => $internUser->intUser_ID,
        'txtInternNo' => 'INT-001',
        'txtInternName' => 'Test Intern',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => $now,
    ]);
    $skillSet = MSkillSet::create([
        'txtSkillSetName' => 'Web Development',
        'txtSkillSetDescription' => 'Test skill set',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => $now,
    ]);
    $project = MProject::create([
        'txtProjectName' => 'Project Alpha',
        'txtProjectType' => 'Main',
        'intSkillSet_ID' => $skillSet->intSkillSet_ID,
        'dtmProjectStartDate' => '2026-07-01',
        'dtmProjectEndDate' => '2026-07-31',
        'txtDescription' => 'Initial project',
        'bitActive' => true,
        'txtInsertedBy' => 'test',
        'dtmInserted' => $now,
    ]);

    TrInternProject::create([
        'intIntern_ID' => $intern->intIntern_ID,
        'intProject_ID' => $project->intProject_ID,
        'intMentor_ID' => $mentor->intMentor_ID,
        'floatProgress' => 25,
        'txtStatus' => 'Inprogress',
        'bitActive' => $active,
        'txtInsertedBy' => 'test',
        'dtmInserted' => $now,
        'txtUpdatedBy' => $active ? null : 'test',
        'dtmUpdated' => $active ? null : $now,
    ]);
    TrProjectMentor::create([
        'intProject_ID' => $project->intProject_ID,
        'intMentor_ID' => $mentor->intMentor_ID,
        'bitActive' => $active,
        'txtInsertedBy' => 'test',
        'dtmInserted' => $now,
        'txtUpdatedBy' => $active ? null : 'test',
        'dtmUpdated' => $active ? null : $now,
    ]);

    return [$user, $intern, $mentor, $project];
}

function projectUpdatePayload(MProject $project, array $overrides = []): array
{
    return array_merge([
        'txtProjectName' => $project->txtProjectName,
        'txtProjectType' => $project->txtProjectType,
        'intSkillSet_ID' => $project->intSkillSet_ID,
        'dtmProjectStartDate' => '2026-07-01',
        'dtmProjectEndDate' => '2026-07-31',
        'txtDescription' => 'Updated project',
        'bitActive' => '1',
        'floatProgress' => '25',
        'stages_present' => '1',
        'stages' => [
            [
                'txtProjectStageStep' => 'Planning',
                'dtmProjectStageStartDate' => '2026-07-01',
                'dtmProjectStageEndDate' => '2026-07-31',
                'floatProjectStagePlan' => '100',
                'floatProjectStageActual' => '25',
            ],
        ],
    ], $overrides);
}
