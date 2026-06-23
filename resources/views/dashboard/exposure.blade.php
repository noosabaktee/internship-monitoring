@extends('layouts.app', [
    'title' => 'Exposure - Kalbe Internship Dashboard',
    'pageTitle' => 'EXPOSURE',
    'pageSubtitle' => 'S Curve Planned vs Actual dari Project Stage.',
])

@php
    $formatShare = fn ($value) => rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
@endphp

@section('content')
    <div class="exposure-page" data-exposure-page>
        <div class="page-crud-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h2>Exposure S Curve</h2>
                <p>Planned vs Actual cumulative progress.</p>
            </div>
            <div class="weight-summary">
                @foreach ($projectTypes as $type)
                    <span>{{ $type['label'] }}: <strong>{{ $type['weight'] }}</strong> / {{ $formatShare($type['share']) }}%</span>
                @endforeach
            </div>
        </div>

        <div class="kpi-row exposure-kpi-row">
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-flag-checkered"></i></div>
                <div class="kpi-data"><h4>Planned Cumulative</h4><h2 id="exposureKpiPlan">--</h2><p id="exposureKpiPlanNote">Target</p></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div class="kpi-data"><h4>Actual Cumulative</h4><h2 id="exposureKpiActual">--</h2><p id="exposureKpiActualNote">Progress</p></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-arrow-trend-down"></i></div>
                <div class="kpi-data"><h4>Gap</h4><h2 id="exposureKpiGap">--</h2><p id="exposureKpiGapNote">Plan - Actual</p></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-diagram-project"></i></div>
                <div class="kpi-data"><h4>Sources</h4><h2 id="exposureKpiSources">{{ $summary['projects'] }}</h2><p id="exposureKpiSourcesNote">{{ $summary['stages'] }} active stages</p></div>
            </div>
        </div>

        <section class="exposure-toolbar">
            <div class="exposure-mode-tabs" role="tablist" aria-label="Exposure mode">
                <button class="exposure-mode-tab active" type="button" data-exposure-mode="main" aria-pressed="true"><i class="fa-solid fa-chart-area"></i><span>S Curve Utama</span></button>
                <button class="exposure-mode-tab" type="button" data-exposure-mode="project" aria-pressed="false"><i class="fa-solid fa-briefcase"></i><span>Project</span></button>
                <button class="exposure-mode-tab" type="button" data-exposure-mode="intern" aria-pressed="false"><i class="fa-solid fa-user-graduate"></i><span>Intern</span></button>
                <button class="exposure-mode-tab" type="button" data-exposure-mode="type" aria-pressed="false"><i class="fa-solid fa-layer-group"></i><span>Tipe Project</span></button>
            </div>

            <div class="exposure-filter-grid">
                <div class="exposure-filter-field" data-exposure-filter-field="project">
                    <label class="form-label" for="exposureProjectFilter">Project</label>
                    <select class="form-control" id="exposureProjectFilter">
                        <option value="">Select project</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->intProject_ID }}">{{ $project->txtProjectName }} ({{ $project->txtProjectType }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="exposure-filter-field" data-exposure-filter-field="intern">
                    <label class="form-label" for="exposureInternFilter">Intern</label>
                    <select class="form-control" id="exposureInternFilter">
                        <option value="">Select intern</option>
                        @foreach ($interns as $intern)
                            <option value="{{ $intern->intIntern_ID }}">{{ $intern->txtInternName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="exposure-filter-field" data-exposure-filter-field="type">
                    <label class="form-label" for="exposureTypeFilter">Tipe Project</label>
                    <select class="form-control" id="exposureTypeFilter">
                        <option value="">Select type</option>
                        @foreach ($projectTypes as $type)
                            <option value="{{ $type['key'] }}">{{ $type['label'] }} <!--  - {{ $formatShare($type['share']) }}%</option> -->
                        @endforeach
                    </select>
                </div>
                <div class="exposure-line-controls">
                    <label class="exposure-check">
                        <input id="exposureToggleActual" type="checkbox" checked>
                        <span class="exposure-check-dot actual"></span>
                        Actual
                    </label>
                    <label class="exposure-check">
                        <input id="exposureTogglePlan" type="checkbox" checked>
                        <span class="exposure-check-dot planned"></span>
                        Planned
                    </label>
                    <button class="btn btn-outline-primary btn-sm" id="exposureResetFilter" type="button"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                </div>
            </div>

            <!-- <div class="exposure-weight-strip">
                @foreach ($projectTypes as $type)
                    <button class="exposure-weight-pill" type="button" data-exposure-type="{{ $type['key'] }}">
                        <i class="{{ $type['icon'] }}" style="color: {{ $type['color'] }}"></i>
                        <span>{{ $type['label'] }}</span>
                        <strong>{{ $type['weight'] }}</strong>
                        <small>{{ $formatShare($type['share']) }}%</small>
                    </button>
                @endforeach
            </div> -->
        </section>

        <div class="exposure-chart-grid">
            <section class="exposure-chart-panel">
                <div class="exposure-panel-head">
                    <div>
                        <h3 id="exposureChartTitle">S Curve Utama</h3>
                        <p id="exposureChartSubtitle">Weighted planned vs actual cumulative progress.</p>
                    </div>
                    <div class="exposure-legend">
                        <span><i class="actual"></i> Actual</span>
                        <span><i class="planned"></i> Planned</span>
                    </div>
                </div>

                <div class="exposure-chart-shell">
                    <canvas id="exposureCurveChart"></canvas>
                    <div class="exposure-empty-state" id="exposureEmptyState" hidden>
                        <i class="fa-solid fa-chart-simple"></i>
                        <strong>No stage data</strong>
                        <span>--</span>
                    </div>
                </div>

                <div class="exposure-scrubber">
                    <label id="exposureTimelineLabel" for="exposureActivityScrubber">Date Time</label>
                    <input id="exposureActivityScrubber" type="range" min="1" max="1" value="1">
                    <output id="exposureActivityOutput">1</output>
                </div>

                <div class="exposure-point-grid">
                    <div><span id="exposurePointDateLabel">Date Time</span><strong id="exposurePointActivity">--</strong></div>
                    <div><span>Planned</span><strong id="exposurePointPlan">--</strong></div>
                    <div><span>Actual</span><strong id="exposurePointActual">--</strong></div>
                    <div><span>Gap</span><strong id="exposurePointGap">--</strong></div>
                </div>
            </section>

            <aside class="exposure-side-panel">
                <div class="exposure-panel-head">
                    <div>
                        <h3>Type Contribution</h3>
                        <p id="exposureContributionSubtitle">Current weighted distribution.</p>
                    </div>
                </div>
                <div class="exposure-contribution-list" id="exposureContributionList"></div>
            </aside>
        </div>

        <div class="exposure-detail-grid">
            <section class="exposure-detail-panel">
                <div class="exposure-panel-head">
                    <div>
                        <h3>Cumulative Table</h3>
                        <p>Planned and actual values by date time.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table data-table align-middle mb-0 exposure-table">
                        <thead>
                            <tr><th>Date Time</th><th>Planned</th><th>Actual</th><th>Gap</th></tr>
                        </thead>
                        <tbody id="exposureCurveTableBody"></tbody>
                    </table>
                </div>
            </section>

            <section class="exposure-detail-panel">
                <div class="exposure-panel-head">
                    <div>
                        <h3>Source Projects</h3>
                        <p id="exposureSourceSubtitle">Projects included in the current curve.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table data-table align-middle mb-0 exposure-table">
                        <thead>
                            <tr><th>Project</th><th>Type</th><th>Stages</th><th>Actual</th></tr>
                        </thead>
                        <tbody id="exposureSourceTableBody"></tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <script type="application/json" id="exposureCurvePayload">@json($exposurePayload)</script>
@endsection
