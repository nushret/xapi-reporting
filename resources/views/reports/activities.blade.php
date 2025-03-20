<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xAPI İçerik Raporları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
        }
        .progress-bar-success {
            background-color: #198754;
        }
        .progress-bar-warning {
            background-color: #ffc107;
        }
        .progress-bar-danger {
            background-color: #dc3545;
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
        <h1>xAPI İçerik Raporları</h1>
        
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
            
            <h2>İçerik Raporları</h2>
            
            @if(count($activities) > 0)
                <div class="table-responsive mt-3">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>İçerik Adı</th>
                                <th>Kullanıcı Sayısı</th>
                                <th>Tamamlanma Oranı</th>
                                <th>Ortalama Skor</th>
                                <th>Ortalama Süre</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activities as $activity)
                            <tr>
                                <td>{{ $activity->name }}</td>
                                <td>{{ $activity->unique_users }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                            <div class="progress-bar 
                                                @if($activity->completion_rate >= 70) progress-bar-success 
                                                @elseif($activity->completion_rate >= 40) progress-bar-warning 
                                                @else progress-bar-danger @endif" 
                                                role="progressbar" style="width: {{ $activity->completion_rate }}%" 
                                                aria-valuenow="{{ $activity->completion_rate }}" aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                        <span>{{ number_format($activity->completion_rate, 1) }}%</span>
                                    </div>
                                </td>
                                <td>
                                    @if($activity->avg_score !== null)
                                        {{ number_format($activity->avg_score, 1) }}%
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($activity->avg_time !== null)
                                        @php
                                            $hours = floor($activity->avg_time / 3600);
                                            $minutes = floor(($activity->avg_time % 3600) / 60);
                                            $seconds = $activity->avg_time % 60;
                                            
                                            $timeStr = '';
                                            if ($hours > 0) {
                                                $timeStr .= $hours . ' saat ';
                                            }
                                            if ($minutes > 0 || $hours > 0) {
                                                $timeStr .= $minutes . ' dk ';
                                            }
                                            $timeStr .= $seconds . ' sn';
                                            
                                            echo $timeStr;
                                        @endphp
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('activity.details', $activity->id) }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-bar-chart"></i> Detaylı Rapor
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Henüz xAPI verisi bulunan içerik yok.
                </div>
            @endif
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
