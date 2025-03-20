<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{



public function dashboard()
{
    try {
        // Kullanıcıları çek
        $users = User::where('is_admin', false)->get();
        
        // İstatistikleri hesapla
        $totalStatements = \App\Models\Statement::count();
        $totalActors = \App\Models\Actor::count();
        $totalActivities = \App\Models\Activity::count();
        
        // Aktiviteleri çek (statements_count ile birlikte)
        $activities = \App\Models\Activity::withCount('statements')
            ->orderBy('statements_count', 'desc')
            ->take(10)
            ->get();
        
        // Son ifadeleri çek
        $recentStatements = \App\Models\Statement::with(['actor', 'activity'])
            ->latest('timestamp')
            ->take(10)
            ->get();
        
        // View'a değişkenleri aktar
        return view('admin.dashboard', compact(
            'users',
            'totalStatements',
            'totalActors',
            'totalActivities',
            'activities',
            'recentStatements'
        ));
    } catch (\Exception $e) {
        // Genel hata durumunda log'a yaz
        \Log::error("Dashboard hatası: " . $e->getMessage());
        return back()->with('error', 'Bir hata oluştu: ' . $e->getMessage());
    }
}

public function listUsers()
{
    $users = User::where('is_admin', false)->get();
    return view('admin.users.index', compact('users'));
}



    public function showUserForm()
    {
        $contents = [];
        $directories = Storage::disk('public')->directories('xapi-content');
        
        foreach ($directories as $directory) {
            $contentPath = basename($directory);
            $contents[] = [
                'path' => $contentPath,
                'name' => $contentPath,
            ];
        }
        
        return view('admin.user_form', compact('contents'));
    }
    
    public function storeUser(Request $request)
    {


		$validated = $request->validate([
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed',
        'contents' => 'nullable|array',
    ]);
    
    $user = User::create([
        'name' => $validated['first_name'] . ' ' . $validated['last_name'], // name alanını doldur
        'first_name' => $validated['first_name'],
        'last_name' => $validated['last_name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'is_admin' => false,
    ]);



        if (isset($validated['contents'])) {
            foreach ($validated['contents'] as $contentPath) {
                // İçerik yoksa oluştur
                $content = Content::firstOrCreate(
                    ['path' => $contentPath],
                    ['name' => $contentPath]
                );
                
                // Kullanıcıya içeriği ata
                $user->contents()->attach($contentPath);
            }
        }
        
        return redirect()->route('admin.dashboard')->with('success', 'Kullanıcı başarıyla oluşturuldu.');
    }
    
    public function editUser($id)
    {
        $user = User::findOrFail($id);
        
        $contents = [];
        $directories = Storage::disk('public')->directories('xapi-content');
        
        foreach ($directories as $directory) {
            $contentPath = basename($directory);
            $contents[] = [
                'path' => $contentPath,
                'name' => $contentPath,
            ];
        }
        
        $userContentPaths = $user->contents->pluck('path')->toArray();
        
        return view('admin.user_edit', compact('user', 'contents', 'userContentPaths'));
    }
    
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
            'contents' => 'nullable|array',
        ]);
        
        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->email = $validated['email'];
        
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        
        $user->save();
        
        // İçerikleri güncelle
        $user->contents()->detach();
        
        if (isset($validated['contents'])) {
            foreach ($validated['contents'] as $contentPath) {
                // İçerik yoksa oluştur
                $content = Content::firstOrCreate(
                    ['path' => $contentPath],
                    ['name' => $contentPath]
                );
                
                // Kullanıcıya içeriği ata
                $user->contents()->attach($contentPath);
            }
        }
        
        return redirect()->route('admin.dashboard')->with('success', 'Kullanıcı başarıyla güncellendi.');
    }
    
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        
        return redirect()->route('admin.dashboard')->with('success', 'Kullanıcı başarıyla silindi.');
    }
}
