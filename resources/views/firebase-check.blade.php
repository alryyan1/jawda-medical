<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فحص اتصال Firebase</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #0f172a;
            color: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .card {
            background: #020617;
            border-radius: 16px;
            padding: 24px 28px;
            box-shadow: 0 20px 40px rgba(15,23,42,0.7);
            min-width: 360px;
            border: 1px solid #1f2937;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .subtitle {
            font-size: 13px;
            color: #9ca3af;
            margin-bottom: 16px;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .status-ok {
            background: rgba(22,163,74,0.15);
            color: #bbf7d0;
            border: 1px solid rgba(34,197,94,0.6);
        }
        .status-fail {
            background: rgba(220,38,38,0.15);
            color: #fecaca;
            border: 1px solid rgba(248,113,113,0.6);
        }
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
        }
        .dot-ok {
            background: #22c55e;
        }
        .dot-fail {
            background: #f97373;
        }
        .issues-title {
            font-size: 14px;
            font-weight: 600;
            margin-top: 8px;
            margin-bottom: 4px;
        }
        ul {
            margin: 0;
            padding-left: 18px;
            font-size: 13px;
            color: #e5e7eb;
        }
        .issue-ok {
            font-size: 13px;
            color: #a7f3d0;
        }
        .hint {
            margin-top: 16px;
            font-size: 12px;
            color: #6b7280;
        }
        code {
            background: rgba(15,23,42,0.9);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
        .section {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #1f2937;
        }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #9ca3af;
            margin-bottom: 8px;
        }
        .project-id {
            font-size: 15px;
            font-weight: 600;
            color: #e5e7eb;
            word-break: break-all;
        }
        .collections-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .collections-list li {
            padding: 6px 10px;
            background: rgba(15,23,42,0.8);
            border-radius: 8px;
            margin-bottom: 6px;
            font-size: 13px;
            font-family: ui-monospace, monospace;
        }
        .collections-list li:last-child {
            margin-bottom: 0;
        }
        .collections-error {
            font-size: 13px;
            color: #fca5a5;
        }
        .collections-empty {
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="title">فحص اتصال Firebase</div>
    <div class="subtitle">هذه الصفحة تستخدم إعدادات <code>firebase-service-account.json</code> للتحقق من الاتصال.</div>

    @if($ok)
        <div class="status status-ok">
            <span class="dot dot-ok"></span>
            <span>الاتصال ناجح ✅</span>
        </div>
        <div class="issue-ok">تم الحصول على access token من Firebase بنجاح.</div>

        @if(isset($projectId) && $projectId)
            <div class="section">
                <div class="section-title">مشروع Firebase</div>
                <div class="project-id">{{ $projectId }}</div>
            </div>
        @endif

        @if(isset($collectionsError) && $collectionsError)
            <div class="section">
                <div class="section-title">المجموعات (Firestore)</div>
                <div class="collections-error">{{ $collectionsError }}</div>
            </div>
        @elseif(isset($collections))
            <div class="section">
                <div class="section-title">المجموعات الرئيسية (Firestore)</div>
                @if(count($collections) > 0)
                    <ul class="collections-list">
                        @foreach($collections as $id)
                            <li>{{ $id }}</li>
                        @endforeach
                    </ul>
                @else
                    <div class="collections-empty">لا توجد مجموعات على المستوى الجذري.</div>
                @endif
            </div>
        @endif
    @else
        <div class="status status-fail">
            <span class="dot dot-fail"></span>
            <span>الاتصال فشل ❌</span>
        </div>

        @if(!empty($issues))
            <div class="issues-title">المشاكل المكتشفة في الإعدادات:</div>
            <ul>
                @foreach($issues as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        @else
            <div class="issues-title">لم يتم الحصول على access token من Firebase.</div>
            <div class="hint">راجع ملف الخدمة وإعدادات <code>config/firebase.php</code>.</div>
        @endif
    @endif

    <div class="hint">
        افتح هذه الصفحة من المتصفح عبر المسار <code>/firebase-check</code> للتحقق من الاتصال.
    </div>
</div>
</body>
</html>

