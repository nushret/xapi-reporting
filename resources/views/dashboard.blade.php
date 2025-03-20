<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xAPI Raporlama Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .accordion-button:not(.collapsed) {
            background-color: #f8f9fa;
            color: #0d6efd;
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
            <h2>Aktiviteler</h2>
            <div class="accordion" id="activitiesAccordion">
                @foreach($activities as $index => $activity)
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading{{ $activity->id }}">
                        <button class="accordion-button {{ $index > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $activity->id }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="collapse{{ $activity->id }}">
                            <strong>{{ $activity->name }}</strong> 
                            <span class="badge bg-primary ms-2">{{ $activity->statements_count }} ifade</span>
                        </button>
                    </h2>
                    <div id="collapse{{ $activity->id }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="heading{{ $activity->id }}" data-bs-parent="#activitiesAccordion">
                        <div class="accordion-body">
                            <div class="d-flex justify-content-between mb-3">
                                <p><strong>Aktivite ID:</strong> {{ $activity->activity_id }}</p>
                                <a href="{{ route('activity.details', $activity->id) }}" class="btn btn-sm btn-primary">Detaylı Rapor</a>
                            </div>
                            
                            @if($activity->description)
                            <p><strong>Açıklama:</strong> {{ $activity->description }}</p>
                            @endif
                            
                            <p><strong>Tür:</strong> {{ $activity->type ?? 'Belirtilmemiş' }}</p>
                            
                            @php
                                $activityStatements = \App\Models\Statement::where('activity_id', $activity->id)->get();
                                $completedCount = $activityStatements->filter(function($s) {
                                    return $s->verb === 'http://adlnet.gov/expapi/verbs/completed';
                                })->count();
                                
                                $uniqueUsers = $activityStatements->pluck('actor_id')->unique()->count();
                                $completionRate = $uniqueUsers > 0 ? ($completedCount / $uniqueUsers) * 100 : 0;
                            @endphp
                            
                            <div class="mt-3">
                                <p><strong>Tamamlanma Oranı:</strong> {{ number_format($completionRate, 1) }}%</p>
                                <div class="progress">
                                    <div class="progress-bar 
                                        @if($completionRate >= 70) progress-bar-success 
                                        @elseif($completionRate >= 40) progress-bar-warning 
                                        @else progress-bar-danger @endif" 
                                        role="progressbar" style="width: {{ $completionRate }}%" 
                                        aria-valuenow="{{ $completionRate }}" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <p><strong>Son Aktiviteler:</strong></p>
                                <ul class="list-group">
                                    @foreach($activityStatements->sortByDesc('timestamp')->take(3) as $statement)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        {{ $statement->actor->name }} - {{ $statement->verb }}
                                        <span class="badge bg-secondary">{{ $statement->timestamp->format('d.m.Y H:i') }}</span>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        
        <div class="mt-5">
            <h2>Son İfadeler</h2>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Kullanıcı</th>
                        <th>Aktivite</th>
                        <th>Eylem</th>
                        <th>Zaman</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentStatements as $statement)
                    <tr>
                        <td>{{ $statement->actor->name }}</td>
                        <td>{{ $statement->activity->name }}</td>
                        <td>{{ $statement->verb }}</td>
                        <td>{{ $statement->timestamp->format('d.m.Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 mb-5">
            <a href="{{ route('content.index') }}" class="btn btn-primary">İçerik Yönetimi</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
