<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $activity->name }} - Aktivite Detayları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .user-card {
            transition: all 0.3s ease;
        }
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>{{ $activity->name }}</h1>
            <a href="{{ route('dashboard.reports') }}" class="btn btn-secondary">Panele Dön</a>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Aktivite Bilgileri</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Aktivite ID:</strong> {{ $activity->activity_id }}</p>
                        @if($activity->description)
                        <p><strong>Açıklama:</strong> {{ $activity->description }}</p>
                        @endif
                        <p><strong>Tür:</strong> {{ $activity->type ?? 'Belirtilmemiş' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Toplam İfade Sayısı:</strong> {{ $statements->count() }}</p>
                        <p><strong>Toplam Kullanıcı Sayısı:</strong> {{ $userProgress->count() }}</p>
                        <p><strong>Tamamlayan Kullanıcı Sayısı:</strong> {{ $userProgress->where('completed', true)->count() }}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <h2>Kullanıcı İlerlemeleri</h2>
        <div class="row">
            @foreach($userProgress as $user)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card user-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ $user['name'] }}</h5>
                        <span class="badge {{ $user['completed'] ? 'bg-success' : 'bg-warning' }}">
                            {{ $user['completed'] ? 'Tamamlandı' : 'Devam Ediyor' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">{{ $user['email'] }}</p>
                        
                        @if($user['score'] !== null)
                        <div class="mb-3">
                            <p class="mb-1"><strong>Skor:</strong> {{ number_format($user['score'], 1) }}%</p>
                            <div class="progress">
                                <div class="progress-bar 
                                    @if($user['score'] >= 70) progress-bar-success 
                                    @elseif($user['score'] >= 40) progress-bar-warning 
                                    @else progress-bar-danger @endif" 
                                    role="progressbar" style="width: {{ $user['score'] }}%" 
                                    aria-valuenow="{{ $user['score'] }}" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <p><strong>Etkileşim Sayısı:</strong> {{ $user['interaction_count'] }}</p>
                        
                        @if($user['duration'] > 0)
                        <p><strong>Toplam Süre:</strong> 
                            @php
                                $hours = floor($user['duration'] / 3600);
                                $minutes = floor(($user['duration'] % 3600) / 60);
                                $seconds = $user['duration'] % 60;
                                
                                if ($hours > 0) {
                                    echo $hours . ' saat ';
                                }
                                if ($minutes > 0 || $hours > 0) {
                                    echo $minutes . ' dakika ';
                                }
                                echo $seconds . ' saniye';
                            @endphp
                        </p>
                        @endif
                        
                        <p><strong>Son Aktivite:</strong> {{ \Carbon\Carbon::parse($user['last_activity'])->format('d.m.Y H:i') }}</p>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('user.activity.details', [$activity->id, $user['id']]) }}" class="btn btn-primary btn-sm w-100">Detaylı Rapor</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        
        <h2 class="mt-5">Son Aktiviteler</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Kullanıcı</th>
                        <th>Eylem</th>
                        <th>Sonuç</th>
                        <th>Zaman</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($statements->take(20) as $statement)
                    <tr>
                        <td>{{ $statement->actor->name }}</td>
                        <td>
                            @php
                                $verbParts = explode('/', $statement->verb);
                                $verbName = end($verbParts);
                                
                                $verbMap = [
                                    'completed' => 'Tamamladı',
                                    'passed' => 'Geçti',
                                    'failed' => 'Başarısız Oldu',
                                    'answered' => 'Cevapladı',
                                    'interacted' => 'Etkileşimde Bulundu',
                                    'experienced' => 'Deneyimledi',
                                    'attempted' => 'Denedi',
                                    'initialized' => 'Başlattı',
                                    'terminated' => 'Sonlandırdı'
                                ];
                                
                                echo $verbMap[$verbName] ?? $verbName;
                            @endphp
                        </td>
                        <td>
                            @if(isset($statement->result) && isset($statement->result['success']))
                                @if($statement->result['success'])
                                    <span class="badge bg-success">Başarılı</span>
                                @else
                                    <span class="badge bg-danger">Başarısız</span>
                                @endif
                            @elseif(isset($statement->result) && isset($statement->result['score']) && isset($statement->result['score']['scaled']))
                                <span class="badge bg-info">{{ number_format($statement->result['score']['scaled'] * 100, 1) }}%</span>
                            @else
                                <span class="badge bg-secondary">-</span>
                            @endif
                        </td>
                        <td>{{ $statement->timestamp->format('d.m.Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
