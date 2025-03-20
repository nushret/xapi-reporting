<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xAPI İçerik Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- Menü Ekleniyor -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">xAPI Yönetim Paneli</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
			<ul class="navbar-nav me-auto">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Kullanıcılar</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('content.index') ? 'active' : '' }}" href="{{ route('content.index') }}">İçerik Yönetimi</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.reports') ? 'active' : '' }}" href="{{ route('dashboard.reports') }}">Raporlar</a>
    </li>
</ul>

                <div class="d-flex">
                    <span class="navbar-text me-3">
                        {{ Auth::user()->full_name }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-light">Çıkış Yap</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
    <!-- Menü Eklendi -->

    <div class="container">
        <h1 class="my-4">xAPI İçerik Yönetimi</h1>
        
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <div class="card mb-4">
            <div class="card-header">Yeni İçerik Yükle</div>
            <div class="card-body">
                <form action="{{ route('content.upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="title" class="form-label">İçerik Başlığı</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="content_file" class="form-label">xAPI Zip Dosyası</label>
                        <input type="file" class="form-control" id="content_file" name="content_file" accept=".zip" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Yükle</button>
                </form>
            </div>
        </div>
        
        <h2>Mevcut İçerikler</h2>
        <div class="row">
            @foreach($contents as $content)
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">{{ $content['name'] }}</h5>
                            <p class="card-text">
                                <small class="text-muted">Yol: {{ $content['path'] }}</small>
                            </p>
                            <a href="{{ route('content.launch', $content['path']) }}" class="btn btn-primary">Başlat</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        <div class="mt-4 mb-5">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Panele Dön</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

