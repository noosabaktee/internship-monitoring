<?php

namespace Database\Seeders;

use App\Models\MIntern;
use App\Models\MMentor;
use App\Models\MProject;
use App\Models\MUser;
use App\Models\TrAchievement;
use App\Models\TrEvaluation;
use App\Models\TrInternProject;
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
                    'txtMajor' => $row['department'],
                    'txtBio' => null,
                    'dtmEndDate' => $this->dateFromSheet($row['end_date']),
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
                            'txtDescription' => $projectType . ' project for ' . $row['department'],
                            'bitActive' => true,
                            'txtInsertedBy' => 'seeder',
                            'dtmInserted' => $this->dateFromSheet($row['join_date']),
                        ]);

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
        });
    }

    private function clearCustomTables(): void
    {
        TrAchievement::query()->delete();
        TrEvaluation::query()->delete();
        TrInternProject::query()->delete();
        MProject::query()->delete();
        MIntern::query()->delete();
        MMentor::query()->delete();
        MUser::query()->delete();
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
                    'Main' => 'TREASURY LITE?',
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

    private function dateFromSheet(string $value): Carbon
    {
        $datePart = trim(explode(',', $value, 2)[1] ?? $value);

        return Carbon::createFromFormat('d F Y', $datePart)->startOfDay();
    }
}
