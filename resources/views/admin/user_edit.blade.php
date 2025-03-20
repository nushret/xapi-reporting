<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Düzenle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">xAPI Yönetim Paneli</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('admin.dashboard') }}">Kullanıcılar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('content.index') }}">İçerik Yönetimi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard') }}">Raporlar</a>
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

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Kullanıcı Düzenle: {{ $user->full_name }}</h1>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Geri Dön
            </a>
        </div>
        
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.users.update', $user->id) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">Ad</label>
                            <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required>
                            @error('first_name')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Soyad</label>
                            <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required>
                            @error('last_name')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta Adresi</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                        @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Şifre (Değiştirmek istemiyorsanız boş bırakın)</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password">
                            @error('password')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">Şifre Tekrar</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Erişim İzni Verilecek İçerikler</label>
                        <div class="card">
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                @if(count($contents) > 0)
                                    @foreach($contents as $content)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="contents[]" value="{{ $content['path'] }}" id="content_{{ $loop->index }}" {{ in_array($content['path'], $userContentPaths) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="content_{{ $loop->index }}">
                                            {{ $content['name'] }}
                                        </label>
                                    </div>
                                    @endforeach
                                @else
                                    <p class="text-muted mb-0">Henüz içerik bulunmamaktadır. Önce içerik yükleyin.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Kullanıcıyı Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
