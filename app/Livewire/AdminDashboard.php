<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

use App\Models\User;
use App\Models\Course;
use App\Models\UserRequest;
use App\Models\File;
use App\Models\Message;

class AdminDashboard extends Component
{
    // State Cards
    public int $totalUsers        = 0;
    public int $newUsersMonth     = 0;
    public int $coursesToday      = 0;
    public int $openUserRequests  = 0;

    // Sektionen/Listen
    public int   $coursesThisWeek = 0;
    public int   $examsThisWeek   = 0; // nur wenn Tabelle existiert
    public $recentUploads; // Top 5 (7 Tage)
    public $recentMessages; // Top 5 ungelesen

    // Charts/Verläufe
    public array $activeUsersHistory = []; // letzte 10 Stunden

    public bool $autoRefresh = true;

    public function mount(): void
    {
        $this->refreshAll();
    }

    public function render()
    {
        return view('livewire.admin-dashboard')->layout('layouts.master');
    }

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = ! $this->autoRefresh;
    }

    public function refreshAll(): void
    {
        $this->updateCounters();
        $this->getActiveUsersHistory();
        $this->buildThisWeekStats();
        $this->getRecentUploads();
        $this->getRecentUnreadMessages();
    }

    protected function tableHas(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function updateCounters(): void
    {
        $now          = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $today        = $now->toDateString();

        // --- Users ---
        if (class_exists(User::class)) {
            $this->totalUsers    = User::count();
            $this->newUsersMonth = User::whereBetween('created_at', [$startOfMonth, $now])->count();
        } else {
            $this->totalUsers = Schema::hasTable('users') ? DB::table('users')->count() : 0;
            $this->newUsersMonth = Schema::hasTable('users')
                ? DB::table('users')->whereBetween('created_at', [$startOfMonth, $now])->count()
                : 0;
        }

        // --- Kurse heute ---
        if (class_exists(Course::class)) {
            $this->coursesToday = Course::query()
                ->whereDate('planned_start_date', '<=', $today)
                ->whereDate('planned_end_date', '>=', $today)
                ->count();
        } elseif (Schema::hasTable('courses')) {
            $this->coursesToday = DB::table('courses')
                ->whereDate('planned_start_date', '<=', $today)
                ->whereDate('planned_end_date', '>=', $today)
                ->count();
        } else {
            $this->coursesToday = 0;
        }

        // --- Offene User Requests ---
        if (class_exists(UserRequest::class)) {
            $this->openUserRequests = UserRequest::where('status', 'pending')->count();
        } elseif (Schema::hasTable('user_requests')) {
            $this->openUserRequests = DB::table('user_requests')->where('status', 'pending')->count();
        } else {
            $this->openUserRequests = 0;
        }
    }

    // Aktive Nutzer je Stunde (letzte 10 Stunden)
    public function getActiveUsersHistory(): void
    {
        $history = [];

        for ($i = 0; $i < 10; $i++) {
            $from = Carbon::now()->subHours($i + 1)->minute(0)->second(0);
            $to   = $from->copy()->addHour();

            $count = DB::table('sessions')
                ->join('users', 'sessions.user_id', '=', 'users.id')
                ->where('users.role', 'guest')
                ->whereBetween('sessions.last_activity', [$from->timestamp, $to->timestamp])
                ->count();

            $history[] = $count;
        }

        $this->activeUsersHistory = array_reverse($history);
    }

    // Woche: Kurse & Prüfungen (falls vorhanden)
    public function buildThisWeekStats(): void
    {
        $monday = now()->startOfWeek(); // Montag
        $sunday = now()->endOfWeek();   // Sonntag

        // Kurse dieser Woche: Überschneidung Zeitraum <-> Woche
        if (class_exists(Course::class)) {
            $this->coursesThisWeek = Course::query()
                ->whereDate('planned_end_date', '>=', $monday->toDateString())
                ->whereDate('planned_start_date', '<=', $sunday->toDateString())
                ->count();
        } elseif (Schema::hasTable('courses')) {
            $this->coursesThisWeek = DB::table('courses')
                ->whereDate('planned_end_date', '>=', $monday->toDateString())
                ->whereDate('planned_start_date', '<=', $sunday->toDateString())
                ->count();
        } else {
            $this->coursesThisWeek = 0;
        }

        // Prüfungen zählen (optional, wenn Tabelle existiert)
        $this->examsThisWeek = 0;
        foreach (['exams', 'pruefungen', 'exam_terms', 'pruefungstermine'] as $examTable) {
            if (Schema::hasTable($examTable)) {
                // gängige Datumsfelder ausprobieren
                $dateField = null;
                foreach (['date', 'exam_date', 'planned_date', 'starts_at'] as $f) {
                    if ($this->tableHas($examTable, $f)) {
                        $dateField = $f;
                        break;
                    }
                }
                if ($dateField) {
                    $this->examsThisWeek = DB::table($examTable)
                        ->whereBetween($dateField, [$monday->toDateString(), $sunday->toDateString()])
                        ->count();
                }
                break;
            }
        }
    }

    // Letzte Uploads (7 Tage) – Top 5
public function getRecentUploads(): void
{
    $sevenDaysAgo = now()->subDays(7);

        $this->recentUploads = File::query()
            ->with('fileable') // optional, wenn du die zugehörigen Modelle im Blade brauchst
            ->where('created_at', '>=', $sevenDaysAgo)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
}


    // Ungelesene Nachrichten – Top 5 (modellbasiert oder DB-Fallback)
public function getRecentUnreadMessages(): void
{
    // Top 5 ungelesene Nachrichten als echte Models
    $this->recentMessages = Message::query()
        ->with(['sender:id,name'])
        ->where('status', 1)
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
}


}
