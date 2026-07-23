<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; overflow: hidden; }
        body { font-family: DejaVu Sans, sans-serif; color: #17332c; background: #f7faf4; }
        .page { position: relative; width: 278mm; height: 192mm; margin: 4mm auto 0; padding: 20px; overflow: hidden; }
        .outer { position: absolute; inset: 10px; border: 3px solid #17332c; }
        .inner { position: absolute; inset: 17px; border: 1px solid #8cc63f; }
        .shape-a { position: absolute; width: 310px; height: 310px; background: #8cc63f; opacity: .12; border-radius: 50%; top: -170px; right: -80px; }
        .shape-b { position: absolute; width: 260px; height: 260px; background: #17332c; opacity: .07; border-radius: 50%; bottom: -150px; left: -70px; }
        .accent { position: absolute; top: 0; left: 0; width: 100%; height: 12px; background: #8cc63f; }
        .content { position: relative; z-index: 2; text-align: center; padding: 6px 56px; }
        .brand { height: 45px; margin-bottom: 4px; }
        .brand-text { font-size: 15px; font-weight: bold; letter-spacing: 3px; color: #507c25; }
        .eyebrow { margin-top: 4px; font-size: 10px; letter-spacing: 5px; text-transform: uppercase; color: #567168; }
        h1 { margin: 2px 0 0; font-family: Georgia, serif; font-size: 38px; font-weight: normal; letter-spacing: 2px; color: #17332c; }
        .line { width: 88px; height: 3px; margin: 7px auto 11px; background: #8cc63f; }
        .presented { font-size: 12px; color: #64756f; }
        .name { margin: 5px 0; font-family: Georgia, serif; font-size: 30px; font-style: italic; color: #315f2c; }
        .description { width: 78%; margin: 0 auto; font-size: 12px; line-height: 1.55; color: #435c54; }
        .description strong { color: #17332c; }
        .score-row { margin: 13px auto 8px; width: 72%; border-collapse: separate; border-spacing: 7px 0; }
        .score-row td { width: 20%; padding: 8px 5px; border: 1px solid #d9e6d0; background: #fff; border-radius: 7px; }
        .score-row span { display: block; font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #77877f; }
        .score-row strong { display: block; margin-top: 2px; font-size: 17px; color: #315f2c; }
        .footer { width: 84%; margin: 12px auto 0; display: table; table-layout: fixed; }
        .footer > div { display: table-cell; width: 33.33%; vertical-align: bottom; }
        .meta { text-align: left; font-size: 8px; line-height: 1.55; color: #667970; }
        .seal { text-align: center; }
        .seal-circle { display: inline-block; width: 56px; height: 56px; border: 2px solid #8cc63f; border-radius: 50%; padding-top: 11px; font-size: 8px; font-weight: bold; letter-spacing: 1px; color: #315f2c; }
        .signature { text-align: center; font-size: 9px; }
        .signature-mark { font-family: Georgia, serif; font-size: 19px; font-style: italic; color: #315f2c; margin-bottom: 2px; }
        .signature-line { border-top: 1px solid #17332c; padding-top: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="page">
        <div class="accent"></div><div class="outer"></div><div class="inner"></div><div class="shape-a"></div><div class="shape-b"></div>
        <div class="content">
            @if ($logoData)<img class="brand" src="{{ $logoData }}" alt="KDC">@else<div class="brand-text">KALBE DIGITAL CORE</div>@endif
            <div class="eyebrow">Certificate of Completion</div>
            <h1>Sertifikat Internship</h1>
            <div class="line"></div>
            <div class="presented">Dengan bangga diberikan kepada</div>
            <div class="name">{{ $intern->txtInternName }}</div>
            <p class="description">
                Atas keberhasilan menyelesaikan program internship di <strong>Kalbe Digital Core</strong>
                sebagai intern <strong>{{ $intern->txtDept ?: 'Digital & Technology' }}</strong>
                @if ($startDate && $endDate) pada periode {{ $startDate->format('d F Y') }} sampai {{ $endDate->format('d F Y') }}@endif,
                dengan menunjukkan kontribusi, kolaborasi, ownership, dan semangat berbagi pengetahuan yang baik.
            </p>
            <table class="score-row">
                <tr>
                    <td><span>Hard Skill</span><strong>{{ number_format((float) $evaluation->floatHardSkill, 0) }}</strong></td>
                    <td><span>Collaboration</span><strong>{{ number_format((float) $evaluation->floatCollaboration, 0) }}</strong></td>
                    <td><span>Ownership</span><strong>{{ number_format((float) $evaluation->floatOwnership, 0) }}</strong></td>
                    <td><span>Sharing</span><strong>{{ number_format((float) $evaluation->floatSharing, 0) }}</strong></td>
                    <td><span>Final Score</span><strong>{{ number_format((float) $evaluation->floatExposureScore, 0) }}</strong></td>
                </tr>
            </table>
            <div class="footer">
                <div class="meta">
                    <strong>No. {{ $certificateNumber }}</strong><br>
                    Diterbitkan {{ ($evaluation->dtmEvaluationCertificatePublished ?? $evaluation->dtmEvaluationCompleted)?->format('d F Y') }}<br>
                    Kalbe Digital Core Internship Program
                </div>
                <div class="seal"><div class="seal-circle">KDC<br>CERTIFIED<br>INTERNSHIP</div></div>
                <div class="signature">
                    <div class="signature-mark">Approved</div>
                    <div class="signature-line">{{ $evaluatorName }}</div>
                    <div>Mentor / Headmaster</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
