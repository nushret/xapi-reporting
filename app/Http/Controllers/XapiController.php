<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Actor;
use App\Models\Statement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class XapiController extends Controller
{
    public function dashboard()
    {
        $totalStatements = Statement::count();
        $totalActors = Actor::count();
        $totalActivities = Activity::count();

        // Son 10 statement'ı al
        $recentStatements = Statement::with(['actor', 'activity'])
            ->latest('timestamp')
            ->take(10)
            ->get();

        // Aktiviteleri al
        $activities = Activity::withCount('statements')
            ->orderBy('statements_count', 'desc')
            ->take(10)
            ->get();

        return view('dashboard', compact(
            'totalStatements',
            'totalActors',
            'totalActivities',
            'recentStatements',
            'activities'
        ));
    }
    public function activityDetails($id)
    {
        $activity = Activity::findOrFail($id);

        // Aktiviteye ait tüm statement'ları al
        $statements = Statement::with('actor')
            ->where('activity_id', $id)
            ->latest('timestamp')
            ->get();

        // Kullanıcıları ve ilerleme durumlarını al
        $userProgress = DB::table('statements')
            ->join('actors', 'statements.actor_id', '=', 'actors.id')
            ->where('statements.activity_id', $id)
            ->select('actors.id', 'actors.name', 'actors.mbox')
            ->distinct()
            ->get()
            ->map(function ($user) use ($id) {
                // Her kullanıcı için ilerleme durumunu hesapla
                $userStatements = Statement::where('actor_id', $user->id)
                    ->where('activity_id', $id)
                    ->get();

                $completed = $userStatements->contains(function ($statement) {
                    return strpos($statement->verb, 'completed') !== false;
                });
                
                $passed = $userStatements->contains(function ($statement) {
                    return strpos($statement->verb, 'passed') !== false;
                });

                // Skor bilgisini bul
                $scoreStatement = $userStatements
                    ->sortByDesc('timestamp')
                    ->first(function ($statement) {
                        return isset($statement->result) &&
                                isset($statement->result['score']) &&
                                isset($statement->result['score']['scaled']);
                    });

                $score = $scoreStatement ? $scoreStatement->result['score']['scaled'] * 100 : null;

                // Toplam harcanan süreyi hesapla (varsa)
                $duration = 0;
                foreach ($userStatements as $statement) {
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

                // Etkileşim sayısını hesapla
                $interactionCount = $userStatements
                    ->filter(function ($statement) {
                        return strpos($statement->verb, 'answered') !== false ||
                                strpos($statement->verb, 'interacted') !== false;
                    })
                    ->count();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->mbox,
                    'completed' => $completed,
                    'passed' => $passed,
                    'score' => $score,
                    'duration' => $duration,
                    'interaction_count' => $interactionCount,
                    'last_activity' => $userStatements->max('timestamp'),
                    'statements_count' => $userStatements->count()
                ];
            });

        return view('reports.activity_details', compact('activity', 'statements', 'userProgress'));
    }
    public function userActivityDetails($userId, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $user = Actor::findOrFail($userId);

        // Kullanıcının bu aktivitedeki tüm statement'larını al
        $statements = Statement::where('activity_id', $activityId)
            ->where('actor_id', $userId)
            ->orderBy('timestamp', 'desc')
            ->get();

        // Etkileşimleri grupla
        $interactions = $statements->filter(function ($statement) {
            return strpos($statement->verb, 'answered') !== false;
        })->map(function ($statement) {
            $result = $statement->result ?? null;
            $success = $result && isset($result['success']) ? $result['success'] : null;
            $response = $result && isset($result['response']) ? $result['response'] : null;

            return [
                'id' => $statement->id,
                'timestamp' => $statement->timestamp,
                'verb' => $statement->verb,
                'success' => $success,
                'response' => $response,
                'result' => $result
            ];
        });

        // Kullanıcının bu aktivitedeki ilerleme durumunu hesapla
        $completed = $statements->contains(function ($statement) {
            return strpos($statement->verb, 'completed') !== false;
        });
    
        $passed = $statements->contains(function ($statement) {
            return strpos($statement->verb, 'passed') !== false;
        });

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

        // Son aktivite zamanı
        $lastActivity = $statements->isNotEmpty() ? $statements->first()->timestamp : null;

        return view('user_activity_details', compact(
            'activity', 
            'user', 
            'statements', 
            'interactions', 
            'completed', 
            'passed', 
            'score', 
            'duration', 
            'lastActivity'
        ));
    }
    public function statements(Request $request)
    {
        Log::info('xAPI isteği alındı', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'content' => $request->getContent()
        ]);

        if ($request->isMethod('post')) {
            return $this->storeStatement($request);
        }

        return $this->getStatements($request);
    }

    private function storeStatement(Request $request)
    {
        try {
            $data = $request->json()->all();
            Log::info('xAPI Statement verisi', ['data' => $data]);
            
            // Tek bir ifade veya ifade dizisi olabilir
            $statements = is_array($data) && isset($data[0]) ? $data : [$data];
            
            $savedIds = [];
            
            foreach ($statements as $statementData) {
                // Statement ID kontrolü
                $statementId = $statementData['id'] ?? Str::uuid()->toString();
                
                // Aynı ID ile kayıt var mı kontrol et
                $existingStatement = Statement::where('statement_id', $statementId)->first();
                if ($existingStatement) {
                    $savedIds[] = $statementId;
                    continue;
                }
                
                // Actor bilgilerini işle
                $actor = $this->processActor($statementData['actor']);
                
                // Activity bilgilerini işle
                $activity = $this->processActivity($statementData['object']);
                
                if (!$actor || !$activity) {
                    Log::warning('Actor veya Activity oluşturulamadı', [
                        'actor_data' => $statementData['actor'] ?? null,
                        'object_data' => $statementData['object'] ?? null
                    ]);
                    continue;
                }
                
                // Statement oluştur
                $statement = Statement::create([
                    'statement_id' => $statementId,
                    'actor_id' => $actor->id,
                    'activity_id' => $activity->id,
                    'verb' => $statementData['verb']['id'],
                    'result' => $statementData['result'] ?? null,
                    'context' => $statementData['context'] ?? null,
                    'timestamp' => $statementData['timestamp'] ?? now(),
                ]);
                
                Log::info('Statement kaydedildi', ['statement_id' => $statement->statement_id]);
                $savedIds[] = $statement->statement_id;
            }
            
            return response()->json($savedIds, 200);
        } catch (\Exception $e) {
            Log::error('Statement kaydedilirken hata oluştu', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function processActor($actorData)
    {
        $mbox = $actorData['mbox'] ?? '';
        
        $actor = Actor::firstOrCreate(
            ['mbox' => $mbox],
            ['name' => $actorData['name'] ?? 'Anonim']
        );
        
        return $actor;
    }

    private function processActivity($objectData)
{
    if (($objectData['objectType'] ?? 'Activity') !== 'Activity') {
        // Şimdilik sadece Activity tipini destekliyoruz
        return null;
    }
    
    $activityId = $objectData['id'];
    
    // Aktivite adını bulmak için daha esnek bir yaklaşım
    $name = 'Bilinmeyen Aktivite';
    if (isset($objectData['definition']) && isset($objectData['definition']['name'])) {
        $nameObj = $objectData['definition']['name'];
        // İlk olarak Türkçe adı kontrol et
        if (isset($nameObj['tr-TR'])) {
            $name = $nameObj['tr-TR'];
        }
        // Sonra İngilizce adı kontrol et
        elseif (isset($nameObj['en-US'])) {
            $name = $nameObj['en-US'];
        }
        // Sonra herhangi bir dildeki ilk adı al
        elseif (is_array($nameObj) && count($nameObj) > 0) {
            $name = reset($nameObj);
        }
        // Eğer name bir string ise doğrudan kullan
        elseif (is_string($nameObj)) {
            $name = $nameObj;
        }
    }
    
    // Açıklama için de benzer bir yaklaşım
    $description = null;
    if (isset($objectData['definition']) && isset($objectData['definition']['description'])) {
        $descObj = $objectData['definition']['description'];
        if (isset($descObj['tr-TR'])) {
            $description = $descObj['tr-TR'];
        }
        elseif (isset($descObj['en-US'])) {
            $description = $descObj['en-US'];
        }
        elseif (is_array($descObj) && count($descObj) > 0) {
            $description = reset($descObj);
        }
        elseif (is_string($descObj)) {
            $description = $descObj;
        }
    }
    
    $activity = Activity::firstOrCreate(
        ['activity_id' => $activityId],
        [
            'name' => $name,
            'description' => $description,
            'type' => $objectData['definition']['type'] ?? null
        ]
    );
    
    // Aktivite adını logla
    \Illuminate\Support\Facades\Log::info('Aktivite işlendi', [
        'activity_id' => $activityId,
        'name' => $name,
        'original_data' => $objectData
    ]);
    
    return $activity;
}


    private function getStatements(Request $request)
    {
        $query = Statement::with(['actor', 'activity']);
        
        // Filtreleme işlemleri burada yapılabilir
        
        $statements = $query->latest('timestamp')->paginate(20);
        
        return response()->json($statements);
    }


	public function activitiesState(Request $request)
{
    Log::info('xAPI activities/state isteği alındı', [
        'method' => $request->method(),
        'params' => $request->all(),
        'content' => $request->getContent()
    ]);
    
    // GET isteği - state'i getir
    if ($request->isMethod('get')) {
        return response()->json(null);
    }
    
    // PUT isteği - state'i kaydet
    if ($request->isMethod('put')) {
        return response('', 204);
    }
    
    // DELETE isteği - state'i sil
    if ($request->isMethod('delete')) {
        return response('', 204);
    }
    
    return response()->json(['error' => 'Method not supported'], 405);
}

public function statementsWithId(Request $request)
{
    Log::info('xAPI statements/statementId isteği alındı', [
        'method' => $request->method(),
        'params' => $request->all(),
        'content' => $request->getContent()
    ]);
    
    // GET isteği - statement'ı getir
    if ($request->isMethod('get')) {
        $statementId = $request->query('statementId');
        $statement = Statement::where('statement_id', $statementId)->first();
        
        if ($statement) {
            return response()->json($statement);
        }
        
        return response()->json(null);
    }
    
    // PUT isteği - statement'ı kaydet
    if ($request->isMethod('put')) {
        $statementId = $request->query('statementId');
        $data = $request->json()->all();
        
        // Actor bilgilerini işle
        $actor = $this->processActor($data['actor']);
        
        // Activity bilgilerini işle
        $activity = $this->processActivity($data['object']);
        
        if (!$actor || !$activity) {
            Log::warning('Actor veya Activity oluşturulamadı', [
                'actor_data' => $data['actor'] ?? null,
                'object_data' => $data['object'] ?? null
            ]);
            return response()->json(['error' => 'Invalid actor or activity'], 400);
        }
        
        // Statement oluştur veya güncelle
        $statement = Statement::updateOrCreate(
            ['statement_id' => $statementId],
            [
                'actor_id' => $actor->id,
                'activity_id' => $activity->id,
                'verb' => $data['verb']['id'],
                'result' => $data['result'] ?? null,
                'context' => $data['context'] ?? null,
                'timestamp' => $data['timestamp'] ?? now(),
            ]
        );
        
        Log::info('Statement kaydedildi', ['statement_id' => $statement->statement_id]);
        
        return response('', 204);
    }
    
    return response()->json(['error' => 'Method not supported'], 405);
}

public function activitiesReport()
{
    $totalStatements = Statement::count();
    $totalActors = Actor::count();
    $totalActivities = Activity::count();
    
    // Aktiviteleri ve ilgili istatistikleri al
    $activities = Activity::select('activities.*')
        ->selectRaw('COUNT(DISTINCT statements.actor_id) as unique_users')
        ->selectRaw('
            (SELECT COUNT(*) FROM statements as s1 
             WHERE s1.activity_id = activities.id 
             AND s1.verb = \'http://adlnet.gov/expapi/verbs/completed\') * 100.0 / 
            NULLIF(COUNT(DISTINCT statements.actor_id), 0) as completion_rate
        ')
        ->leftJoin('statements', 'activities.id', '=', 'statements.activity_id')
        ->where('activities.type', 'http://adlnet.gov/expapi/activities/course')
        ->orWhereNull('activities.type') // Tip belirtilmemiş aktiviteleri de dahil et
        ->groupBy('activities.id')
        ->get()
        ->map(function ($activity) {
            // Ortalama skoru hesapla - PostgreSQL için düzeltilmiş
            $avgScore = DB::table('statements')
                ->where('activity_id', $activity->id)
                ->where('verb', 'http://adlnet.gov/expapi/verbs/passed')
                ->whereRaw('(result->\'score\'->>\'scaled\') IS NOT NULL')
                ->avg(DB::raw('(result->\'score\'->>\'scaled\')::float * 100'));
            
            // Ortalama süreyi hesapla
            $avgTime = $this->calculateAverageTimeForActivity($activity->id);
            
            $activity->avg_score = $avgScore;
            $activity->avg_time = $avgTime;
            $activity->completion_rate = $activity->completion_rate ?: 0;
            
            return $activity;
        });
    
    $activeTab = 'activities';
    
    return view('reports.activities', compact(
        'totalStatements',
        'totalActors',
        'totalActivities',
        'activities',
        'activeTab'
    ));
}
// Aktivite için ortalama süreyi hesaplayan yardımcı metot
private function calculateAverageTimeForActivity($activityId)
{
    // Aktivite için tüm süre bilgilerini içeren statement'ları al
    $statements = Statement::where('activity_id', $activityId)
        ->whereRaw('(result->>\'duration\') IS NOT NULL')
        ->get();
    
    if ($statements->isEmpty()) {
        return null;
    }
    
    $durations = [];
    
    foreach ($statements as $statement) {
        $actorId = $statement->actor_id;
        // PostgreSQL'de JSON verilerine erişim
        $durationStr = $statement->result['duration'] ?? null;
        
        if ($durationStr) {
            // ISO 8601 süre formatını saniyeye çevir
            preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $durationStr, $matches);
            
            $hours = isset($matches[1]) ? (int)$matches[1] : 0;
            $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
            $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
            
            $duration = $hours * 3600 + $minutes * 60 + $seconds;
            
            // Her kullanıcı için toplam süreyi hesapla
            if (!isset($durations[$actorId])) {
                $durations[$actorId] = 0;
            }
            $durations[$actorId] += $duration;
        }
    }
    
    if (empty($durations)) {
        return null;
    }
    
    return array_sum($durations) / count($durations);
}

public function userDetails($userId)
{
    // Kullanıcıyı bul
    $user = Actor::findOrFail($userId);
    
    // Kullanıcının tüm statement'larını al
    $statements = Statement::with('activity')
        ->where('actor_id', $userId)
        ->latest('timestamp')
        ->get();
    
    // Kullanıcının etkileşimde bulunduğu course tipindeki aktiviteleri al
    $courseActivities = DB::table('statements')
        ->join('activities', 'statements.activity_id', '=', 'activities.id')
        ->where('statements.actor_id', $userId)
        ->where(function($query) {
            $query->where('activities.type', 'http://adlnet.gov/expapi/activities/course')
                  ->orWhereNull('activities.type');
        })
        ->select('activities.id', 'activities.name', 'activities.activity_id', 'activities.description')
        ->distinct()
        ->get();
    
    // Her bir course aktivitesi için ilerleme durumunu hesapla
    $courseProgress = $courseActivities->map(function($course) use ($userId) {
        // Bu aktivite için tüm statement'ları al
        $activityStatements = Statement::where('actor_id', $userId)
            ->where('activity_id', $course->id)
            ->get();
        
        // Tamamlama durumunu kontrol et
        $completed = $activityStatements->contains(function($statement) {
            return strpos($statement->verb, 'completed') !== false;
        });
        
        // Başarı durumunu kontrol et
        $passed = $activityStatements->contains(function($statement) {
            return strpos($statement->verb, 'passed') !== false;
        });
        
        // Skor bilgisini bul
        $scoreStatement = $activityStatements
            ->sortByDesc('timestamp')
            ->first(function($statement) {
                return isset($statement->result) &&
                       isset($statement->result['score']) &&
                       isset($statement->result['score']['scaled']);
            });
        
        $score = $scoreStatement ? $scoreStatement->result['score']['scaled'] * 100 : null;
        
        // Toplam harcanan süreyi hesapla
        $duration = 0;
        foreach ($activityStatements as $statement) {
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
        
        // Alt aktiviteleri bul (modüller, sorular vb.)
        $subActivities = [];
        
        // Tincan.xml dosyasından alt aktiviteleri çıkarmaya çalış
        $tincanData = $this->getTincanDataForActivity($course->activity_id);
        if ($tincanData && isset($tincanData['activities'])) {
            foreach ($tincanData['activities'] as $subActivity) {
                if ($subActivity['id'] != $course->activity_id) {
                    $subActivities[] = [
                        'id' => $subActivity['id'],
                        'name' => $subActivity['name'] ?? 'İsimsiz Aktivite',
                        'type' => $subActivity['type'] ?? null,
                        'description' => $subActivity['description'] ?? null
                    ];
                }
            }
        }
        
        // Son aktivite zamanı
        $lastActivity = $activityStatements->max('timestamp');
        
        return [
            'id' => $course->id,
            'name' => $course->name,
            'activity_id' => $course->activity_id,
            'description' => $course->description,
            'completed' => $completed,
            'passed' => $passed,
            'score' => $score,
            'duration' => $duration,
            'last_activity' => $lastActivity,
            'statements_count' => $activityStatements->count(),
            'sub_activities' => $subActivities
        ];
    });
    
    // Kullanıcının toplam istatistiklerini hesapla
    $totalStatements = $statements->count();
    $totalActivities = $courseProgress->count();
    $completedCount = $courseProgress->where('completed', true)->count();
    $passedCount = $courseProgress->where('passed', true)->count();
    
    // Ortalama skoru hesapla
    $scores = $courseProgress->filter(function($course) {
        return $course['score'] !== null;
    })->pluck('score');
    
    $avgScore = $scores->isNotEmpty() ? $scores->avg() : null;
    
    // Toplam süreyi hesapla
    $totalTime = $courseProgress->sum('duration');
    
    return view('reports.user_details', compact(
        'user',
        'statements',
        'courseProgress',
        'totalStatements',
        'totalActivities',
        'completedCount',
        'passedCount',
        'avgScore',
        'totalTime'
    ));
}

// Tincan.xml dosyasından aktivite verilerini çıkaran yardımcı metot
private function getTincanDataForActivity($activityId)
{
    // Aktivite ID'sinden içerik yolunu çıkar
    $parts = explode(':', $activityId);
    if (count($parts) < 3) {
        return null;
    }
    
    // Aktivite ID'sinden içerik adını çıkar (örn: urn:articulate:storyline:6Lmotbxq1E0)
    $contentId = end($parts);
    
    // Olası tincan.xml dosya yollarını kontrol et
    $possiblePaths = [
        storage_path('app/public/xapi-content/*/*/tincan.xml'),
        storage_path('app/public/xapi-content/*/*/tincan.xml'),
        public_path('storage/xapi-content/*/*/tincan.xml'),
        public_path('storage/xapi-content/*/*/tincan.xml')
    ];
    
    $tincanXmlPath = null;
    
    foreach ($possiblePaths as $pattern) {
        $files = glob($pattern);
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (strpos($content, $contentId) !== false) {
                $tincanXmlPath = $file;
                break 2;
            }
        }
    }
    
    if (!$tincanXmlPath) {
        return null;
    }
    
    // Tincan.xml dosyasını parse et
    $xml = simplexml_load_file($tincanXmlPath);
    if (!$xml) {
        return null;
    }
    
    // XML'den aktiviteleri çıkar
    $activities = [];
    
    foreach ($xml->activities->activity as $activity) {
        $id = (string)$activity['id'];
        $type = (string)$activity['type'];
        
        $name = null;
        if (isset($activity->name)) {
            $name = (string)$activity->name;
        }
        
        $description = null;
        if (isset($activity->description)) {
            $description = (string)$activity->description;
        }
        
        $activities[] = [
            'id' => $id,
            'type' => $type,
            'name' => $name,
            'description' => $description
        ];
    }
    
    return [
        'activities' => $activities
    ];
}


public function usersReport()
{
    $totalStatements = Statement::count();
    $totalActors = Actor::count();
    $totalActivities = Activity::count();
    
    // Kullanıcıları ve ilgili istatistikleri al
    $users = Actor::select('actors.*')
        ->selectRaw('COUNT(DISTINCT statements.activity_id) as total_activities')
        ->selectRaw('
            (SELECT COUNT(*) FROM statements as s1 
             WHERE s1.actor_id = actors.id 
             AND s1.verb = \'http://adlnet.gov/expapi/verbs/completed\') as completed_activities
        ')
        ->selectRaw('COUNT(statements.id) as total_statements')
        ->leftJoin('statements', 'actors.id', '=', 'statements.actor_id')
        ->groupBy('actors.id')
        ->get()
        ->map(function ($user) {
            // Tamamlama oranını hesapla
            $user->completion_rate = $user->total_activities > 0 
                ? ($user->completed_activities / $user->total_activities) * 100 
                : 0;
            
            // Ortalama skoru hesapla
            $avgScore = DB::table('statements')
                ->where('actor_id', $user->id)
                ->where('verb', 'http://adlnet.gov/expapi/verbs/passed')
                ->whereRaw('(result->\'score\'->>\'scaled\') IS NOT NULL')
                ->avg(DB::raw('(result->\'score\'->>\'scaled\')::float * 100'));
            
            $user->avg_score = $avgScore;
            
            // Son aktivite zamanını bul
            $lastStatement = Statement::where('actor_id', $user->id)
                ->latest('timestamp')
                ->first();
            
            $user->last_activity = $lastStatement ? $lastStatement->timestamp : null;
            
            return $user;
        });
    
    $activeTab = 'users';
    
    return view('reports.users', compact(
        'totalStatements',
        'totalActors',
        'totalActivities',
        'users',
        'activeTab'
    ));
}

}
