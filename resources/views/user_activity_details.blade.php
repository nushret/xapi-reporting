<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $user->name }} - {{ $activity->name }} Raporu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
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
            <h1>{{ $user->name }} - {{ $activity->name }}</h1>
            <a href="{{ route('activity.details', $activity->id) }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> İçerik Raporuna Dön
            </a>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Durum</h5>
                        <p class="card-text">
                            @if($completed)
                                <span class="badge bg-success">Tamamlandı</span>
                            @else
                                <span class="badge bg-warning">Devam Ediyor</span>
                            @endif
                            
                            @if($passed)
                                <span class="badge bg-primary">Başarılı</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Skor</h5>
                        <p class="card-text">
                            @if($score !== null)
                                {{ number_format($score, 1) }}%
                            @else
                                -
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Toplam Süre</h5>
                        <p class="card-text">
                            @php
                                $hours = floor($duration / 3600);
                                $minutes = floor(($duration % 3600) / 60);
                                $seconds = $duration % 60;
                                
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
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Son Aktivite</h5>
                        <p class="card-text">
                            @if($lastActivity)
                                {{ \Carbon\Carbon::parse($lastActivity)->format('d.m.Y H:i') }}
                            @else
                                -
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-5">
            <h2>Etkileşimler</h2>
            
            @if(count($interactions) > 0)
                <div class="table-responsive mt-3">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Soru/Etkileşim</th>
                                <th>Cevap</th>
                                <th>Sonuç</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($interactions as $interaction)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($interaction['timestamp'])->format('d.m.Y H:i:s') }}</td>
                                <td>
                                    @if(isset($interaction['result']['description']))
                                        {{ $interaction['result']['description'] }}
                                    @else
                                        Etkileşim
                                    @endif
                                </td>
                                <td>{{ $interaction['response'] ?? '-' }}</td>
                                <td>
                                    @if($interaction['success'] === true)
                                        <span class="badge bg-success">Doğru</span>
                                    @elseif($interaction['success'] === false)
                                        <span class="badge bg-danger">Yanlış</span>
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
                    <i class="bi bi-info-circle"></i> Bu kullanıcı için henüz etkileşim kaydı bulunmuyor.
                </div>
            @endif
        </div>
        
        <div class="mt-5">
            <h2>Tüm Aktiviteler</h2>
            
            @if(count($statements) > 0)
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Eylem</th>
                                <th>Sonuç</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($statements as $statement)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($statement->timestamp)->format('d.m.Y H:i:s') }}</td>
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