<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $user->name }} - {{ $activity->name }} Detayları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>{{ $user->name }} - {{ $activity->name }}</h1>
            <a href="{{ route('activity.details', $activity->id) }}" class="btn btn-secondary">Aktiviteye Dön</a>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Kullanıcı Bilgileri</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Kullanıcı Adı:</strong> {{ $user->name }}</p>
                        <p><strong>E-posta:</strong> {{ $user->mbox }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Toplam İfade Sayısı:</strong> {{ $statements->count() }}</p>
                        <p><strong>İlk Aktivite:</strong> {{ $statements->last()->timestamp->format('d.m.Y H:i') }}</p>
                        <p><strong>Son Aktivite:</strong> {{ $statements->first()->timestamp->format('d.m.Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">İlerleme Özeti</h5>
                    </div>
                    <div class="card-body">
                        @php
                            $completed = $statements->contains('verb', 'http://adlnet.gov/expapi/verbs/completed');
                            $passed = $statements->contains('verb', 'http://adlnet.gov/expapi/verbs/passed');
                            
                            // Skor bilgisini bul
                            $scoreStatement = $statements
                                ->sortByDesc('timestamp')
                                ->first(function ($statement) {
                                    return isset($statement->result) && 
                                           isset($statement->result['score']) && 
                                           isset($statement->result['score']['scaled']);
                                });
                            
                            $score = $scoreStatement ? $scoreStatement->result['score']['scaled'] * 100 : null;
                            
                            // Toplam harcanan süreyi hesapla
                            $duration = 0;
                            foreach ($statements as $statement) {
                                if (isset($statement->result) && isset($statement->result['duration'])) {
                                    // ISO 8601 süre formatını saniyeye çevir
                                    $durationStr = $statement->result['duration'];
                                    preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $durationStr, $matches);
                                    
                                    $hours = isset($matches[1]) ? (int)$matches[1] : 0;
                                    $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
                                    $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
                                    
                                    $duration += $hours * 3600 + $minutes * 60 + $seconds;
                                }
                            }
                        @endphp
                        
                        <div class="mb-3">
                            <p><strong>Durum:</strong> 
                                @if($completed)
                                    <span class="badge bg-success">Tamamlandı</span>
                                @else
                                    <span class="badge bg-warning">Devam Ediyor</span>
                                @endif
                            </p>
                            
                            <p><strong>Sonuç:</strong> 
                                @if($passed)
                                    <span class="badge bg-success">Başarılı</span>
                                @elseif($statements->contains('verb', 'http://adlnet.gov/expapi/verbs/failed'))
                                    <span class="badge bg-danger">Başarısız</span>
                                @else
                                    <span class="badge bg-secondary">Belirtilmemiş</span>
                                @endif
                            </p>
                        </div>
                        
                        @if($score !== null)
                        <div class="mb-3">
                            <p class="mb-1"><strong>Skor:</strong> {{ number_format($score, 1) }}%</p>
                            <div class="progress">
                                <div class="progress-bar 
                                    @if($score >= 70) bg-success 
                                    @elseif($score >= 40) bg-warning 
                                    @else bg-danger @endif" 
                                    role="progressbar" style="width: {{ $score }}%" 
                                    aria-valuenow="{{ $score }}" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        @if($duration > 0)
                        <p><strong>Toplam Süre:</strong> 
                            @php
                                $hours = floor($duration / 3600);
                                $minutes = floor(($duration % 3600) / 60);
                                $seconds = $duration % 60;
                                
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
                        
                        <p><strong>Etkileşim Sayısı:</strong> {{ $interactions->count() }}</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Aktivite Zaman Çizelgesi</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @foreach($statements->take(10) as $statement)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
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
                                            
                                            $verbDisplay = $verbMap[$verbName] ?? $verbName;
                                        @endphp
                                        <strong>{{ $verbDisplay }}</strong>
                                        
                                        @if(isset($statement->result) && isset($statement->result['success']))
                                            @if($statement->result['success'])
                                                <span class="badge bg-success">Başarılı</span>
                                            @else
                                                <span class="badge bg-danger">Başarısız</span>
                                            @endif
                                        @endif
                                    </div>
                                    <small class="text-muted">{{ $statement->timestamp->format('d.m.Y H:i:s') }}</small>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <h2 class="mt-4">Etkileşim Detayları</h2>
        
        @if($interactions->count() > 0)
        <div class="accordion" id="interactionsAccordion">
            @foreach($interactions as $index => $interaction)
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading{{ $interaction['id'] }}">
                    <button class="accordion-button {{ $index > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $interaction['id'] }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="collapse{{ $interaction['id'] }}">
                        <div class="d-flex justify-content-between w-100 me-3">
                            <span>
                                @php
                                    $verbParts = explode('/', $interaction['verb']);
                                    $verbName = end($verbParts);
                                    $verbDisplay = $verbMap[$verbName] ?? $verbName;
                                @endphp
                                <strong>{{ $verbDisplay }}</strong>
                                
                                @if(isset($interaction['success']))
                                    @if($interaction['success'])
                                        <span class="badge bg-success">Başarılı</span>
                                    @else
                                        <span class="badge bg-danger">Başarısız</span>
                                    @endif
                                @endif
                            </span>
                            <small>{{ \Carbon\Carbon::parse($interaction['timestamp'])->format('d.m.Y H:i:s') }}</small>
                        </div>
                    </button>
                </h2>
                <div id="collapse{{ $interaction['id'] }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="heading{{ $interaction['id'] }}" data-bs-parent="#interactionsAccordion">
                    <div class="accordion-body">
                        @if(isset($interaction['response']))
                        <div class="mb-3">
                            <h6>Kullanıcı Yanıtı:</h6>
                            <div class="p-3 bg-light rounded">
                                {{ $interaction['response'] }}
                            </div>
                        </div>
                        @endif
                        
                        @if(isset($interaction['result']) && !empty($interaction['result']))
                        <div>
                            <h6>Sonuç Detayları:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <tbody>
                                        @foreach($interaction['result'] as $key => $value)
                                            @if($key != 'response' && !is_array($value))
                                            <tr>
                                                <th>{{ ucfirst($key) }}</th>
                                                <td>
                                                    @if($key == 'success')
                                                        @if($value)
                                                            <span class="badge bg-success">Başarılı</span>
                                                        @else
                                                            <span class="badge bg-danger">Başarısız</span>
                                                        @endif
                                                    @elseif($key == 'completion')
                                                        @if($value)
                                                            <span class="badge bg-success">Tamamlandı</span>
                                                        @else
                                                            <span class="badge bg-warning">Tamamlanmadı</span>
                                                        @endif
                                                    @elseif($key == 'duration')
                                                        @php
                                                            preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $value, $matches);
                                                            
                                                            $hours = isset($matches[1]) ? (int)$matches[1] : 0;
                                                            $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
                                                            $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
                                                            
                                                            $durationStr = '';
                                                            if ($hours > 0) {
                                                                $durationStr .= $hours . ' saat ';
                                                            }
                                                            if ($minutes > 0 || $hours > 0) {
                                                                $durationStr .= $minutes . ' dakika ';
                                                            }
                                                            $durationStr .= $seconds . ' saniye';
                                                            
                                                            echo $durationStr;
                                                        @endphp
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </td>
                                            </tr>
                                            @endif
                                        @endforeach
                                        
                                        @if(isset($interaction['result']['score']))
                                        <tr>
                                            <th>Skor</th>
                                            <td>
                                                <table class="table table-sm mb-0">
                                                    @foreach($interaction['result']['score'] as $scoreKey => $scoreValue)
                                                    <tr>
                                                        <th>{{ ucfirst($scoreKey) }}</th>
                                                        <td>
                                                            @if($scoreKey == 'scaled')
                                                                {{ number_format($scoreValue * 100, 1) }}%
                                                            @else
                                                                {{ $scoreValue }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </table>
                                            </td>
                                        </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="alert alert-info">
            Bu kullanıcı için henüz etkileşim kaydı bulunmamaktadır.
        </div>
        @endif
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
