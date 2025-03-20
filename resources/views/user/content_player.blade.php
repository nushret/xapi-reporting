<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $activityName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        .content-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        .content-header {
            background-color: #343a40;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .content-frame {
            flex: 1;
            border: none;
            width: 100%;
            height: 100%;
            background-color: white;
        }
        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .close-btn:hover {
            color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="content-container">
        <div class="content-header">
            <h5 class="mb-0">{{ $activityName }}</h5>
            <button class="close-btn" id="closeContent">
                <i class="bi bi-x-lg"></i> Kapat
            </button>
        </div>
        <iframe src="{{ $contentUrl }}" class="content-frame" allowfullscreen></iframe>
    </div>

    <script>
        // Kapat butonuna tıklandığında kullanıcı dashboard'a yönlendirilir
        document.getElementById('closeContent').addEventListener('click', function() {
            window.location.href = "{{ route('user.dashboard') }}";
        });
        
        // ESC tuşuna basıldığında da kapat
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                window.location.href = "{{ route('user.dashboard') }}";
            }
        });
        
        // Sayfa yüklendiğinde tarayıcı geçmişini değiştir (URL'i gizle)
        window.onload = function() {
            history.pushState({}, '', "{{ route('user.dashboard') }}");
        };
    </script>
</body>
</html>
