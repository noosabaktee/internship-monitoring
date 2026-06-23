document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-exposure-page]');
    const payloadElement = document.getElementById('exposureCurvePayload');

    if (!page || !payloadElement || !window.Chart) {
        return;
    }

    const payload = JSON.parse(payloadElement.textContent || '{}');
    const projects = Array.isArray(payload.projects) ? payload.projects : [];
    const interns = Array.isArray(payload.interns) ? payload.interns : [];
    const projectTypes = Array.isArray(payload.projectTypes) ? payload.projectTypes : [];
    const canvas = document.getElementById('exposureCurveChart');
    const emptyState = document.getElementById('exposureEmptyState');
    const modeButtons = page.querySelectorAll('[data-exposure-mode]');
    const typeButtons = page.querySelectorAll('[data-exposure-type]');
    const projectFilter = document.getElementById('exposureProjectFilter');
    const internFilter = document.getElementById('exposureInternFilter');
    const typeFilter = document.getElementById('exposureTypeFilter');
    const resetButton = document.getElementById('exposureResetFilter');
    const actualToggle = document.getElementById('exposureToggleActual');
    const planToggle = document.getElementById('exposureTogglePlan');
    const scrubber = document.getElementById('exposureActivityScrubber');
    const scrubberOutput = document.getElementById('exposureActivityOutput');
    const scrubberLabel = document.getElementById('exposureTimelineLabel');
    const contributionList = document.getElementById('exposureContributionList');
    const curveTableBody = document.getElementById('exposureCurveTableBody');
    const sourceTableBody = document.getElementById('exposureSourceTableBody');
    const chartTitle = document.getElementById('exposureChartTitle');
    const chartSubtitle = document.getElementById('exposureChartSubtitle');
    const contributionSubtitle = document.getElementById('exposureContributionSubtitle');
    const sourceSubtitle = document.getElementById('exposureSourceSubtitle');
    const pointDate = document.getElementById('exposurePointActivity');
    const pointDateLabel = document.getElementById('exposurePointDateLabel');
    const pointPlan = document.getElementById('exposurePointPlan');
    const pointActual = document.getElementById('exposurePointActual');
    const pointGap = document.getElementById('exposurePointGap');
    const kpiPlan = document.getElementById('exposureKpiPlan');
    const kpiActual = document.getElementById('exposureKpiActual');
    const kpiGap = document.getElementById('exposureKpiGap');
    const kpiSources = document.getElementById('exposureKpiSources');
    const kpiPlanNote = document.getElementById('exposureKpiPlanNote');
    const kpiActualNote = document.getElementById('exposureKpiActualNote');
    const kpiGapNote = document.getElementById('exposureKpiGapNote');
    const kpiSourcesNote = document.getElementById('exposureKpiSourcesNote');
    const filterFields = page.querySelectorAll('[data-exposure-filter-field]');
    const state = {
        mode: 'main',
        projectId: projects[0]?.id || '',
        internId: interns[0]?.id || '',
        typeKey: projectTypes[0]?.key || '',
        selectedIndex: 0,
        showActual: true,
        showPlan: true,
    };
    let chart = null;
    let currentXAxisLabel = 'Date Time';

    const dayMs = 86400000;
    const dateFormatter = new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
    const monthFormatter = new Intl.DateTimeFormat('id-ID', {
        month: 'short',
        year: 'numeric',
    });
    const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

    const toNumber = (value) => {
        const number = Number(value);

        return Number.isFinite(number) ? number : 0;
    };

    const clamp = (value, min = 0, max = 100) => Math.max(min, Math.min(max, toNumber(value)));

    const round = (value, decimals = 2) => {
        const factor = 10 ** decimals;

        return Math.round((toNumber(value) + Number.EPSILON) * factor) / factor;
    };

    const formatNumber = (value, decimals = 1) => {
        const rounded = round(value, decimals);
        const hasDecimal = Math.abs(rounded % 1) > 0.0001;

        return rounded.toLocaleString('en-US', {
            minimumFractionDigits: hasDecimal ? decimals : 0,
            maximumFractionDigits: decimals,
        });
    };

    const formatPercent = (value, decimals = 1) => `${formatNumber(value, decimals)}%`;
    const setText = (element, value) => {
        if (element) {
            element.textContent = value;
        }
    };

    const dateValue = (value) => {
        if (!value) {
            return null;
        }

        const parsed = new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(parsed.getTime())) {
            return null;
        }

        return parsed.getTime();
    };

    const dateLabel = (value) => {
        if (!Number.isFinite(value)) {
            return '--';
        }

        const date = new Date(value);
        const hasTime = date.getHours() !== 0 || date.getMinutes() !== 0;

        return hasTime ? dateTimeFormatter.format(date) : dateFormatter.format(date);
    };

    const monthKey = (value) => {
        const date = new Date(value);

        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    };

    const monthLabel = (value) => monthFormatter.format(new Date(value));

    const compactMonthlyPoints = (points) => {
        const monthly = new Map();

        points.forEach((point) => {
            monthly.set(monthKey(point.date), {
                ...point,
                label: monthLabel(point.date),
                period: monthKey(point.date),
            });
        });

        const compacted = [...monthly.values()].sort((left, right) => left.date - right.date);

        if (compacted.length > 0 && points.length > 0) {
            const sourceFinal = points[points.length - 1];
            const finalIndex = compacted.length - 1;

            compacted[finalIndex] = {
                ...compacted[finalIndex],
                planned: sourceFinal.planned,
                actual: sourceFinal.actual,
                rawPlanned: sourceFinal.rawPlanned,
                rawActual: sourceFinal.rawActual,
            };
        }

        return compacted;
    };

    const typeByKey = (key) => projectTypes.find((type) => type.key === key);
    const projectById = (id) => projects.find((project) => project.id === id);
    const internById = (id) => interns.find((intern) => intern.id === id);
    const sumStages = (sourceProjects) => sourceProjects.reduce((total, project) => total + (project.stages?.length || 0), 0);
    const projectTypeShare = (project) => toNumber(typeByKey(project.typeKey)?.share);

    const sortStagesByDate = (project) => [...(project.stages || [])].sort((left, right) => {
        const leftDate = dateValue(left.end || left.start || project.end || project.start) || 0;
        const rightDate = dateValue(right.end || right.start || project.end || project.start) || 0;

        if (leftDate === rightDate) {
            return toNumber(left.number) - toNumber(right.number);
        }

        return leftDate - rightDate;
    });

    const projectCurve = (project) => {
        if (project._dateCurve) {
            return project._dateCurve;
        }

        const stages = sortStagesByDate(project);
        const planTotal = stages.reduce((sum, stage) => sum + toNumber(stage.plan), 0);
        const divisor = planTotal > 0 ? planTotal : 100;
        const fallbackPlan = stages.length > 0 ? 100 / stages.length : 0;
        const firstStageDate = stages
            .map((stage) => dateValue(stage.start || stage.end || project.start || project.end))
            .find((value) => Number.isFinite(value));
        let lastDate = dateValue(project.start) || firstStageDate || dateValue(project.end) || Date.now();
        let cumulativePlan = 0;
        let cumulativeActual = 0;
        const points = [{
            date: lastDate,
            label: dateLabel(lastDate),
            planned: 0,
            actual: 0,
            rawPlanned: 0,
            rawActual: 0,
        }];

        stages.forEach((stage, index) => {
            const planValue = planTotal > 0 ? toNumber(stage.plan) : fallbackPlan;
            let nextDate = dateValue(stage.end || stage.start || project.end || project.start);

            if (!Number.isFinite(nextDate)) {
                nextDate = lastDate + dayMs;
            }

            if (nextDate < lastDate) {
                nextDate = lastDate + dayMs;
            }

            cumulativePlan += planValue;
            cumulativeActual += toNumber(stage.actual);
            lastDate = nextDate;
            points.push({
                date: nextDate,
                label: dateLabel(nextDate),
                planned: round(clamp((cumulativePlan / divisor) * 100)),
                actual: round(clamp((cumulativeActual / divisor) * 100)),
                rawPlanned: round(clamp((cumulativePlan / divisor) * 100)),
                rawActual: round(clamp((cumulativeActual / divisor) * 100)),
                step: stage.step || `Stage ${index + 1}`,
            });
        });

        if (points.length > 1) {
            points[points.length - 1].planned = 100;
            points[points.length - 1].rawPlanned = 100;
        }

        project._dateCurve = mergeSameDatePoints(points);

        return project._dateCurve;
    };

    const mergeSameDatePoints = (points) => {
        const merged = new Map();

        points.forEach((point) => {
            merged.set(point.date, {
                ...point,
                label: dateLabel(point.date),
            });
        });

        return [...merged.values()].sort((left, right) => left.date - right.date);
    };

    const projectPointAt = (project, date) => {
        const points = projectCurve(project);

        if (points.length === 0) {
            return { planned: 0, actual: 0, rawPlanned: 0, rawActual: 0 };
        }

        let selected = points[0].date <= date ? points[0] : null;

        for (const point of points) {
            if (point.date <= date) {
                selected = point;
            } else {
                break;
            }
        }

        return selected || { planned: 0, actual: 0, rawPlanned: 0, rawActual: 0 };
    };

    const timelineDates = (sourceProjects) => {
        const dates = sourceProjects
            .flatMap((project) => projectCurve(project).map((point) => point.date))
            .filter((value) => Number.isFinite(value));

        return [...new Set(dates)].sort((left, right) => left - right);
    };

    const finalPoint = (points) => points.at(-1) || {
        date: null,
        label: '--',
        planned: 0,
        actual: 0,
        rawPlanned: 0,
        rawActual: 0,
    };

    const aggregateTypeProjects = (typeProjects, dates) => {
        const projectsWithStages = typeProjects.filter((project) => projectCurve(project).length > 1);

        if (projectsWithStages.length === 0 || dates.length === 0) {
            return {
                points: [],
                projects: projectsWithStages,
            };
        }

        const points = dates.map((date) => {
            const totals = projectsWithStages.reduce((carry, project) => {
                const point = projectPointAt(project, date);

                carry.planned += point.rawPlanned;
                carry.actual += point.rawActual;

                return carry;
            }, { planned: 0, actual: 0 });
            const rawPlanned = totals.planned / projectsWithStages.length;
            const rawActual = totals.actual / projectsWithStages.length;

            return {
                date,
                label: dateLabel(date),
                planned: round(rawPlanned),
                actual: round(rawActual),
                rawPlanned: round(rawPlanned),
                rawActual: round(rawActual),
                projectCount: projectsWithStages.length,
            };
        });

        return {
            points,
            projects: projectsWithStages,
        };
    };

    const weightedCurve = (sourceProjects) => {
        const projectsWithStages = sourceProjects.filter((project) => projectCurve(project).length > 1);
        const dates = timelineDates(projectsWithStages);
        const activeShare = projectTypes.reduce((sum, type) => {
            const hasProject = projectsWithStages.some((project) => project.typeKey === type.key);

            return hasProject ? sum + toNumber(type.share) : sum;
        }, 0);
        const denominator = activeShare > 0 ? activeShare : 100;
        const typeSummaries = projectTypes.map((type) => {
            const typeProjects = projectsWithStages.filter((project) => project.typeKey === type.key);
            const localCurve = aggregateTypeProjects(typeProjects, dates);
            const localFinal = finalPoint(localCurve.points);
            const normalizedShare = denominator > 0 ? (toNumber(type.share) / denominator) * 100 : 0;

            return {
                ...type,
                projects: typeProjects,
                projectCount: typeProjects.length,
                stageCount: sumStages(typeProjects),
                normalizedShare: typeProjects.length > 0 ? normalizedShare : 0,
                localCurve,
                localPlan: localFinal.rawPlanned,
                localActual: localFinal.rawActual,
                plannedContribution: typeProjects.length > 0 ? round((localFinal.rawPlanned * toNumber(type.share)) / denominator) : 0,
                actualContribution: typeProjects.length > 0 ? round((localFinal.rawActual * toNumber(type.share)) / denominator) : 0,
            };
        });

        const points = dates.map((date, index) => {
            const totals = typeSummaries.reduce((carry, summary) => {
                if (summary.projectCount === 0) {
                    return carry;
                }

                const typePoint = summary.localCurve.points[index] || { rawPlanned: 0, rawActual: 0 };

                carry.planned += (typePoint.rawPlanned * toNumber(summary.share)) / denominator;
                carry.actual += (typePoint.rawActual * toNumber(summary.share)) / denominator;

                return carry;
            }, { planned: 0, actual: 0 });

            return {
                date,
                label: dateLabel(date),
                planned: round(clamp(totals.planned)),
                actual: round(clamp(totals.actual)),
                rawPlanned: round(clamp(totals.planned)),
                rawActual: round(clamp(totals.actual)),
            };
        });

        if (points.length > 1) {
            points[points.length - 1].planned = 100;
            points[points.length - 1].rawPlanned = 100;
        }

        return {
            points,
            typeSummaries,
            sourceProjects: projectsWithStages,
            activeShare: denominator,
            weighted: true,
        };
    };

    const currentCurve = () => {
        if (state.mode === 'project') {
            const project = projectById(state.projectId);
            const sourceProjects = project ? [project] : [];
            const weighted = weightedCurve(sourceProjects);
            const internNames = project?.internNames?.length ? project.internNames.join(', ') : '-';

            return {
                ...weighted,
                xAxisLabel: 'Date Time',
                title: project?.name || 'Project S Curve',
                subtitle: project
                    ? `${project.type} | ${internNames} | normalized to 100% from ${formatPercent(projectTypeShare(project))} type weight`
                    : 'No project selected.',
            };
        }

        if (state.mode === 'intern') {
            const intern = internById(state.internId);
            const sourceProjects = intern
                ? projects.filter((project) => (project.internIds || []).includes(intern.id))
                : [];
            const weighted = weightedCurve(sourceProjects);

            return {
                ...weighted,
                points: compactMonthlyPoints(weighted.points),
                xAxisLabel: 'Month',
                title: intern?.name || 'Intern S Curve',
                subtitle: intern ? `${intern.department} | weighted by project type, normalized to 100%` : 'No intern selected.',
            };
        }

        if (state.mode === 'type') {
            const type = typeByKey(state.typeKey);
            const sourceProjects = type
                ? projects.filter((project) => project.typeKey === type.key)
                : [];
            const weighted = weightedCurve(sourceProjects);

            return {
                ...weighted,
                points: compactMonthlyPoints(weighted.points),
                xAxisLabel: 'Month',
                title: type ? `${type.label} S Curve` : 'Project Type S Curve',
                subtitle: type ? `${formatPercent(type.share)} allocation normalized to 100%` : 'No project type selected.',
            };
        }

        const weighted = weightedCurve(projects);

        return {
            ...weighted,
            points: compactMonthlyPoints(weighted.points),
            xAxisLabel: 'Month',
            title: 'S Curve Utama',
            subtitle: 'Weighted by project type, then normalized so planned ends at 100%.',
        };
    };

    const syncControls = () => {
        modeButtons.forEach((button) => {
            const isActive = button.dataset.exposureMode === state.mode;

            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        filterFields.forEach((field) => {
            field.classList.toggle('is-active', field.dataset.exposureFilterField === state.mode);
        });

        if (projectFilter) {
            projectFilter.value = state.mode === 'project' ? state.projectId : '';
        }

        if (internFilter) {
            internFilter.value = state.mode === 'intern' ? state.internId : '';
        }

        if (typeFilter) {
            typeFilter.value = state.mode === 'type' ? state.typeKey : '';
        }
    };

    const chartColors = () => {
        const styles = getComputedStyle(document.body);

        return {
            planned: '#6D5BD0',
            actual: styles.getPropertyValue('--secondary').trim() || '#8CC63F',
            grid: styles.getPropertyValue('--border').trim() || '#DDE5DD',
            text: styles.getPropertyValue('--text-gray').trim() || '#555555',
        };
    };

    const renderChart = (curve) => {
        const points = curve.points || [];
        const colors = chartColors();
        currentXAxisLabel = curve.xAxisLabel || 'Date Time';

        if (emptyState) {
            emptyState.hidden = points.length > 0;
            const emptyCopy = emptyState.querySelector('span');

            if (emptyCopy) {
                emptyCopy.textContent = curve.sourceProjects?.length
                    ? 'Selected projects do not have active dated stages yet.'
                    : 'No source projects found for this filter.';
            }
        }

        if (canvas) {
            canvas.hidden = points.length === 0;
        }

        const labels = points.map((point) => point.label);
        const actualData = points.map((point) => point.actual);
        const planData = points.map((point) => point.planned);

        if (!chart) {
            chart = new window.Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Actual Cumulative (%)',
                            data: actualData,
                            borderColor: colors.actual,
                            backgroundColor: 'rgba(140, 198, 63, 0.16)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.34,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: colors.actual,
                            pointBorderWidth: 2,
                        },
                        {
                            label: 'Planned Cumulative (%)',
                            data: planData,
                            borderColor: colors.planned,
                            backgroundColor: 'rgba(109, 91, 208, 0.08)',
                            borderWidth: 3,
                            fill: false,
                            tension: 0.34,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: colors.planned,
                            pointBorderWidth: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (items) => `${currentXAxisLabel} ${items[0]?.label || '-'}`,
                                label: (context) => `${context.dataset.label}: ${formatPercent(context.parsed.y)}`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: curve.xAxisLabel || 'Date Time',
                                color: colors.text,
                                font: { size: 12, weight: '700' },
                            },
                            grid: { color: colors.grid },
                            ticks: {
                                color: colors.text,
                                autoSkip: true,
                                maxRotation: 35,
                            },
                        },
                        y: {
                            min: 0,
                            max: 100,
                            ticks: {
                                color: colors.text,
                                callback: (value) => `${value}%`,
                            },
                            grid: { color: colors.grid },
                        },
                    },
                },
            });
        } else {
            chart.data.labels = labels;
            chart.data.datasets[0].data = actualData;
            chart.data.datasets[1].data = planData;
            chart.data.datasets[0].hidden = !state.showActual;
            chart.data.datasets[1].hidden = !state.showPlan;
            chart.data.datasets[0].borderColor = colors.actual;
            chart.data.datasets[0].pointBorderColor = colors.actual;
            chart.options.scales.x.grid.color = colors.grid;
            chart.options.scales.y.grid.color = colors.grid;
            chart.options.scales.x.ticks.color = colors.text;
            chart.options.scales.y.ticks.color = colors.text;
            chart.options.scales.x.title.color = colors.text;
            chart.options.scales.x.title.text = curve.xAxisLabel || 'Date Time';
            chart.update();
        }
    };

    const renderKpis = (curve) => {
        const point = finalPoint(curve.points || []);
        const gap = point.planned - point.actual;
        const stageCount = sumStages(curve.sourceProjects || []);

        setText(kpiPlan, formatPercent(point.planned));
        setText(kpiActual, formatPercent(point.actual));
        setText(kpiGap, formatPercent(gap));
        setText(kpiSources, String((curve.sourceProjects || []).length));
        // setText(kpiPlanNote, 'Normalized target');
        // setText(kpiActualNote, `Weighted actual from ${formatPercent(curve.activeShare || 100)} source weight`);
        setText(kpiGapNote, gap <= 0 ? 'On or above plan' : 'Behind plan');
        setText(kpiSourcesNote, `${stageCount} active stages`);
        setText(chartTitle, curve.title);
        setText(chartSubtitle, curve.subtitle);
        setText(scrubberLabel, curve.xAxisLabel || 'Date Time');
        setText(pointDateLabel, curve.xAxisLabel || 'Date Time');
        setText(contributionSubtitle, 'Contribution after weight normalization.');
        setText(sourceSubtitle, `${stageCount} stages from ${(curve.sourceProjects || []).length} project(s).`);
    };

    const renderScrubber = (points) => {
        const count = points.length;

        if (!scrubber) {
            return;
        }

        scrubber.disabled = count === 0;
        scrubber.min = count ? '1' : '0';
        scrubber.max = String(Math.max(count, 1));
        state.selectedIndex = count ? Math.min(state.selectedIndex, count - 1) : 0;
        scrubber.value = count ? String(state.selectedIndex + 1) : '0';
        setText(scrubberOutput, count ? points[state.selectedIndex].label : '--');
    };

    const renderPoint = (points) => {
        const point = points[state.selectedIndex];

        if (!point) {
            setText(pointDate, '--');
            setText(pointPlan, '--');
            setText(pointActual, '--');
            setText(pointGap, '--');

            return;
        }

        setText(pointDate, point.label);
        setText(pointPlan, formatPercent(point.planned));
        setText(pointActual, formatPercent(point.actual));
        setText(pointGap, formatPercent(point.planned - point.actual));
    };

    const appendCell = (row, value, className = '') => {
        const cell = document.createElement('td');

        cell.textContent = value;

        if (className) {
            cell.className = className;
        }

        row.appendChild(cell);
    };

    const appendEmptyRow = (tbody, colspan, message) => {
        const row = document.createElement('tr');
        const cell = document.createElement('td');

        cell.colSpan = colspan;
        cell.className = 'center';
        cell.textContent = message;
        row.appendChild(cell);
        tbody.appendChild(row);
    };

    const renderTables = (curve) => {
        const points = curve.points || [];
        const sourceProjects = curve.sourceProjects || [];

        if (curveTableBody) {
            curveTableBody.textContent = '';

            if (points.length === 0) {
                appendEmptyRow(curveTableBody, 4, 'No cumulative data.');
            } else {
                points.forEach((point) => {
                    const row = document.createElement('tr');

                    appendCell(row, point.label);
                    appendCell(row, formatPercent(point.planned), 'score-a');
                    appendCell(row, formatPercent(point.actual), 'score-b');
                    appendCell(row, formatPercent(point.planned - point.actual), point.planned - point.actual <= 0 ? 'score-b' : 'score-c');
                    curveTableBody.appendChild(row);
                });
            }
        }

        if (sourceTableBody) {
            sourceTableBody.textContent = '';

            if (sourceProjects.length === 0) {
                appendEmptyRow(sourceTableBody, 4, 'No source projects.');
            } else {
                sourceProjects.forEach((project) => {
                    const localFinal = finalPoint(projectCurve(project));
                    const row = document.createElement('tr');

                    appendCell(row, project.name || '-');
                    appendCell(row, project.type || '-');
                    appendCell(row, String(project.stages?.length || 0));
                    appendCell(row, formatPercent(localFinal.rawActual), 'score-b');
                    sourceTableBody.appendChild(row);
                });
            }
        }
    };

    const renderContributions = (curve) => {
        if (!contributionList) {
            return;
        }

        contributionList.textContent = '';

        (curve.typeSummaries || []).forEach((summary) => {
            const card = document.createElement('button');
            const head = document.createElement('div');
            const title = document.createElement('strong');
            const icon = document.createElement('i');
            const meta = document.createElement('span');
            const values = document.createElement('div');
            const actual = document.createElement('b');
            const target = document.createElement('small');
            const track = document.createElement('div');
            const fill = document.createElement('div');

            card.type = 'button';
            card.className = 'exposure-contribution-card';
            card.classList.toggle('active', state.mode === 'type' && state.typeKey === summary.key);
            card.classList.toggle('is-empty', summary.projectCount === 0);
            icon.className = summary.icon || 'fa-solid fa-circle';
            icon.style.color = summary.color || '#006838';
            title.textContent = summary.label;
            meta.textContent = `${summary.projectCount} project | ${summary.stageCount} stage`;
            actual.textContent = formatPercent(summary.actualContribution);
            // target.textContent = `${formatPercent(summary.normalizedShare)} normalized from ${formatPercent(summary.share)} weight`;
            track.className = 'exposure-contribution-track';
            fill.style.width = `${Math.max(0, Math.min(100, summary.actualContribution))}%`;
            fill.style.backgroundColor = summary.color || '#006838';
            head.className = 'exposure-contribution-head';
            values.className = 'exposure-contribution-values';
            head.append(icon, title, meta);
            values.append(actual, target);
            track.appendChild(fill);
            card.append(head, values, track);
            card.addEventListener('click', () => {
                state.mode = 'type';
                state.typeKey = summary.key;
                state.selectedIndex = 0;
                render();
            });
            contributionList.appendChild(card);
        });
    };

    const render = () => {
        syncControls();
        const curve = currentCurve();

        renderChart(curve);
        renderKpis(curve);
        renderScrubber(curve.points || []);
        renderPoint(curve.points || []);
        renderTables(curve);
        renderContributions(curve);
    };

    modeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            state.mode = button.dataset.exposureMode || 'main';
            state.projectId = state.projectId || projects[0]?.id || '';
            state.internId = state.internId || interns[0]?.id || '';
            state.typeKey = state.typeKey || projectTypes[0]?.key || '';
            state.selectedIndex = 0;
            render();
        });
    });

    typeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            state.mode = 'type';
            state.typeKey = button.dataset.exposureType || state.typeKey;
            state.selectedIndex = 0;
            render();
        });
    });

    projectFilter?.addEventListener('change', () => {
        state.mode = 'project';
        state.projectId = projectFilter.value;
        state.selectedIndex = 0;
        render();
    });

    internFilter?.addEventListener('change', () => {
        state.mode = 'intern';
        state.internId = internFilter.value;
        state.selectedIndex = 0;
        render();
    });

    typeFilter?.addEventListener('change', () => {
        state.mode = 'type';
        state.typeKey = typeFilter.value;
        state.selectedIndex = 0;
        render();
    });

    resetButton?.addEventListener('click', () => {
        state.mode = 'main';
        state.selectedIndex = 0;
        render();
    });

    actualToggle?.addEventListener('change', () => {
        state.showActual = actualToggle.checked;

        if (!state.showActual && !state.showPlan) {
            state.showActual = true;
            actualToggle.checked = true;
        }

        render();
    });

    planToggle?.addEventListener('change', () => {
        state.showPlan = planToggle.checked;

        if (!state.showActual && !state.showPlan) {
            state.showPlan = true;
            planToggle.checked = true;
        }

        render();
    });

    scrubber?.addEventListener('input', () => {
        state.selectedIndex = Math.max(0, toNumber(scrubber.value) - 1);
        render();
    });

    render();
});
