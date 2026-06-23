<?php

namespace App\Support;

use App\Models\MIntern;
use App\Models\MProjectWeight;
use Illuminate\Support\Collection;

class ProjectScoreboard
{
    public static function rows(): Collection
    {
        $weights = self::weights();

        return MIntern::with(['user', 'projects.project'])
            ->where('bitActive', true)
            ->orderBy('txtInternName')
            ->get()
            ->map(function (MIntern $intern) use ($weights) {
                $assignments = $intern->projects
                    ->filter(fn ($assignment) => $assignment->bitActive && $assignment->project?->bitActive);

                $counts = [
                    'main' => $assignments->where('project.txtProjectType', 'Main')->count(),
                    'collaboration' => $assignments->where('project.txtProjectType', 'Collaboration')->count(),
                    'satellite' => $assignments->where('project.txtProjectType', 'Satellite')->count(),
                    'sharing' => $assignments->where('project.txtProjectType', 'Sharing')->count(),
                ];

                $latestMainProject = $assignments
                    ->filter(fn ($assignment) => $assignment->project?->txtProjectType === 'Main' && (float) $assignment->floatProgress < 100)
                    ->sortByDesc('dtmInserted')
                    ->first()
                    ?? $assignments
                        ->filter(fn ($assignment) => $assignment->project?->txtProjectType === 'Main')
                        ->sortByDesc('dtmInserted')
                        ->first();

                $latestAssignment = $assignments->sortByDesc('dtmInserted')->first();
                $score = collect($counts)->reduce(
                    fn (int $carry, int $count, string $type) => $carry + ($count * $weights[$type]),
                    0,
                );

                return [
                    'intern' => $intern,
                    'main_project' => $latestMainProject?->project?->txtProjectName ?? '-',
                    'main' => $counts['main'],
                    'collaboration' => $counts['collaboration'],
                    'satellite' => $counts['satellite'],
                    'sharing' => $counts['sharing'],
                    'score' => $score,
                    'period' => $latestAssignment?->dtmInserted?->format('M Y') ?? '-',
                ];
            })
            ->sortBy([
                ['score', 'desc'],
                ['intern.txtInternName', 'asc'],
            ])
            ->values();
    }

    public static function weights(): array
    {
        $weight = MProjectWeight::where('bitActive', true)->orderBy('intProjectWeight_ID')->first();

        return [
            'main' => (int) ($weight?->intProjectWeightMain ?? 10),
            'collaboration' => (int) ($weight?->intProjectWeightCollaboration ?? 6),
            'satellite' => (int) ($weight?->intProjectWeightSatellite ?? 2),
            'sharing' => (int) ($weight?->intProjectWeightSharing ?? 4),
        ];
    }
}
