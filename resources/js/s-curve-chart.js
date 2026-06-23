document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) {
        return;
    }

    document.querySelectorAll('[data-scurve-chart]').forEach((element) => {
        const payloadElement = element.querySelector('.s-curve-payload');
        const canvas = element.querySelector('canvas');

        if (!payloadElement || !canvas) {
            return;
        }

        const payload = JSON.parse(payloadElement.textContent || '{}');
        const renderer = createSCurveRenderer(element, payload, canvas);

        renderer.render();
    });
});

const createSCurveRenderer = (element, payload, canvas) => {
    const projects = Array.isArray(payload.projects) ? payload.projects : [];
    const projectTypes = Array.isArray(payload.projectTypes) ? payload.projectTypes : [];
    const mode = element.dataset.scurveMode || 'main';
    const emptyState = element.querySelector('[data-scurve-empty]');
    const planKpi = element.querySelector('[data-scurve-plan]');
    const actualKpi = element.querySelector('[data-scurve-actual]');
    const gapKpi = element.querySelector('[data-scurve-gap]');
    const sourceKpi = element.querySelector('[data-scurve-source]');
    const dayMs = 86400000;
    const dateFormatter = new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
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
    const monthFormatter = new Intl.DateTimeFormat('id-ID', {
        month: 'short',
        year: 'numeric',
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
    const formatPercent = (value) => `${formatNumber(value)}%`;
    const setText = (target, value) => {
        if (target) {
            target.textContent = value;
        }
    };
    const dateValue = (value) => {
        if (!value) {
            return null;
        }

        const parsed = new Date(String(value).replace(' ', 'T'));

        return Number.isNaN(parsed.getTime()) ? null : parsed.getTime();
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
    const typeByKey = (key) => projectTypes.find((type) => type.key === key);
    const sortStagesByDate = (project) => [...(project.stages || [])].sort((left, right) => {
        const leftDate = dateValue(left.end || left.start || project.end || project.start) || 0;
        const rightDate = dateValue(right.end || right.start || project.end || project.start) || 0;

        return leftDate === rightDate ? toNumber(left.number) - toNumber(right.number) : leftDate - rightDate;
    });
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
    const projectCurve = (project) => {
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

        stages.forEach((stage) => {
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
            });
        });

        if (points.length > 1) {
            points[points.length - 1].planned = 100;
            points[points.length - 1].rawPlanned = 100;
        }

        return mergeSameDatePoints(points);
    };
    const projectPointAt = (project, date) => {
        const points = projectCurve(project);
        let selected = points[0]?.date <= date ? points[0] : null;

        for (const point of points) {
            if (point.date <= date) {
                selected = point;
            } else {
                break;
            }
        }

        return selected || { rawPlanned: 0, rawActual: 0 };
    };
    const timelineDates = (sourceProjects) => {
        const dates = sourceProjects
            .flatMap((project) => projectCurve(project).map((point) => point.date))
            .filter((value) => Number.isFinite(value));

        return [...new Set(dates)].sort((left, right) => left - right);
    };
    const finalPoint = (points) => points.at(-1) || {
        planned: 0,
        actual: 0,
        rawPlanned: 0,
        rawActual: 0,
    };
    const aggregateTypeProjects = (typeProjects, dates) => {
        const projectsWithStages = typeProjects.filter((project) => projectCurve(project).length > 1);

        if (projectsWithStages.length === 0 || dates.length === 0) {
            return [];
        }

        return dates.map((date) => {
            const totals = projectsWithStages.reduce((carry, project) => {
                const point = projectPointAt(project, date);

                carry.planned += point.rawPlanned;
                carry.actual += point.rawActual;

                return carry;
            }, { planned: 0, actual: 0 });

            return {
                date,
                label: dateLabel(date),
                rawPlanned: round(totals.planned / projectsWithStages.length),
                rawActual: round(totals.actual / projectsWithStages.length),
            };
        });
    };
    const compactMonthlyPoints = (points) => {
        const monthly = new Map();

        points.forEach((point) => {
            monthly.set(monthKey(point.date), {
                ...point,
                label: monthLabel(point.date),
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
    const weightedCurve = () => {
        const projectsWithStages = projects.filter((project) => projectCurve(project).length > 1);
        const dates = timelineDates(projectsWithStages);
        const activeShare = projectTypes.reduce((sum, type) => {
            const hasProject = projectsWithStages.some((project) => project.typeKey === type.key);

            return hasProject ? sum + toNumber(type.share) : sum;
        }, 0);
        const denominator = activeShare > 0 ? activeShare : 100;
        const typeCurves = projectTypes.map((type) => {
            const typeProjects = projectsWithStages.filter((project) => project.typeKey === type.key);

            return {
                share: toNumber(typeByKey(type.key)?.share),
                projectCount: typeProjects.length,
                points: aggregateTypeProjects(typeProjects, dates),
            };
        });
        const points = dates.map((date, index) => {
            const totals = typeCurves.reduce((carry, typeCurve) => {
                if (typeCurve.projectCount === 0) {
                    return carry;
                }

                const point = typeCurve.points[index] || { rawPlanned: 0, rawActual: 0 };

                carry.planned += (point.rawPlanned * typeCurve.share) / denominator;
                carry.actual += (point.rawActual * typeCurve.share) / denominator;

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
            points: mode === 'project' ? points : compactMonthlyPoints(points),
            sourceProjects: projectsWithStages,
            xAxisLabel: mode === 'project' ? 'Date Time' : 'Month',
        };
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

    return {
        render() {
            const curve = weightedCurve();
            const points = curve.points;
            const final = finalPoint(points);
            const gap = final.planned - final.actual;
            const colors = chartColors();

            setText(planKpi, formatPercent(final.planned));
            setText(actualKpi, formatPercent(final.actual));
            setText(gapKpi, formatPercent(gap));
            setText(sourceKpi, `${curve.sourceProjects.length}`);

            if (emptyState) {
                emptyState.hidden = points.length > 0;
            }

            canvas.hidden = points.length === 0;

            new window.Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: points.map((point) => point.label),
                    datasets: [
                        {
                            label: 'Actual Cumulative (%)',
                            data: points.map((point) => point.actual),
                            borderColor: colors.actual,
                            backgroundColor: 'rgba(140, 198, 63, 0.16)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.34,
                            pointRadius: mode === 'project' ? 3 : 2,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: colors.actual,
                            pointBorderWidth: 2,
                        },
                        {
                            label: 'Planned Cumulative (%)',
                            data: points.map((point) => point.planned),
                            borderColor: colors.planned,
                            backgroundColor: 'rgba(109, 91, 208, 0.08)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.34,
                            pointRadius: mode === 'project' ? 3 : 2,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: colors.planned,
                            pointBorderWidth: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (items) => `${curve.xAxisLabel} ${items[0]?.label || '-'}`,
                                label: (context) => `${context.dataset.label}: ${formatPercent(context.parsed.y)}`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: curve.xAxisLabel,
                                color: colors.text,
                                font: { size: 11, weight: '700' },
                            },
                            grid: { color: colors.grid },
                            ticks: {
                                color: colors.text,
                                autoSkip: true,
                                maxRotation: mode === 'project' ? 35 : 0,
                                font: { size: 10 },
                            },
                        },
                        y: {
                            min: 0,
                            max: 100,
                            ticks: {
                                color: colors.text,
                                callback: (value) => `${value}%`,
                                font: { size: 10 },
                            },
                            grid: { color: colors.grid },
                        },
                    },
                },
            });
        },
    };
};
