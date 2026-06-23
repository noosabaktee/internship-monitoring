<?php

namespace App\Support;

use App\Models\MProject;
use Illuminate\Support\Collection;

class ExposureCurveBuilder
{
    private const PROJECT_TYPES = [
        ['key' => 'main', 'label' => 'Main', 'icon' => 'fa-solid fa-star', 'color' => '#006838'],
        ['key' => 'collaboration', 'label' => 'Collaboration', 'icon' => 'fa-solid fa-people-arrows', 'color' => '#2563EB'],
        ['key' => 'satellite', 'label' => 'Satellite', 'icon' => 'fa-solid fa-satellite-dish', 'color' => '#F59E0B'],
        ['key' => 'sharing', 'label' => 'Sharing', 'icon' => 'fa-solid fa-share-nodes', 'color' => '#7C3AED'],
    ];

    public static function payload(Collection $projects): array
    {
        $projectTypes = self::projectTypes();

        return [
            'generatedAt' => now()->format('d M Y H:i'),
            'projectTypes' => $projectTypes,
            'projects' => $projects->map(fn (MProject $project) => self::projectPayload($project, $projectTypes))->values()->all(),
        ];
    }

    public static function projectTypes(): array
    {
        $weights = ProjectScoreboard::weights();
        $totalWeight = array_sum($weights);

        return collect(self::PROJECT_TYPES)
            ->map(function (array $type) use ($weights, $totalWeight) {
                $weight = (int) ($weights[$type['key']] ?? 0);

                return [
                    ...$type,
                    'weight' => $weight,
                    'share' => $totalWeight > 0 ? round(($weight / $totalWeight) * 100, 4) : 0,
                ];
            })
            ->values()
            ->all();
    }

    public static function projectPayload(MProject $project, array $projectTypes): array
    {
        $typeKey = self::projectTypeKey($project->txtProjectType);
        $type = collect($projectTypes)->firstWhere('key', $typeKey);
        $assignments = $project->assignments
            ->filter(fn ($assignment) => $assignment->bitActive && $assignment->intern?->bitActive)
            ->values();

        return [
            'id' => (string) $project->intProject_ID,
            'name' => $project->txtProjectName,
            'type' => $project->txtProjectType,
            'typeKey' => $typeKey,
            'typeShare' => (float) ($type['share'] ?? 0),
            'start' => $project->dtmProjectStartDate?->format('Y-m-d H:i'),
            'end' => $project->dtmProjectEndDate?->format('Y-m-d H:i'),
            'internIds' => $assignments->pluck('intIntern_ID')->map(fn ($id) => (string) $id)->unique()->values()->all(),
            'internNames' => $assignments
                ->map(fn ($assignment) => $assignment->intern?->txtInternName)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'stages' => $project->stages->map(fn ($stage) => [
                'number' => (int) $stage->intProjectStageNumber,
                'step' => $stage->txtProjectStageStep,
                'start' => $stage->dtmProjectStageStartDate?->format('Y-m-d H:i'),
                'end' => $stage->dtmProjectStageEndDate?->format('Y-m-d H:i'),
                'plan' => (float) ($stage->floatProjectStagePlan ?? 0),
                'actual' => (float) ($stage->floatProjectStageActual ?? 0),
            ])->values()->all(),
        ];
    }

    public static function projectTypeKey(?string $projectType): string
    {
        return match ($projectType) {
            'Main' => 'main',
            'Collaboration' => 'collaboration',
            'Satellite' => 'satellite',
            'Sharing' => 'sharing',
            default => strtolower((string) $projectType),
        };
    }
}
