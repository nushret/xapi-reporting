<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        $contents = $user->contents;
        
        return view('user.dashboard', compact('user', 'contents'));
    }
    
    public function launchContent($contentPath)
    {
        $user = Auth::user();
        
        // Kullanıcının bu içeriğe erişim izni var mı kontrol et
        $hasAccess = $user->contents()->where('path', $contentPath)->exists();
        
        if (!$hasAccess) {
            return redirect()->route('user.dashboard')->with('error', 'Bu içeriğe erişim izniniz yok.');
        }
        
        // İçerik yolundan aktiviteyi bul
        $activity = Activity::where('content_path', $contentPath)->first();
        
        if (!$activity) {
            return redirect()->route('user.dashboard')->with('error', 'İçerik bulunamadı.');
        }
        
        // Launch URL'ini belirle
        $launchUrl = $activity->launch_url;
        $contentBasePath = storage_path('app/public/xapi-content/' . $contentPath);
        $subDir = null;
        
        if (!$launchUrl) {
            // Varsayılan olarak index_lms.html veya story.html kullan
            if (file_exists($contentBasePath . '/index_lms.html')) {
                $launchUrl = 'index_lms.html';
            } elseif (file_exists($contentBasePath . '/story.html')) {
                $launchUrl = 'story.html';
            } else {
                // Alt dizinlerde ara
                $directories = glob($contentBasePath . '/*', GLOB_ONLYDIR);
                
                foreach ($directories as $dir) {
                    $dirName = basename($dir);
                    
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
                return redirect()->route('user.dashboard')->with('error', 'Başlatılabilir dosya bulunamadı.');
            }
            
            // Bulunan launch URL'ini kaydet
            $activity->launch_url = $subDir ? $subDir . '/' . $launchUrl : $launchUrl;
            $activity->save();
        }
        
        // Kullanıcı bilgilerini al
        $actor = json_encode([
            'name' => $user->full_name,
            'mbox' => 'mailto:' . $user->email
        ]);
        
        // Rastgele bir registration ID oluştur
        $registrationId = Str::uuid()->toString();
        
        // Özel bir route kullanarak içerik dosyalarına erişim sağla
        $fullLaunchUrl = $subDir 
            ? url('content-serve/' . $contentPath . '/' . $subDir . '/' . $launchUrl)
            : url('content-serve/' . $contentPath . '/' . $launchUrl);
        
        $endpoint = url('api/statements');
        $auth = base64_encode('xapi_user:xapi_password');
Log::info('Launch URL parametreleri', [
    'endpoint' => $endpoint,
    'auth' => $auth,
    'actor' => $actor,
    'activity_id' => $activity->activity_id,
    'registration' => $registrationId
]);
        
        $finalLaunchUrl = $fullLaunchUrl . '?endpoint=' . urlencode($endpoint) . 
                     '&auth=' . urlencode($auth) . 
                     '&actor=' . urlencode($actor) . 
                     '&activity_id=' . urlencode($activity->activity_id) . 
                     '&registration=' . urlencode($registrationId);
        
        return redirect($finalLaunchUrl);
    }
}
