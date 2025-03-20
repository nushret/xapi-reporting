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

                $completed = $userStatements->contains('verb', 'http://adlnet.gov/expapi/verbs/completed');
                $passed = $userStatements->contains('verb', 'http://adlnet.gov/expapi/verbs/passed');

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

        return view('activity_details', compact('activity', 'statements', 'userProgress'));
    }

    public function userActivityDetails($activityId, $userId)
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

        return view('user_activity_details', compact('activity', 'user', 'statements', 'interactions'));
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
        
        $activity = Activity::firstOrCreate(
            ['activity_id' => $activityId],
            [
                'name' => $objectData['definition']['name']['en-US'] ?? 'Bilinmeyen Aktivite',
                'description' => $objectData['definition']['description']['en-US'] ?? null,
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
