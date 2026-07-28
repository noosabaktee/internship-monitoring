<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; background: #f9f9f9; }
        .page {
            position: relative;
            width: 297mm;
            height: 210mm;
            overflow: hidden;
            page-break-after: always;
            background-color: #f9f9f9;
            background-repeat: no-repeat;
            background-position: center;
            background-size: 297mm 210mm;
        }
        .page:last-child { page-break-after: auto; }
        .page-one { background-image: url('{{ $pageOneBackground }}'); }
        .page-two { background-image: url('{{ $pageTwoBackground }}'); }
        .center { position: absolute; left: 0; width: 100%; text-align: center; }
        .green { color: #218b72; }
        .certificate-title {
            top: 17mm;
            font-size: 35pt;
            line-height: 1;
            font-weight: 700;
            letter-spacing: .8mm;
        }
        .certificate-subtitle {
            top: 40mm;
            font-size: 15pt;
            line-height: 1;
            letter-spacing: .25mm;
        }
        .certificate-presented { top: 61mm; font-size: 11pt; }
        .certificate-name {
            top: 73mm;
            font-family: DejaVu Serif, serif;
            font-size: 30pt;
            line-height: 1.15;
            color: #218b72;
        }
        .certificate-name-rule {
            position: absolute;
            left: 72.5mm;
            top: 89.7mm;
            width: 151.5mm;
            border-top: .55mm solid #08705b;
        }
        .certificate-name-rule::before,
        .certificate-name-rule::after {
            content: '';
            position: absolute;
            top: -1.15mm;
            width: 1.6mm;
            height: 1.6mm;
            border: .5mm solid #08705b;
            border-radius: 50%;
            background: #f9f9f9;
        }
        .certificate-name-rule::before { left: -.9mm; }
        .certificate-name-rule::after { right: -.9mm; }
        .certificate-school {
            top: 93mm;
            font-size: 10.5pt;
            color: #4b4b4b;
        }
        .certificate-copy {
            position: absolute;
            left: 24mm;
            top: 106mm;
            width: 249mm;
            margin: 0;
            text-align: center;
            font-size: 10.7pt;
            line-height: 1.45;
            letter-spacing: .05mm;
        }
        .certificate-copy strong { font-weight: 700; }
        .certificate-place {
            top: 149mm;
            color: #176b5e;
            font-size: 10.5pt;
            font-weight: 700;
        }
        .signature {
            position: absolute;
            text-align: center;
            color: #006c57;
        }
        .signature-one { left: 112mm; top: 189mm; width: 73mm; }
        .signature-two { left: 185mm; top: 182mm; width: 77mm; }
        .signature-rule { border-top: .35mm solid #718109; padding-top: 2.2mm; }
        .signature-name {
            font-size: 9.5pt;
            line-height: 1.1;
            font-weight: 700;
            text-decoration: underline;
        }
        .signature-role { margin-top: .5mm; font-size: 9.2pt; line-height: 1.1; }

        .report-title {
            top: 28mm;
            color: #218b72;
            font-size: 19.5pt;
            line-height: 1;
            font-weight: 700;
            letter-spacing: .25mm;
        }
        .watermark {
            position: absolute;
            left: 76mm;
            top: -19mm;
            width: 148mm;
            height: 209mm;
            opacity: .055;
        }
        .assessment {
            position: absolute;
            left: 40.7mm;
            top: 45.9mm;
            width: 216.1mm;
            border-collapse: collapse;
            table-layout: fixed;
            background: transparent;
            font-size: 9pt;
        }
        .assessment th,
        .assessment td {
            border: .3mm solid #5aa24d;
            padding: 0;
            vertical-align: middle;
            background: transparent;
        }
        .assessment thead tr { height: 28.6mm; }
        .assessment tbody tr { height: 8.48mm; }
        .assessment th { font-size: 10pt; font-weight: 700; }
        .assessment .number { width: 9.2%; text-align: center; font-weight: 700; }
        .assessment .criterion { width: 53.5%; padding-left: .2mm; font-weight: 700; }
        .assessment .score { width: 18.2%; text-align: center; }
        .assessment .grade { width: 19.1%; text-align: center; }
        .description-title {
            position: absolute;
            left: 41.2mm;
            top: 132mm;
            margin: 0;
            color: #176b5e;
            font-size: 11pt;
            font-weight: 700;
        }
        .grade-description {
            position: absolute;
            left: 40.7mm;
            top: 142.3mm;
            width: 92.8mm;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.6pt;
        }
        .grade-description th,
        .grade-description td {
            height: 9.5mm;
            border: .25mm solid #d5d5d5;
            padding: 0 2.2mm;
            text-align: left;
            vertical-align: middle;
        }
        .grade-description th { font-size: 7pt; font-weight: 700; }
        .grade-description th:nth-child(1),
        .grade-description td:nth-child(1) { width: 35%; }
        .grade-description th:nth-child(2),
        .grade-description td:nth-child(2) { width: 22%; text-align: center; }
        .grade-description th:nth-child(3),
        .grade-description td:nth-child(3) { width: 43%; }
        .report-place {
            position: absolute;
            left: 177mm;
            top: 145.2mm;
            width: 84mm;
            text-align: center;
            color: #176b5e;
            font-size: 10.5pt;
            font-weight: 700;
        }
    </style>
</head>
<body>
    @php
        $department = trim((string) ($intern->txtDept ?: 'Integrated Operation System'));
        $certificateDate = $evaluation->dtmEvaluationCertificatePublished
            ?? $evaluation->dtmEvaluationCompleted
            ?? now('Asia/Jakarta');
        $criteria = $evaluation->assessmentCriteria();
        $signatoryName = 'EMANUELLE NATHANIA LIANTO';
        $signatoryRole = 'Human Capital Dept Head';
    @endphp

    <section class="page page-one">
        <div class="center certificate-title green">CERTIFICATE</div>
        <div class="center certificate-subtitle">OF COMPLETION</div>
        <div class="center certificate-presented">This is to certify that</div>
        <div class="center certificate-name">{{ $intern->txtInternName }}</div>
        <div class="certificate-name-rule"></div>
        <div class="center certificate-school">{{ $intern->txtUniversity ?: 'PT Kalbe Morinaga Indonesia' }}</div>
        <p class="certificate-copy">
            Has successfully completed the Internship Program as a <strong>{{ $department }} Intern</strong><br>
            in the <strong>{{ $department }} Department</strong><br>
            @if ($startDate && $endDate)
                from <strong>{{ $startDate->format('j F Y') }} - {{ $endDate->format('j F Y') }}</strong><br>
            @endif
            We sincerely appreciate your dedication, commitment, and valuable contributions throughout your<br>
            internship at PT Kalbe Morinaga Indonesia.
        </p>
        <div class="center certificate-place">Karawang,{{ $certificateDate->format('j F Y') }}</div>
        <div class="signature signature-one">
            <div class="signature-rule">
                <div class="signature-name">{{ $signatoryName }}</div>
                <div class="signature-role">{{ $signatoryRole }}</div>
            </div>
        </div>
    </section>

    <section class="page page-two">
        @if ($watermarkData)<img class="watermark" src="{{ $watermarkData }}" alt="">@endif
        <div class="center report-title">INTERNSHIP ASSESSMENT REPORT</div>
        <table class="assessment">
            <thead>
                <tr>
                    <th class="number">No</th>
                    <th class="criterion">Assessment Criteria</th>
                    <th class="score">Score</th>
                    <th class="grade">Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($criteria as $index => $criterion)
                    <tr>
                        <td class="number">{{ $index + 1 }}</td>
                        <td class="criterion">{{ $criterion['label'] }}</td>
                        <td class="score">{{ number_format($criterion['score'], 0) }}</td>
                        <td class="grade">{{ $criterion['grade'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2 class="description-title">Description :</h2>
        <table class="grade-description">
            <thead>
                <tr><th>Score Range</th><th>Grade</th><th>Remarks</th></tr>
            </thead>
            <tbody>
                <tr><td>90–100</td><td>A</td><td>Excellent</td></tr>
                <tr><td>80–89</td><td>B</td><td>Good</td></tr>
                <tr><td>70–79</td><td>C</td><td>Satisfactory</td></tr>
                <tr><td>60–69</td><td>D</td><td>Needs Improvement</td></tr>
                <tr><td>0–59</td><td>E</td><td>Fail</td></tr>
            </tbody>
        </table>
        <div class="report-place">Karawang, {{ $certificateDate->format('j F Y') }}</div>
        <div class="signature signature-two">
            <div class="signature-rule">
                <div class="signature-name">{{ $signatoryName }}</div>
                <div class="signature-role">{{ $signatoryRole }}</div>
            </div>
        </div>
    </section>
</body>
</html>
