<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İçeriklerim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .content-card {
            transition: all 0.3s ease;
            height: 100%;
        }
        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ route('user.dashboard') }}">xAPI İçerik Portalı</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('user.dashboard') }}">İçeriklerim</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        {{ $user->full_name }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-light">Çıkış Yap</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="mb-4">İçeriklerim</h1>
        
        @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
        @endif
        
        @if($contents->count() > 0)
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            @foreach($contents as $content)
            <div class="col">
                <div class="card content-card">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">{{ $content->path }}</h5>
                        <p class="card-text text-muted mb-4">
                            Bu içeriği başlatmak için aşağıdaki butona tıklayın.
                        </p>
                        <a href="{{ route('user.content.launch', $content->path) }}" class="btn btn-primary mt-auto">
                            <i class="bi bi-play-fill"></i> Başlat
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="alert alert-info">
            <i class="bi bi-info-circle-fill me-2"></i> Henüz size atanmış içerik bulunmamaktadır. Lütfen yöneticinizle iletişime geçin.
        </div>
        @endif
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
