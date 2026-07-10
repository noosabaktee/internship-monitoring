<?php

namespace Database\Seeders;

use App\Models\MIntern;
use App\Models\MAttendanceSetting;
use App\Models\MFaceEnrollment;
use App\Models\MMentor;
use App\Models\MProject;
use App\Models\MProjectHandle;
use App\Models\MProjectWeight;
use App\Models\MSkillSet;
use App\Models\MUser;
use App\Models\TrAchievement;
use App\Models\TrAttendance;
use App\Models\TrCalendarSharing;
use App\Models\TrEvaluation;
use App\Models\TrInternProject;
use App\Models\TrProjectMentor;
use App\Models\TrProjectStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->clearCustomTables();

            $password = Hash::make('123456');
            $internRows = $this->internRows();
            $progressStatuses = [
                0 => 'Open',
                25 => 'Inprogress',
                50 => 'Project Review',
                75 => 'Trial/testing',
                100 => 'Completed',
            ];
            $skillSetIds = $this->seedSkillSets();
            $this->seedAttendanceSetting();
            $this->seedProjectHandles();
            $this->seedProjectWeight();
            $mentorNames = collect($internRows)->pluck('mentor')->unique()->values();
            $mentorIds = [];

            foreach ($mentorNames as $index => $mentorName) {
                $mentorId = $index + 1;
                $userId = 100 + $mentorId;
                $mentorRows = collect($internRows)->where('mentor', $mentorName);

                MUser::create([
                    'intUser_ID' => $userId,
                    'txtEmail' => $this->emailFromName($mentorName),
                    'txtPassword' => $password,
                    'txtRole' => 'Mentor',
                    'bitActive' => true,
                    'txtInsertedBy' => 'seeder',
                    'dtmInserted' => now(),
                ]);

                MMentor::create([
                    'intMentor_ID' => $mentorId,
                    'intUser_ID' => $userId,
                    'txtMentorName' => $mentorName,
                    'txtMentorGender' => null,
                    'txtDepartment' => $mentorRows->first()['department'],
                    'txtRole' => 'Mentor',
                    'bitActive' => true,
                    'txtInsertedBy' => 'seeder',
                    'dtmInserted' => now(),
                ]);

                $mentorIds[$mentorName] = $mentorId;
            }

            $projectIds = [];
            $nextProjectId = 1;
            $nextAssignmentId = 1;
            $nextProjectMentorId = 1;
            $projectMentorPairs = [];

            foreach ($internRows as $index => $row) {
                $internId = $index + 1;
                $userId = $internId;

                MUser::create([
                    'intUser_ID' => $userId,
                    'txtEmail' => $this->emailFromName($row['name']),
                    'txtPassword' => $password,
                    'txtRole' => 'Intern',
                    'bitActive' => true,
                    'txtInsertedBy' => 'seeder',
                    'dtmInserted' => $this->dateFromSheet($row['join_date']),
                ]);

                MIntern::create([
                    'intIntern_ID' => $internId,
                    'intUser_ID' => $userId,
                    'txtInternNo' => 'INT-' . str_pad((string) $internId, 3, '0', STR_PAD_LEFT),
                    'txtInternName' => $row['name'],
                    'txtInternGender' => $row['gender'],
                    'txtUniversity' => $row['university'],
                    'txtDept' => $row['department'],
                    'txtBio' => null,
                    'dtmEndDate' => $this->dateFromSheet($row['end_date']),
                    'txtInternExtendEndDates' => [],
                    'bitActive' => true,
                    'txtInsertedBy' => 'seeder',
                    'dtmInserted' => $this->dateFromSheet($row['join_date']),
                ]);

                foreach ($row['projects'] as $projectType => $projectName) {
                    if ($projectName === '') {
                        continue;
                    }

                    $projectKey = $projectType . '|' . $projectName;

                    if (! isset($projectIds[$projectKey])) {
                        $projectIds[$projectKey] = $nextProjectId;

                        MProject::create([
                            'intProject_ID' => $nextProjectId,
                            'txtProjectName' => $projectName,
                            'txtProjectType' => $projectType,
                            'intSkillSet_ID' => $this->skillSetIdForProject($projectName, $projectType, $skillSetIds),
                            'dtmProjectStartDate' => $this->dateFromSheet($row['join_date']),
                            'dtmProjectEndDate' => $this->dateFromSheet($row['end_date']),
                            'txtDescription' => $projectType . ' project for ' . $row['department'],
                            'bitActive' => true,
                            'txtInsertedBy' => 'seeder',
                            'dtmInserted' => $this->dateFromSheet($row['join_date']),
                        ]);
                        $this->seedProjectStages(
                            $nextProjectId,
                            $projectType,
                            $this->dateFromSheet($row['join_date']),
                            $this->dateFromSheet($row['end_date']),
                        );

                        $nextProjectId++;
                    }

                    $progress = array_rand($progressStatuses);

                    TrInternProject::create([
                        'intInternProject_ID' => $nextAssignmentId,
                        'intIntern_ID' => $internId,
                        'intProject_ID' => $projectIds[$projectKey],
                        'intMentor_ID' => $mentorIds[$row['mentor']],
                        'floatProgress' => $progress,
                        'txtStatus' => $progressStatuses[$progress],
                        'bitActive' => true,
                        'txtInsertedBy' => 'seeder',
                        'dtmInserted' => $this->dateFromSheet($row['join_date']),
                    ]);

                    $projectMentorKey = $projectIds[$projectKey] . '|' . $mentorIds[$row['mentor']];

                    if (! isset($projectMentorPairs[$projectMentorKey])) {
                        TrProjectMentor::create([
                            'intProjectMentor_ID' => $nextProjectMentorId,
                            'intProject_ID' => $projectIds[$projectKey],
                            'intMentor_ID' => $mentorIds[$row['mentor']],
                            'bitActive' => true,
                            'txtInsertedBy' => 'seeder',
                            'dtmInserted' => $this->dateFromSheet($row['join_date']),
                        ]);

                        $projectMentorPairs[$projectMentorKey] = true;
                        $nextProjectMentorId++;
                    }

                    $nextAssignmentId++;
                }

                $hardSkill = random_int(72, 96);
                $collaboration = random_int(70, 95);
                $ownership = random_int(73, 97);
                $sharing = random_int(68, 94);
                $projectName = collect($row['projects'])->filter()->first() ?? 'Internship Program';

                TrEvaluation::create([
                    'intEvaluation_ID' => $internId,
                    'intIntern_ID' => $internId,
                    'dtmPeriod' => $this->dateFromSheet($row['join_date'])->copy()->addMonth()->startOfMonth(),
                    'floatHardSkill' => $hardSkill,
                    'floatCollaboration' => $collaboration,
                    'floatOwnership' => $ownership,
                    'floatSharing' => $sharing,
                    'floatExposureScore' => round(($hardSkill + $collaboration + $ownership + $sharing) / 4, 2),
                    'bitActive' => true,
                    'txtInsertedBy' => 'seeder',
                    'dtmInserted' => $this->dateFromSheet($row['join_date']),
                ]);

                TrAchievement::create([
                    'intAchievement_ID' => $internId,
                    'intIntern_ID' => $internId,
                    'txtAchievementTitle' => 'Project Milestone',
                    'txtDescription' => $row['name'] . ' contributed to ' . $projectName . '.',
                    'txtIcon' => ['fa-solid fa-trophy', 'fa-solid fa-award', 'fa-solid fa-medal', 'fa-solid fa-star'][$index % 4],
                    'dtmAwarded' => $this->dateFromSheet($row['join_date'])->copy()->addMonths(2),
                    'bitActive' => true,
                    'txtInsertedBy' => 'seeder',
                    'dtmInserted' => $this->dateFromSheet($row['join_date']),
                ]);
            }
            $this->seedCalendarSharings();
        });
    }

    private function clearCustomTables(): void
    {
        TrAttendance::query()->delete();
        MFaceEnrollment::query()->delete();
        TrCalendarSharing::query()->delete();
        TrAchievement::query()->delete();
        TrEvaluation::query()->delete();
        TrProjectMentor::query()->delete();
        TrInternProject::query()->delete();
        TrProjectStage::query()->delete();
        MProject::query()->delete();
        MSkillSet::query()->delete();
        MProjectHandle::query()->delete();
        MProjectWeight::query()->delete();
        MAttendanceSetting::query()->delete();
        MIntern::query()->delete();
        MMentor::query()->delete();
        MUser::query()->delete();
    }

    private function seedAttendanceSetting(): void
    {
        MAttendanceSetting::create([
            'intAttendanceSetting_ID' => 1,
            'txtAttendanceSettingStartTime' => '06:30',
            'txtAttendanceSettingEndTime' => '18:30',
            'txtAttendanceSettingClockInStartTime' => '06:30',
            'txtAttendanceSettingClockInEndTime' => '09:00',
            'txtAttendanceSettingClockOutStartTime' => '16:00',
            'txtAttendanceSettingClockOutEndTime' => '18:30',
            'floatAttendanceSettingFaceThreshold' => 0.38,
            'bitAttendanceSettingLocationRequired' => true,
            'bitActive' => true,
            'txtInsertedBy' => 'seeder',
            'dtmInserted' => now(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function seedSkillSets(): array
    {
        $rows = [
            'Web Development' => 'Web application, dashboard, and workflow interface development.',
            'Embedded Systems & IoT Data Acquisition' => 'Sensor, machine, and operational data acquisition.',
            'AI & Computer Vision' => 'AI model, visual inspection, and computer vision initiatives.',
            'Robotic Process Automation (RPA)' => 'Workflow automation and repetitive process orchestration.',
            'Engineering Modeling & Simulation' => '3D modeling, CFD, digital twin, and engineering simulation.',
            'Reverse Engineering' => 'Reverse engineering and technical reconstruction activities.',
        ];
        $ids = [];

        foreach ($rows as $skillSetName => $description) {
            $id = count($ids) + 1;

            MSkillSet::create([
                'intSkillSet_ID' => $id,
                'txtSkillSetName' => $skillSetName,
                'txtSkillSetDescription' => $description,
                'bitActive' => true,
                'txtInsertedBy' => 'seeder',
                'dtmInserted' => now(),
            ]);

            $ids[$skillSetName] = $id;
        }

        return $ids;
    }

    private function seedProjectHandles(): void
    {
        foreach ([
            ['1 month', 0, 1, 1, 1],
            ['3 months', 1, 1, 1, 2],
            ['6 months', 2, 2, 2, 3],
            ['1 year', 3, 4, 3, 4],
        ] as $index => [$duration, $main, $collaboration, $satellite, $sharing]) {
            MProjectHandle::create([
                'intProjectHandle_ID' => $index + 1,
                'txtProjectHandleDuration' => $duration,
                'intProjectHandleMain' => $main,
                'intProjectHandleCollaboration' => $collaboration,
                'intProjectHandleSatellite' => $satellite,
                'intProjectHandleSharing' => $sharing,
                'bitActive' => true,
                'txtInsertedBy' => 'seeder',
                'dtmInserted' => now(),
            ]);
        }
    }

    private function seedProjectWeight(): void
    {
        MProjectWeight::create([
            'intProjectWeight_ID' => 1,
            'intProjectWeightMain' => 10,
            'intProjectWeightCollaboration' => 6,
            'intProjectWeightSatellite' => 2,
            'intProjectWeightSharing' => 4,
            'bitActive' => true,
            'txtInsertedBy' => 'seeder',
            'dtmInserted' => now(),
        ]);
    }

    private function seedCalendarSharings(): void
    {
        $creatorIds = MUser::where('txtRole', 'Mentor')->orderBy('intUser_ID')->pluck('intUser_ID')->values();

        if ($creatorIds->isEmpty()) {
            $creatorIds = MUser::orderBy('intUser_ID')->pluck('intUser_ID')->values();
        }

        $rows = [
            ['Workflow Automation with n8n', 'Understand RPA workflow design for engineering operations.', 'Hands-on sharing about automation opportunities and simple workflow patterns.', 'Engineering Interns', now()->subDays(5), 'Complete', 'fa-solid fa-robot'],
            ['Digital Twin Project Review', 'Align simulation model progress and expected project output.', 'Sharing session for digital twin learning points and project risks.', 'MDP Interns', now()->addDays(3), 'Open', 'fa-solid fa-network-wired'],
            ['Computer Vision Use Case', 'Introduce AI and visual inspection opportunities.', 'Discussion about camera setup, dataset planning, and model evaluation.', 'All Interns', now()->addDays(9), 'Open', 'fa-solid fa-brain'],
            ['CFD Simulation Clinic', 'Improve engineering modeling and simulation practice.', 'Peer learning session for CFD assumptions, meshing, and validation.', 'Engineering Modeling Team', now()->addDays(15), 'Open', 'fa-solid fa-gears'],
            ['Reverse Engineering Showcase', 'Share reverse engineering method and documentation output.', 'Case study session from reverse engineering project execution.', 'Manufacturing Interns', now()->addDays(22), 'Reschedule', 'fa-solid fa-screwdriver-wrench'],
            ['Web Dashboard Mini Demo', 'Show progress of intern dashboard and project tracking features.', 'Short demo and feedback session for web dashboard improvement.', 'Mentors and Interns', now()->addMonth()->addDays(2), 'Open', 'fa-solid fa-laptop-code'],
        ];

        foreach ($rows as $index => [$theme, $objective, $description, $audience, $date, $status, $icon]) {
            TrCalendarSharing::create([
                'intCalendarSharing_ID' => $index + 1,
                'intCalendarSharingCreatorUser_ID' => $creatorIds[$index % max(1, $creatorIds->count())] ?? null,
                'txtCalendarSharingTheme' => $theme,
                'txtCalendarSharingObjective' => $objective,
                'txtCalendarSharingDescription' => $description,
                'txtCalendarSharingTargetAudience' => $audience,
                'dtmCalendarSharingDate' => $date->copy()->startOfDay(),
                'txtCalendarSharingStatus' => $status,
                'txtCalendarSharingIcon' => $icon,
                'bitActive' => true,
                'txtInsertedBy' => 'seeder',
                'dtmInserted' => now(),
            ]);
        }
    }

    private function seedProjectStages(int $projectId, string $projectType, Carbon $projectStartDate, Carbon $projectEndDate): void
    {
        $templates = match ($projectType) {
            'Sharing' => [
                [
                    ['Topic Preparation', 30],
                    ['Sharing Session', 50],
                    ['Documentation', 20],
                ],
                [
                    ['Topic Selection', 15],
                    ['Audience Mapping', 15],
                    ['Material Draft', 25],
                    ['Sharing Session', 30],
                    ['Feedback Recap', 15],
                ],
                [
                    ['Theme Research', 10],
                    ['Objective Alignment', 10],
                    ['Speaker Preparation', 10],
                    ['Material Development', 20],
                    ['Dry Run', 10],
                    ['Session Delivery', 25],
                    ['Q&A Recap', 10],
                    ['Documentation', 5],
                ],
            ],
            'Satellite' => [
                [
                    ['Initiation', 20],
                    ['Execution', 65],
                    ['Handover', 15],
                ],
                [
                    ['Scope Definition', 10],
                    ['Design Setup', 15],
                    ['Build', 35],
                    ['Testing', 20],
                    ['Review', 10],
                    ['Handover', 10],
                ],
                [
                    ['Observation', 8],
                    ['Scope Definition', 10],
                    ['Setup', 12],
                    ['Execution', 25],
                    ['Data Capture', 10],
                    ['Issue Fixing', 12],
                    ['Validation', 10],
                    ['Documentation', 8],
                    ['Handover', 5],
                ],
            ],
            'Collaboration' => [
                [
                    ['Alignment', 15],
                    ['Joint Development', 50],
                    ['Validation', 25],
                    ['Closing', 10],
                ],
                [
                    ['Kickoff', 10],
                    ['Requirement Alignment', 10],
                    ['Task Splitting', 10],
                    ['Development', 30],
                    ['Integration', 15],
                    ['Validation', 15],
                    ['Handover', 10],
                ],
                [
                    ['Stakeholder Mapping', 5],
                    ['Kickoff', 8],
                    ['Requirement Sync', 10],
                    ['Data Preparation', 10],
                    ['Development Sprint', 22],
                    ['Peer Review', 10],
                    ['Integration', 10],
                    ['Validation', 10],
                    ['Final Adjustment', 8],
                    ['Closing', 7],
                ],
            ],
            default => [
                [
                    ['Initiation', 10],
                    ['Planning', 15],
                    ['Development', 40],
                    ['Testing', 20],
                    ['Review', 15],
                ],
                [
                    ['Problem Definition', 10],
                    ['Data Collection', 15],
                    ['Concept Design', 15],
                    ['Execution', 35],
                    ['Validation', 15],
                    ['Final Report', 10],
                ],
                [
                    ['Observation', 5],
                    ['Problem Framing', 8],
                    ['Literature Study', 7],
                    ['Concept Design', 10],
                    ['Prototype Setup', 15],
                    ['Execution', 25],
                    ['Testing', 10],
                    ['Revision', 8],
                    ['Final Presentation', 7],
                    ['Handover', 5],
                ],
            ],
        };
        $rows = $templates[($projectId - 1) % count($templates)];

        $stageCount = count($rows);
        $projectDays = max(1, $projectStartDate->diffInDays($projectEndDate) + 1);
        $actualTargets = [0, 12, 28, 43, 58, 76, 100];
        $remainingActual = $actualTargets[$projectId % count($actualTargets)];

        foreach ($rows as $index => [$step, $plan]) {
            $stageStartOffset = (int) floor($projectDays * $index / $stageCount);
            $stageEndOffset = $index === $stageCount - 1
                ? $projectDays - 1
                : (int) floor($projectDays * ($index + 1) / $stageCount) - 1;
            $stageStartDate = $projectStartDate->copy()->addDays(max(0, $stageStartOffset));
            $stageEndDate = $projectStartDate->copy()->addDays(max($stageStartOffset, $stageEndOffset));
            $actual = min($plan, max(0, $remainingActual));
            $remainingActual -= $actual;

            TrProjectStage::create([
                'intProject_ID' => $projectId,
                'intProjectStageNumber' => $index + 1,
                'txtProjectStageStep' => $step,
                'dtmProjectStageStartDate' => $stageStartDate,
                'dtmProjectStageEndDate' => $stageEndDate,
                'floatProjectStagePlan' => $plan,
                'floatProjectStageActual' => $actual,
                'bitActive' => true,
                'txtInsertedBy' => 'seeder',
                'dtmInserted' => now(),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function internRows(): array
    {
        return [
            [
                'name' => 'Lisna Juliani',
                'university' => 'UPI Purwakarta',
                'department' => 'Manufacturing Development and Planning',
                'gender' => 'Perempuan',
                'mentor' => 'Agung Hartanto',
                'join_date' => 'Thursday, 21 August 2025',
                'end_date' => 'Sunday, 17 May 2026',
                'projects' => [
                    'Main' => '',
                    'Satellite' => 'Project Administration',
                    'Collaboration' => '',
                    'Sharing' => 'Management Project',
                ],
            ],
            [
                'name' => 'Frendy',
                'university' => 'Universitas Atma Jaya',
                'department' => 'Engineering',
                'gender' => 'Laki-laki',
                'mentor' => 'Insani Gustrianjar Muharom',
                'join_date' => 'Monday, 25 August 2025',
                'end_date' => 'Wednesday, 19 August 2026',
                'projects' => [
                    'Main' => 'Preventive Maintenance',
                    'Satellite' => '',
                    'Collaboration' => '',
                    'Sharing' => '',
                ],
            ],
            [
                'name' => 'Khansa Galih Septahadi',
                'university' => 'Universitas Singaperbangsa Karawang',
                'department' => 'Manufacturing Development and Planning',
                'gender' => 'Laki-laki',
                'mentor' => 'Agung Hartanto',
                'join_date' => 'Friday, 19 September 2025',
                'end_date' => 'Friday, 19 June 2026',
                'projects' => [
                    'Main' => '3D Modeling',
                    'Satellite' => '',
                    'Collaboration' => '',
                    'Sharing' => '3D Modeling',
                ],
            ],
            [
                'name' => 'Husain Afrizal Aminullah',
                'university' => 'Universitas Negeri Yogya',
                'department' => 'Manufacturing Development and Planning',
                'gender' => 'Laki-laki',
                'mentor' => 'Wahyu Agus',
                'join_date' => 'Thursday, 13 November 2025',
                'end_date' => 'Friday, 10 July 2026',
                'projects' => [
                    'Main' => 'RE',
                    'Satellite' => 'Project Documentation',
                    'Collaboration' => '',
                    'Sharing' => '',
                ],
            ],
            [
                'name' => 'Christopher Rey Wijaya',
                'university' => 'Binus Aso',
                'department' => 'Manufacturing Development and Planning',
                'gender' => 'Laki-laki',
                'mentor' => 'Wahyu Agus',
                'join_date' => 'Monday, 24 November 2025',
                'end_date' => 'Tuesday, 21 July 2026',
                'projects' => [
                    'Main' => 'CFD',
                    'Satellite' => 'Project Documentation',
                    'Collaboration' => 'CFD Platform',
                    'Sharing' => 'CFD Simulation',
                ],
            ],
            [
                'name' => 'Muhammad Kautsar',
                'university' => 'UPI Purwakarta',
                'department' => 'Engineering',
                'gender' => 'Laki-laki',
                'mentor' => 'Insani Gustrianjar Muharom',
                'join_date' => 'Monday, 12 January 2026',
                'end_date' => 'Friday, 10 July 2026',
                'projects' => [
                    'Main' => 'WWTPS',
                    'Satellite' => 'Digital Twin',
                    'Collaboration' => '',
                    'Sharing' => '',
                ],
            ],
            [
                'name' => 'Humaira Zeanova',
                'university' => 'Universitas Padjadjaran',
                'department' => 'Manufacturing Development and Planning',
                'gender' => 'Perempuan',
                'mentor' => 'Jepri Haerudin',
                'join_date' => 'Wednesday, 04 February 2026',
                'end_date' => 'Tuesday, 04 August 2026',
                'projects' => [
                    'Main' => 'Spray Dryer (Data)',
                    'Satellite' => 'WorkFlow Automation n8n',
                    'Collaboration' => 'Optimus',
                    'Sharing' => '',
                ],
            ],
            [
                'name' => 'Shearani Gino',
                'university' => 'Institut Teknologi Sepuluh November',
                'department' => 'Manufacturing Development and Planning',
                'gender' => 'Perempuan',
                'mentor' => 'Jepri Haerudin',
                'join_date' => 'Wednesday, 18 February 2026',
                'end_date' => 'Friday, 17 July 2026',
                'projects' => [
                    'Main' => 'Spray Dryer (Data)',
                    'Satellite' => 'WorkFlow Automation n8n',
                    'Collaboration' => '',
                    'Sharing' => '',
                ],
            ],
            [
                'name' => 'Rama Nusa Bakti',
                'university' => 'Universitas Singaperbangsa Karawang',
                'department' => 'Manufacturing Development and Planning',
                'gender' => 'Laki-laki',
                'mentor' => 'Irpan Hidayat Pamil',
                'join_date' => 'Monday, 04 May 2026',
                'end_date' => 'Wednesday, 04 November 2026',
                'projects' => [
                    'Main' => 'Digital Twin, Todays/PMP',
                    'Satellite' => '',
                    'Collaboration' => 'CFD Platform',
                    'Sharing' => '',
                ],
            ],
            [
                'name' => 'Delia Nur Ilmi Salam',
                'university' => 'Universitas Singaperbangsa Karawang',
                'department' => 'Business Development Analysis - Finance',
                'gender' => 'Perempuan',
                'mentor' => 'Nasthasya Priyanka',
                'join_date' => 'Tuesday, 02 June 2026',
                'end_date' => 'Wednesday, 02 September 2026',
                'projects' => [
                    'Main' => 'Treasury Lite',
                    'Satellite' => '',
                    'Collaboration' => '',
                    'Sharing' => '',
                ],
            ],
        ];
    }

    private function emailFromName(string $name): string
    {
        $localPart = (string) preg_replace('/[^a-z0-9]+/', '.', strtolower($name));
        $localPart = trim($localPart, '.');

        return $localPart . '@kalbe.co.id';
    }

    /**
     * @param array<string, int> $skillSetIds
     */
    private function skillSetIdForProject(string $projectName, string $projectType, array $skillSetIds): int
    {
        $name = strtolower($projectName);

        if (trim(strtoupper($projectName)) === 'RE') {
            return $skillSetIds['Reverse Engineering'];
        }

        if (str_contains($name, '3d') || str_contains($name, 'cfd') || str_contains($name, 'digital twin') || str_contains($name, 'simulation')) {
            return $skillSetIds['Engineering Modeling & Simulation'];
        }

        if (str_contains($name, 'workflow') || str_contains($name, 'n8n')) {
            return $skillSetIds['Robotic Process Automation (RPA)'];
        }

        if (str_contains($name, 'spray') || str_contains($name, 'wwtps') || str_contains($name, 'preventive') || str_contains($name, 'data')) {
            return $skillSetIds['Embedded Systems & IoT Data Acquisition'];
        }

        if (str_contains($name, 'optimus')) {
            return $skillSetIds['AI & Computer Vision'];
        }

        if ($projectType === 'Sharing') {
            return $skillSetIds['Web Development'];
        }

        return $skillSetIds['Web Development'];
    }

    private function dateFromSheet(string $value): Carbon
    {
        $datePart = trim(explode(',', $value, 2)[1] ?? $value);

        return Carbon::createFromFormat('d F Y', $datePart)->startOfDay();
    }
}
