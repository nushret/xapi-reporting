<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Actor;
use App\Models\Statement;
use App\Models\User;
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
            
        // Kullanıcıları al
        $users = User::where('is_admin', false)->get();
        $userReports = [];
        
        foreach ($users as $user) {
            $actor = Actor::where('mbox', 'mailto:' . $user->email)->first();
            
            if ($actor) {
                $completedActivities = DB::table('statements')
                    ->join('activities', 'statements.activity_id', '=', 'activities.id')
                    ->where('statements.actor_id', $actor->id)
                    ->where('statements.verb', 'http://adlnet.gov/expapi/verbs/completed')
                    ->select('activities.id', 'activities.name')
                    ->distinct()
                    ->count();
                
                $userReports[] = [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'actor_id' => $actor->id,
                    'completed_activities' => $completedActivities,
                    'total_statements' => Statement::where('actor_id', $actor->id)->count(),
                    'last_activity' => Statement::where('actor_id', $actor->id)->latest('timestamp')->first()?->timestamp
                ];
            }
        }
        
        // Aktif sekme
        $activeTab = 'users'; // Varsayılan olarak kullanıcılar sekmesi
        
        return view('reports.dashboard', compact(
            'totalStatements', 
            'totalActors', 
            'totalActivities', 
            'recentStatements',
            'activities',
            'userReports',
            'activeTab'
        ));
    }
    
    public function activitiesReport()
    {
        $totalStatements = Statement::count();
        $totalActors = Actor::count();
        $totalActivities = Activity::count();
        
        // Aktiviteleri al
        $activities = Activity::withCount('statements')
            ->orderBy('statements_count', 'desc')
            ->get();
            
        // Her aktivite için istatistikler
        foreach ($activities as $activity) {
            $activity->unique_users = DB::table('statements')
                ->where('activity_id', $activity->id)
                ->select('actor_id')
                ->distinct()
                ->count();
                
            $activity->completion_rate = $this->calculateCompletionRate($activity->id);
            $activity->avg_score = $this->calculateAverageScore($activity->id);
            $activity->avg_time = $this->calculateAverageTime($activity->id);
        }
        
        // Aktif sekme
        $activeTab = 'activities'; // Aktiviteler sekmesi
        
        return view('reports.activities', compact(
            'totalStatements', 
            'totalActors', 
            'totalActivities', 
            'activities',
            'activeTab'
        ));
    }
    
    public function userDetails($userId)
    {
        $user = User::findOrFail($userId);
        $actor = Actor::where('mbox', 'mailto:' . $user->email)->first();
        
        if (!$actor) {
            return redirect()->route('dashboard.reports')->with('error', 'Bu kullanıcı için xAPI verisi bulunamadı.');
        }
        
        // Kullanıcının etkileşimde bulunduğu aktiviteleri al
        $userActivities = DB::table('statements')
            ->join('activities', 'statements.activity_id', '=', 'activities.id')
            ->where('statements.actor_id', $actor->id)
            ->select('activities.id', 'activities.name', 'activities.activity_id', 'activities.description')
            ->distinct()
            ->get();
            
        // Her aktivite için istatistikler
        $activityStats = [];
        foreach ($userActivities as $activity) {
            $statements = Statement::where('actor_id', $actor->id)
                ->where('activity_id', $activity->id)
                ->get();
                
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
                    $durationStr = $statement->result['duration'];
                    preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $durationStr, $matches);
                    
                    $hours = isset($matches[1]) ? (int)$matches[1] : 0;
                    $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
                    $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
                    
                    $duration += $hours * 3600 + $minutes * 60 + $seconds;
                }
            }
            
            $activityStats[$activity->id] = [
                'completed' => $completed,
                'passed' => $passed,
                'score' => $score,
                'duration' => $duration,
                'last_activity' => $statements->max('timestamp'),
                'statements_count' => $statements->count()
            ];
        }
        
        return view('reports.user_details', compact('user', 'actor', 'userActivities', 'activityStats'));
    }
    
    public function userActivityDetails($userId, $activityId)
    {
        $user = User::findOrFail($userId);
        $actor = Actor::where('mbox', 'mailto:' . $user->email)->first();
        $activity = Activity::findOrFail($activityId);
        
        if (!$actor) {
            return redirect()->route('dashboard.reports')->with('error', 'Bu kullanıcı için xAPI verisi bulunamadı.');
        }
        
        // Kullanıcının bu aktivitedeki tüm statement'larını al
        $statements = Statement::where('actor_id', $actor->id)
            ->where('activity_id', $activityId)
            ->orderBy('timestamp', 'desc')
            ->get();
            
        // Aktivite detaylarını tincan.xml'den al
        $activityDetails = $this->getActivityDetailsFromTinCan($activity->activity_id);
        
        // Etkileşimleri grupla
        $interactions = [];
        $answers = [];
        
        foreach ($statements as $statement) {
            if (strpos($statement->verb, 'answered') !== false) {
                $result = $statement->result ?? null;
                
                if ($result && isset($result['response'])) {
                    $objectId = null;
                    if (isset($statement->context) && isset($statement->context['contextActivities']) && 
                        isset($statement->context['contextActivities']['parent'][0]['id'])) {
                        $objectId = $statement->context['contextActivities']['parent'][0]['id'];
                    }
                    
                    $interactionDetails = $this->findInteractionDetails($activityDetails, $objectId);
                    
                    $interactions[] = [
                        'id' => $statement->id,
                        'timestamp' => $statement->timestamp,
                        'object_id' => $objectId,
                        'response' => $result['response'],
                        'success' => $result['success'] ?? null,
                        'score' => isset($result['score']) ? ($result['score']['scaled'] * 100) : null,
                        'details' => $interactionDetails
                    ];
                    
                    $answers[$objectId] = [
                        'response' => $result['response'],
                        'success' => $result['success'] ?? null,
                        'timestamp' => $statement->timestamp
                    ];
                }
            }
        }
        
        // Toplam süre ve skor hesapla
        $totalDuration = 0;
        $finalScore = null;
        
        foreach ($statements as $statement) {
            if (isset($statement->result) && isset($statement->result['duration'])) {
                $durationStr = $statement->result['duration'];
                preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $durationStr, $matches);
                
                $hours = isset($matches[1]) ? (int)$matches[1] : 0;
                $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
                $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
                
                $totalDuration += $hours * 3600 + $minutes * 60 + $seconds;
            }
            
            if (isset($statement->result) && isset($statement->result['score']) && isset($statement->result['score']['scaled'])) {
                $finalScore = $statement->result['score']['scaled'] * 100;
            }
        }
        
        $completed = $statements->contains('verb', 'http://adlnet.gov/expapi/verbs/completed');
        $passed = $statements->contains('verb', 'http://adlnet.gov/expapi/verbs/passed');
        
        return view('reports.user_activity_details', compact(
            'user', 
            'actor', 
            'activity', 
            'statements', 
            'interactions', 
            'activityDetails',
            'answers',
            'totalDuration',
            'finalScore',
            'completed',
            'passed'
        ));
    }
    
    private function getActivityDetailsFromTinCan($activityId)
    {
        // İçerik yolunu bul
        $activity = Activity::where('activity_id', $activityId)->first();
        if (!$activity || !$activity->content_path) {
            return null;
        }
        
        $contentPath = storage_path('app/public/xapi-content/' . $activity->content_path);
        
        // tincan.xml dosyasını bul
        $tincanXmlPath = null;
        
        // Ana dizinde ara
        if (file_exists($contentPath . '/tincan.xml')) {
            $tincanXmlPath = $contentPath . '/tincan.xml';
        } else {
            // Alt dizinlerde ara
            $directories = glob($contentPath . '/*', GLOB_ONLYDIR);
            foreach ($directories as $dir) {
                if (file_exists($dir . '/tincan.xml')) {
                    $tincanXmlPath = $dir . '/tincan.xml';
                    break;
                }
            }
        }
        
        if (!$tincanXmlPath) {
            return null;
        }
        
        // tincan.xml dosyasını analiz et
        $xml = simplexml_load_file($tincanXmlPath);
        
        if (!$xml) {
            return null;
        }
        
        $activityDetails = [];
        
        // Tüm aktiviteleri ve etkileşimleri topla
        foreach ($xml->activities->activity as $xmlActivity) {
            $id = (string)$xmlActivity['id'];
            $type = (string)$xmlActivity['type'];
            $name = (string)$xmlActivity->name;
            $description = (string)$xmlActivity->description;
            
            $activityDetails[$id] = [
                'id' => $id,
                'type' => $type,
                'name' => $name,
                'description' => $description,
                'interactions' => []
            ];
            
            // Etkileşim tipindeki aktiviteleri işle
            if ($type === 'http://adlnet.gov/expapi/activities/cmi.interaction') {
                $interactionType = (string)$xmlActivity->interactionType;
                $correctResponses = [];
                
                if (isset($xmlActivity->correctResponsePatterns)) {
                    foreach ($xmlActivity->correctResponsePatterns->correctResponsePattern as $pattern) {
                        $correctResponses[] = (string)$pattern;
                    }
                }
                
                $choices = [];
                if (isset($xmlActivity->choices)) {
                    foreach ($xmlActivity->choices->component as $component) {
                        $choices[(string)$component->id] = (string)$component->description;
                    }
                }
                
                $source = [];
                if (isset($xmlActivity->source)) {
                    foreach ($xmlActivity->source->component as $component) {
                        $source[(string)$component->id] = (string)$component->description;
                    }
                }
                
                $target = [];
                if (isset($xmlActivity->target)) {
                    foreach ($xmlActivity->target->component as $component) {
                        $target[(string)$component->id] = (string)$component->description;
                    }
                }
                
                $activityDetails[$id]['interactionType'] = $interactionType;
                $activityDetails[$id]['correctResponsePatterns'] = $correctResponses;
                $activityDetails[$id]['choices'] = $choices;
                $activityDetails[$id]['source'] = $source;
                $activityDetails[$id]['target'] = $target;
            }
        }
        
        return $activityDetails;
    }
    
    private function findInteractionDetails($activityDetails, $objectId)
    {
        if (!$activityDetails || !$objectId) {
            return null;
        }
        
        return $activityDetails[$objectId] ?? null;
    }
    
    private function calculateCompletionRate($activityId)
    {
        $totalUsers = DB::table('statements')
            ->where('activity_id', $activityId)
            ->select('actor_id')
            ->distinct()
            ->count();
            
        if ($totalUsers === 0) {
            return 0;
        }
        
        $completedUsers = DB::table('statements')
            ->where('activity_id', $activityId)
            ->where('verb', 'http://adlnet.gov/expapi/verbs/completed')
            ->select('actor_id')
            ->distinct()
            ->count();
            
        return ($completedUsers / $totalUsers) * 100;
    }
    
    private function calculateAverageScore($activityId)
    {
        $scores = [];
        
        $statements = Statement::where('activity_id', $activityId)
            ->whereNotNull('result')
            ->get();
            
        foreach ($statements as $statement) {
            if (isset($statement->result['score']) && isset($statement->result['score']['scaled'])) {
                $actorId = $statement->actor_id;
                $score = $statement->result['score']['scaled'] * 100;
                
                // Her kullanıcı için en son skoru al
                $scores[$actorId] = $score;
            }
        }
        
        if (empty($scores)) {
            return null;
        }
        
        return array_sum($scores) / count($scores);
    }
    
    private function calculateAverageTime($activityId)
    {
        $durations = [];
        
        $statements = Statement::where('activity_id', $activityId)
            ->whereNotNull('result')
            ->get();
            
        foreach ($statements as $statement) {
            if (isset($statement->result['duration'])) {
                $actorId = $statement->actor_id;
                $durationStr = $statement->result['duration'];
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
    
    // Diğer mevcut metodlar (statements, storeStatement, processActor, processActivity, getStatements)...
    
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
        }
        
