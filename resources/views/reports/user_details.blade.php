<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Raporu - {{ $user->name }}</title>
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
        .sub-activities {
            margin-left: 2rem;
            border-left: 2px solid #dee2e6;
            padding-left: 1rem;
        }
        .activity-toggle {
            cursor: pointer;
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Kullanıcı Raporu: {{ $user->name }}</h1>
            <a href="{{ route('dashboard.reports') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Kullanıcı Listesine Dön
            </a>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Toplam İfade</h5>
                        <p class="card-text display-4">{{ $totalStatements }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Toplam İçerik</h5>
                        <p class="card-text display-4">{{ $totalActivities }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Tamamlanan İçerik</h5>
                        <p class="card-text display-4">{{ $completedCount }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Ortalama Skor</h5>
                        <p class="card-text display-4">
                            @if($avgScore !== null)
                                {{ number_format($avgScore, 1) }}%
                            @else
                                -
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-5">
            <h2>İçerik İlerleme Durumu</h2>
            
            @if(count($courseProgress) > 0)
                <div class="accordion mt-3" id="courseAccordion">
                    @foreach($courseProgress as $index => $course)
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="activity-toggle" data-bs-toggle="collapse" data-bs-target="#course{{ $index }}">
                                <i class="bi bi-chevron-down me-2"></i>
                                <strong>{{ $course['name'] }}</strong>
                                
                                @if($course['completed'])
                                    <span class="badge bg-success ms-2">Tamamlandı</span>
                                @else
                                    <span class="badge bg-warning ms-2">Devam Ediyor</span>
                                @endif
                                
                                @if($course['passed'])
                                    <span class="badge bg-primary ms-2">Başarılı</span>
                                @endif
                            </div>
                            <a href="{{ route('user.activity.details', ['userId' => $user->id, 'activityId' => $course['id']]) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-bar-chart"></i> Detaylı Rapor
                            </a>
                        </div>
                        <div id="course{{ $index }}" class="collapse" data-bs-parent="#courseAccordion">
                            <div class="card-body">
                                @if($course['description'])
                                    <p>{{ $course['description'] }}</p>
                                @endif
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <strong>Skor:</strong> 
                                        @if($course['score'] !== null)
                                            {{ number_format($course['score'], 1) }}%
                                        @else
                                            -
                                        @endif
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Süre:</strong> 
                                        @php
                                            $hours = floor($course['duration'] / 3600);
                                            $minutes = floor(($course['duration'] % 3600) / 60);
                                            $seconds = $course['duration'] % 60;
                                            
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
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Son Aktivite:</strong> 
                                        @if($course['last_activity'])
                                            {{ \Carbon\Carbon::parse($course['last_activity'])->format('d.m.Y H:i') }}
                                        @else
                                            -
                                        @endif
                                    </div>
                                </div>
                                
                                @if(count($course['sub_activities']) > 0)
                                    <h5 class="mt-3">Alt Aktiviteler</h5>
                                    <div class="sub-activities">
                                        <ul class="list-group">
                                            @foreach($course['sub_activities'] as $subActivity)
                                                <li class="list-group-item">
                                                    <strong>{{ $subActivity['name'] }}</strong>
                                                    @if($subActivity['type'])
                                                        <span class="badge bg-secondary ms-2">{{ basename($subActivity['type']) }}</span>
                                                    @endif
                                                    @if($subActivity['description'])
                                                        <p class="mb-0 small text-muted">{{ $subActivity['description'] }}</p>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Bu kullanıcı henüz hiçbir içerikle etkileşimde bulunmamış.
                </div>
            @endif
        </div>
        
        <div class="mt-5">
            <h2>Son Aktiviteler</h2>
            
            @if(count($statements) > 0)
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>İçerik</th>
                                <th>Eylem</th>
                                <th>Sonuç</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($statements->take(20) as $statement)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($statement->timestamp)->format('d.m.Y H:i:s') }}</td>
                                <td>{{ $statement->activity->name }}</td>
                                <td>
                                    @php
                                        $verb = $statement->verb;
                                        $verbText = '';
                                        
                                        if (strpos($verb, 'completed') !== false) {
                                            $verbText = 'Tamamladı';
                                        } elseif (strpos($verb, 'passed') !== false) {
                                            $verbText = 'Başarılı Oldu';
                                        } elseif (strpos($verb, 'failed') !== false) {
                                            $verbText = 'Başarısız Oldu';
                                        } elseif (strpos($verb, 'answered') !== false) {
                                            $verbText = 'Cevapladı';
                                        } elseif (strpos($verb, 'attempted') !== false) {
                                            $verbText = 'Denedi';
                                        } elseif (strpos($verb, 'experienced') !== false) {
                                            $verbText = 'Deneyimledi';
                                        } elseif (strpos($verb, 'interacted') !== false) {
                                            $verbText = 'Etkileşimde Bulundu';
                                        } elseif (strpos($verb, 'launched') !== false) {
                                            $verbText = 'Başlattı';
                                        } elseif (strpos($verb, 'initialized') !== false) {
                                            $verbText = 'Başlattı';
                                        } elseif (strpos($verb, 'terminated') !== false) {
                                            $verbText = 'Sonlandırdı';
                                        } else {
                                            $verbText = $verb;
                                        }
                                        
                                        echo $verbText;
                                    @endphp
                                </td>
                                <td>
                                    @if(isset($statement->result) && isset($statement->result['score']) && isset($statement->result['score']['scaled']))
                                        Skor: {{ number_format($statement->result['score']['scaled'] * 100, 1) }}%
                                    @elseif(isset($statement->result) && isset($statement->result['success']))
                                        {{ $statement->result['success'] ? 'Başarılı' : 'Başarısız' }}
                                    @elseif(isset($statement->result) && isset($statement->result['completion']))
                                        {{ $statement->result['completion'] ? 'Tamamlandı' : 'Tamamlanmadı' }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Bu kullanıcı için henüz aktivite kaydı bulunmuyor.
                </div>
            @endif
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
