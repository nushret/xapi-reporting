<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Support\Str;
use App\Models\Activity;
use Illuminate\Support\Facades\Log;

class ContentController extends Controller
{
    public function index()
    {
        // Yüklenen içerikleri listele
        $contents = [];
        $directories = Storage::disk('public')->directories('xapi-content');
        
        foreach ($directories as $directory) {
            $contentName = basename($directory);
            $activity = Activity::where('content_path', $contentName)->first();
            
            if ($activity) {
                $contents[] = [
                    'id' => $activity->activity_id,
                    'name' => $activity->name,
                    'path' => $contentName
                ];
            } else {
                $contents[] = [
                    'id' => $contentName,
                    'name' => $contentName,
                    'path' => $contentName
                ];
            }
        }
        
        return view('content.index', compact('contents'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'content_file' => 'required|file|mimes:zip',
            'title' => 'required|string|max:255'
        ]);

        $zipFile = $request->file('content_file');
        $title = Str::slug($request->title);
        $contentPath = 'xapi-content/' . $title;
        
        // Zip dosyasını geçici bir konuma kaydet
        $zipPath = $zipFile->storeAs('temp', $title . '.zip', 'public');
        $zipFullPath = Storage::disk('public')->path($zipPath);
        
        // Zip dosyasını çıkart
        $extractPath = Storage::disk('public')->path($contentPath);
        
        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0755, true);
        }
        
        $zip = new ZipArchive;
        if ($zip->open($zipFullPath) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
            
            // Geçici zip dosyasını sil
            Storage::disk('public')->delete($zipPath);
            
            // tincan.xml dosyasını analiz et
            $this->parseTinCanXml($extractPath, $title);
            
            return redirect()->route('content.index')->with('success', 'İçerik başarıyla yüklendi.');
        } else {
            return redirect()->back()->with('error', 'Zip dosyası açılamadı.');
        }
    }
    
    
    private function parseTinCanXml($extractPath, $contentPath)
{
    // Önce tincan.xml dosyasını bul
    $tincanXmlPath = null;
    
    // Ana dizinde ara
    if (file_exists($extractPath . '/tincan.xml')) {
        $tincanXmlPath = $extractPath . '/tincan.xml';
    } else {
        // Alt dizinlerde ara
        $directories = glob($extractPath . '/*', GLOB_ONLYDIR);
        foreach ($directories as $dir) {
            if (file_exists($dir . '/tincan.xml')) {
                $tincanXmlPath = $dir . '/tincan.xml';
                break;
            }
        }
    }
    
    if (!$tincanXmlPath) {
        Log::warning('tincan.xml dosyası bulunamadı: ' . $extractPath);
        return;
    }
    
    // tincan.xml dosyasını analiz et
    $xml = simplexml_load_file($tincanXmlPath);
    
    if (!$xml) {
        Log::warning('tincan.xml dosyası geçerli bir XML değil: ' . $tincanXmlPath);
        return;
    }
    
    // Kurs aktivitesini bul (launch özelliği olan)
    $launchActivity = null;
    $launchUrl = null;
    $contentName = basename($contentPath); // Varsayılan olarak içerik yolunu kullan
    
    foreach ($xml->activities->activity as $activity) {
        $activityId = (string)$activity['id'];
        $activityType = (string)$activity['type'];
        $name = (string)$activity->name;
        $description = (string)$activity->description;
        
        // İçerik adını al (ilk aktivite veya launch özelliği olan aktivite)
        if (empty($contentName) || isset($activity->launch)) {
            $contentName = $name ?: basename($contentPath);
        }
        
        // Launch özelliği varsa kaydet
        if (isset($activity->launch)) {
            $launchUrl = (string)$activity->launch;
            $launchActivity = $activity;
        }
        
        // Aktiviteyi veritabanına kaydet
        Activity::updateOrCreate(
            ['activity_id' => $activityId],
            [
                'name' => $name ?: $contentName,
                'description' => $description,
                'type' => $activityType,
                'content_path' => basename($contentPath),
                'launch_url' => isset($activity->launch) ? (string)$activity->launch : null
            ]
        );
    }
    
    // İçerik adını Content modeline de kaydet
    Content::updateOrCreate(
        ['path' => basename($contentPath)],
        [
            'name' => $contentName,
            'description' => $description ?? null
        ]
    );
    
    // Eğer launch özelliği olan bir aktivite bulunamadıysa, ilk aktiviteyi kullan
    if (!$launchActivity && isset($xml->activities->activity[0])) {
        $launchActivity = $xml->activities->activity[0];
        $activityId = (string)$launchActivity['id'];
        
        // index_lms.html veya story.html dosyasını varsayılan olarak kullan
        if (file_exists($extractPath . '/index_lms.html')) {
            $launchUrl = 'index_lms.html';
        } elseif (file_exists($extractPath . '/story.html')) {
            $launchUrl = 'story.html';
        }
        
        if ($launchUrl) {
            Activity::where('activity_id', $activityId)->update(['launch_url' => $launchUrl]);
        }
    }
}

    
   

		public function launch($contentPath)
{
    // İçerik yolundan aktiviteyi bul
    $activity = Activity::where('content_path', $contentPath)->first();
    
    if (!$activity) {
        Log::warning('İçerik bulunamadı: ' . $contentPath);
        return redirect()->route('content.index')->with('error', 'İçerik bulunamadı.');
    }
    
    // Dosya yapısını kontrol et
    $contentBasePath = Storage::disk('public')->path('xapi-content/' . $contentPath);
    $launchUrl = null;
    $subDir = null;
    
    // Önce doğrudan index_lms.html dosyasını ara
    if (file_exists($contentBasePath . '/index_lms.html')) {
        $launchUrl = 'index_lms.html';
    } 
    // Sonra story.html dosyasını ara
    elseif (file_exists($contentBasePath . '/story.html')) {
        $launchUrl = 'story.html';
    } 
    // Alt dizinlerde ara
    else {
        // Alt dizinleri listele
        $directories = glob($contentBasePath . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $dirName = basename($dir);
            
            // Alt dizinde index_lms.html veya story.html ara
            if (file_exists($dir . '/index_lms.html')) {
                $subDir = $dirName;
                $launchUrl = 'index_lms.html';
                break;
            } elseif (file_exists($dir . '/story.html')) {
                $subDir = $dirName;
                $launchUrl = 'story.html';
                break;
            }
        }
    }
    
    if (!$launchUrl) {
        Log::error('Başlatılabilir dosya bulunamadı: ' . $contentBasePath);
        return redirect()->route('content.index')->with('error', 'Başlatılabilir dosya bulunamadı.');
    }
    
    // Bulunan launch URL'ini kaydet
    $activity->launch_url = $subDir ? $subDir . '/' . $launchUrl : $launchUrl;
    $activity->save();
    
    // Kullanıcı bilgilerini al (örnek)
    $actor = json_encode([
        'name' => 'Test Kullanıcı',
        'mbox' => 'mailto:test@example.com'
    ]);
    
    // Rastgele bir registration ID oluştur
    $registrationId = Str::uuid()->toString();
    
    // Özel bir route kullanarak içerik dosyalarına erişim sağla
    $fullLaunchUrl = $subDir 
        ? url('content-serve/' . $contentPath . '/' . $subDir . '/' . $launchUrl)
        : url('content-serve/' . $contentPath . '/' . $launchUrl);
    
    $endpoint = url('api/statements');
    $auth = base64_encode('xapi_user:xapi_password');
    
    Log::info('Launch URL oluşturuluyor', [
        'contentPath' => $contentPath,
        'subDir' => $subDir,
        'launchUrl' => $launchUrl,
        'fullLaunchUrl' => $fullLaunchUrl
    ]);
    
    $finalLaunchUrl = $fullLaunchUrl . '?endpoint=' . urlencode($endpoint) . 
                 '&auth=' . urlencode($auth) . 
                 '&actor=' . urlencode($actor) . 
                 '&activity_id=' . urlencode($activity->activity_id) . 
                 '&registration=' . urlencode($registrationId);
    
    return redirect($finalLaunchUrl);
}


}
