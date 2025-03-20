<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xAPI Raporlama Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
        }
        .user-card {
            transition: all 0.3s ease;
        }
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
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
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">Kullanıcılar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('content.index') }}">İçerik Yönetimi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('dashboard.reports') }}">Raporlar</a>
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

    <div class="container mt-4">
        <h1>xAPI Raporlama Paneli</h1>
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Toplam İfade</h5>
                        <p class="card-text display-4">{{ $totalStatements }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Toplam Kullanıcı</h5>
                        <p class="card-text display-4">{{ $totalActors }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Toplam Aktivite</h5>
                        <p class="card-text display-4">{{ $totalActivities }}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-5">
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'users' ? 'active' : '' }}" href="{{ route('dashboard.reports') }}">
                        <i class="bi bi-people"></i> Kullanıcılara Göre
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'activities' ? 'active' : '' }}" href="{{ route('dashboard.activities') }}">
                        <i class="bi bi-book"></i> İçeriklere Göre
                    </a>
                </li>
            </ul>
            
            <h2>Kullanıcı Raporları</h2>
            
            @if(count($userReports) > 0)
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">
                    @foreach($userReports as $report)
                    <div class="col">
                        <div class="card user-card h-100">
                            <div class="card-body">
                                <h5 class="card-title">{{ $report['name'] }}</h5>
                                <p class="card-text text-muted">{{ $report['email'] }}</p>
                                
                                <div class="mt-3">
                                    <p><strong>Tamamlanan İçerikler:</strong> {{ $report['completed_activities'] }}</p>
                                    <p><strong>Toplam Etkileşim:</strong> {{ $report['total_statements'] }}</p>
                                    @if($report['last_activity'])
                                    <p><strong>Son Aktivite:</strong> {{ \Carbon\Carbon::parse($report['last_activity'])->format('d.m.Y H:i') }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="{{ route('user.details', $report['id']) }}" class="btn btn-primary w-100">
                                    <i class="bi bi-bar-chart"></i> Detaylı Rapor
                                </a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Henüz xAPI verisi bulunan kullanıcı yok.
                </div>
            @endif
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
