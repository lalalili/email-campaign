<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>取消訂閱確認</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f8f9fa; }
        .card { background: white; border-radius: 8px; padding: 48px; max-width: 480px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        h1 { font-size: 1.5rem; color: #1a1a1a; margin: 0 0 12px; }
        p { color: #666; line-height: 1.6; margin: 0 0 24px; }
        button { background: #dc2626; color: white; border: none; border-radius: 6px; padding: 12px 32px; font-size: 1rem; cursor: pointer; }
        button:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <div class="card">
        <h1>確認取消訂閱</h1>
        <p>按下確認後，您的電子郵件將從寄送名單中移除，<br>我們將不再寄送相關郵件給您。</p>
        <form method="POST" action="{{ $confirmUrl }}">
            <button type="submit">確認取消訂閱</button>
        </form>
    </div>
</body>
</html>
