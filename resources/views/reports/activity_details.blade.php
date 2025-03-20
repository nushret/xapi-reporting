<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $activity->name }} - İçerik Raporu</title>
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
                        <a class="nav-link active" href="{{ route('dashboard.activities') }}">Raporlar</a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>{{ $activity->name }}</h1>
            <a href="{{ route('dashboard.activities') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> İçerik Listesine Dön
            </a>
        </div>
        
        @if($activity->description)
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> {{ $activity->description }}
        </div>
        @endif
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Toplam Kullanıcı</h5>
                        <p class="card-text display-4">{{ count($userProgress) }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Tamamlama Oranı</h5>
                        <p class="card-text display-4">
                            @php
                                $completedCount = collect($userProgress)->where('completed', true)->count();
                                $completionRate = count($userProgress) > 0 ? ($completedCount / count($userProgress) * 100) : 0;
                                echo number_format($completionRate, 1) . '%';
                            @endphp
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Ortalama Skor</h5>
                        <p class="card-text display-4">
                            @php
                                $scores = collect($userProgress)->filter(function($user) {
                                    return $user['score'] !== null;
                                })->pluck('score');
                                
                                $avgScore = $scores->isNotEmpty() ? $scores->avg() : null;
                                echo $avgScore !== null ? number_format($avgScore, 1) . '%' : '-';
                            @endphp
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-5">
            <h2>Kullanıcı İlerleme Durumu</h2>
            
            @if(count($userProgress) > 0)
                <div class="table-responsive mt-3">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>Durum</th>
                                <th>Skor</th>
                                <th>Süre</th>
                                <th>Etkileşim</th>
                                <th>Son Aktivite</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($userProgress as $user)
                            <tr>
                                <td>{{ $user['name'] }}</td>
                                <td>
                                    @if($user['completed'])
                                        <span class="badge bg-success">Tamamlandı</span>
                                    @else
                                        <span class="badge bg-warning">Devam Ediyor</span>
                                    @endif
                                    
                                    @if($user['passed'])
                                        <span class="badge bg-primary">Başarılı</span>
                                    @endif
                                </td>
                                <td>
                                    @if($user['score'] !== null)
                                        {{ number_format($user['score'], 1) }}%
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $hours = floor($user['duration'] / 3600);
                                        $minutes = floor(($user['duration'] % 3600) / 60);
                                        $seconds = $user['duration'] % 60;
                                        
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
                                </td>
                                <td>{{ $user['interaction_count'] }}</td>
                                <td>
                                    @if($user['last_activity'])
                                        {{ \Carbon\Carbon::parse($user['last_activity'])->format('d.m.Y H:i') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('user.activity.details', ['userId' => $user['id'], 'activityId' => $activity->id]) }}" class="btn btn-sm btn-primary">
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
                    <i class="bi bi-info-circle"></i> Bu içerik için henüz kullanıcı verisi bulunmuyor.
                </div>
            @endif
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
