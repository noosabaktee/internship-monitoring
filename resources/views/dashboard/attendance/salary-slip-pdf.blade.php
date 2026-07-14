<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 34px; }
        body { font-family: "DejaVu Sans", sans-serif; color: #16281c; font-size: 12px; }
        .slip { border: 1px solid #dfe8d6; padding: 24px; }
        .header { border-bottom: 3px solid #8cc63f; padding-bottom: 16px; margin-bottom: 20px; }
        .brand-row { border-collapse: collapse; margin-bottom: 3px; }
        .brand-row td { vertical-align: middle; padding: 0; }
        .brand-logo { width: 28px; padding-right: 7px !important; }
        .brand-logo img { display: block; width: 22px; height: auto; }
        .brand { color: #006838; font-size: 11px; font-weight: 700; letter-spacing: 1.6px; text-transform: uppercase; }
        h1 { margin: 5px 0 4px; font-size: 26px; color: #12351f; }
        .muted { color: #667085; }
        .profile { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .profile td { width: 25%; padding: 8px 0; vertical-align: top; }
        .label { display: block; color: #667085; font-size: 9px; text-transform: uppercase; letter-spacing: .6px; }
        .value { display: block; margin-top: 3px; font-weight: 700; }
        .net { background: #f3faec; border: 1px solid #cfe5bd; padding: 18px; margin: 18px 0; text-align: center; }
        .net span { color: #667085; text-transform: uppercase; font-size: 10px; letter-spacing: .8px; }
        .net strong { display: block; margin-top: 6px; font-size: 30px; color: #006838; }
        .calc { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .calc th { background: #12351f; color: #fff; text-align: left; padding: 8px; }
        .calc td { border-bottom: 1px solid #e7ecdf; padding: 9px 8px; }
        .calc .amount { text-align: right; font-weight: 700; }
        .deduction { color: #b42318; }
        .attendance { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 10px; }
        .attendance th { background: #edf6e5; padding: 7px; text-align: left; }
        .attendance td { border-bottom: 1px solid #eef2e8; padding: 6px 7px; }
        .signatures { width: 100%; margin-top: 34px; text-align: center; }
        .signatures td { width: 50%; padding-top: 42px; }
        .line { border-top: 1px solid #9aa79a; margin: 0 36px; padding-top: 6px; font-weight: 700; }
    </style>
</head>
<body>
    @php
        $payroll = $payload['payroll'];
        $rows = $payload['rows'];
    @endphp

    <div class="slip">
        <div class="header">
            @include('dashboard.attendance.partials.pdf-brand')
            <h1>Salary Slip</h1>
            <!-- <div class="muted">Slip uang saku internship berdasarkan filter absensi terpilih.</div> -->
        </div>

        <table class="profile">
            <tr>
                <td><span class="label">Intern No</span><span class="value">{{ $payroll['internNo'] }}</span></td>
                <td><span class="label">Intern Name</span><span class="value">{{ $payroll['internName'] }}</span></td>
                <td><span class="label">Type</span><span class="value">{{ $payroll['internType'] }}</span></td>
                <td><span class="label">Period</span><span class="value">{{ $payroll['period'] }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Department</span><span class="value">{{ $payroll['department'] }}</span></td>
                <td><span class="label">Generated</span><span class="value">{{ $payload['generatedAt']->format('d M Y') }}</span></td>
                <td><span class="label">Prepared By</span><span class="value">{{ $payload['generatedBy'] }}</span></td>
            </tr>
        </table>

        <div class="net">
            <span>Net Salary</span>
            <strong>Rp {{ number_format((float) $payroll['netSalary'], 0, ',', '.') }}</strong>
        </div>

        <table class="calc">
            <thead>
                <tr><th>Description</th><th>Days</th><th class="amount">Amount</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Salary / workday x total workdays</td>
                    <td>{{ $payroll['workdays'] }} x Rp {{ number_format((float) $payroll['dailySalary'], 0, ',', '.') }}</td>
                    <td class="amount">Rp {{ number_format((float) $payroll['grossSalary'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Absent deduction</td>
                    <td>{{ $payroll['absentDays'] }} x Rp {{ number_format((float) $payroll['dailySalary'], 0, ',', '.') }}</td>
                    <td class="amount deduction">- Rp {{ number_format((float) $payroll['deduction'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td><strong>Final salary</strong></td>
                    <td>{{ $payroll['paidDays'] }} calculated paid days</td>
                    <td class="amount">Rp {{ number_format((float) $payroll['netSalary'], 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <table class="attendance">
            <thead>
                <tr><th>Date</th><th>Status</th><th>Clock In</th><th>Clock Out</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['date']->format('d M Y') }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td>{{ $row['clockIn'] }} ({{ $row['clockInStatus'] ?: '-' }})</td>
                        <td>{{ $row['clockOut'] }} ({{ $row['clockOutStatus'] ?: '-' }})</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="signatures">
            <tr>
                <td><div class="line">HRD</div></td>
                <td><div class="line">Headmaster</div></td>
            </tr>
        </table>
    </div>
</body>
</html>
