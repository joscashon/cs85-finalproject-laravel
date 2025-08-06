<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Run;
use App\Models\UserProfile;
use League\CommonMark\CommonMarkConverter;

class RunController extends Controller
{
    public function index(Request $request)
    {
        $runs = Run::orderBy('date', 'desc')->get();

        $totalRuns = $runs->count();
        $totalDistance = $runs->sum('miles');
        $totalMinutes = $runs->sum('minutes');
        $totalSeconds = $runs->sum('seconds');
        $totalTime = round($totalMinutes / 60 + $totalSeconds / 3600, 2);

        // Average pace (min/mile)
        $averagePace = $totalDistance > 0 ? round(($totalMinutes + $totalSeconds / 60) / $totalDistance, 2) : 0;

        // Current streak calculation (up to most recent run)
        $dates = $runs->pluck('date')->unique()->sortDesc()->values();
        $recentStreak = 0;
        if (!$dates->isEmpty()) {
            $recentStreak = 1;
            for ($i = 1; $i < count($dates); $i++) {
                $prev = new \DateTime($dates[$i - 1]);
                $curr = new \DateTime($dates[$i]);
                if ($prev->diff($curr)->days === 1) {
                    $recentStreak++;
                } else {
                    break;
                }
            }
        }

        // Calculate streak as of each run's date
        $streaks = [];
        $dates = $runs->pluck('date')->unique()->sortDesc()->values();
        foreach ($runs as $run) {
            $runDate = new \DateTime($run->date);
            $runStreak = 0;
            foreach ($dates as $date) {
                $dateObj = new \DateTime($date);
                if ($dateObj > $runDate) continue;
                if ($dateObj->diff($runDate)->days === $runStreak && $dateObj <= $runDate) {
                    $runStreak++;
                } else {
                    break;
                }
            }
            $streaks[$run->id] = $runStreak;
        }

        // Calculate best streak (longest consecutive-day streak)
        $bestStreak = 0;
        if (!$dates->isEmpty()) {
            $current = 1;
            for ($i = 1; $i < count($dates); $i++) {
                $prev = new \DateTime($dates[$i - 1]);
                $curr = new \DateTime($dates[$i]);
                if ($prev->diff($curr)->days === 1) {
                    $current++;
                } else {
                    if ($current > $bestStreak) $bestStreak = $current;
                    $current = 1;
                }
            }
            if ($current > $bestStreak) $bestStreak = $current; // Check last streak
        }

        $ai_feedback = session('ai_feedback', null);

        // Parse markdown if feedback exists
        $ai_feedback_html = null;
        if ($ai_feedback) {
            $converter = new CommonMarkConverter([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ]);
            $ai_feedback_html = $converter->convert($ai_feedback)->getContent();
        }

        $profile = UserProfile::first();

        return view('run', compact(
            'runs', 'ai_feedback', 'totalRuns', 'totalDistance', 'totalTime', 'averagePace',
            'recentStreak', 'bestStreak', 'streaks', 'profile', 'ai_feedback_html'
        ));
    }

    public function addRun(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'miles' => 'required|numeric|min:0.01',
            'minutes' => 'required|integer|min:0',
            'seconds' => 'required|integer|min:0|max:59',
        ]);

        Run::create($request->only(['date', 'miles', 'minutes', 'seconds']));
        return redirect('/');
    }

    public function deleteRun(Request $request)
    {
        $id = $request->input('delete');
        $run = Run::find($id);
        if ($run) {
            $run->delete();
        }
        return redirect('/');
    }

    public function generateFeedback(Request $request)
    {
        $runs = Run::orderBy('date', 'desc')->get()->toArray();
        $run = new \App\Models\Run([]);
        $run->runs = $runs;

        // Prepare a summary of the user's run history and stats
        $summary = "Run History:\n";
        foreach ($run->runs as $r) {
            $summary .= "Date: {$r['date']}, Miles: {$r['miles']}, Time: {$r['minutes']}m {$r['seconds']}s, Pace: " . $run->paceForRun($r) . " min/mile\n";
        }
        $summary .= "\nStatistics:\n";
        $summary .= "Most Recent Streak: " . $run->streak() . " days\n";
        $summary .= "Total Runs: " . $run->totalRuns() . "\n";
        $summary .= "Total Time Running: " . $run->totalHours() . " hours\n";
        $summary .= "Average Pace: " . $run->averagePace() . " min/mile\n";

        // Get user profile information
        $profile = UserProfile::first();
        $profileSummary = '';
        if ($profile) {
            $profileSummary = "User Profile:\n";
            $profileSummary .= "Fitness Level: " . $profile->fitness_level . "\n";
            $profileSummary .= "Age: " . $profile->age . "\n";
            $profileSummary .= "Sex: " . $profile->sex . "\n";
            $profileSummary .= "Height: " . $profile->height . " in\n";
            $profileSummary .= "Weight: " . $profile->weight . " lbs\n\n";
        }
        $summary = $profileSummary . $summary;

        // Compose the prompt for the AI
        $prompt =
            "**You are an expert, encouraging, and evidence-based running coach, skilled at motivating athletes of all levels. Your advice is practical, empathetic, and always grounded in the provided data.**\n\n" .
            "**Task:** Given the following user profile and run history, provide tailored feedback.\n\n" .
            "**Instructions:**\n" .
            "1.  **Encouragement:** Start your response with a dedicated section offering general encouragement based on the user's progress and strengths.\n" .
            "2.  **Recommendations for Improvement:** Provide at least three **specific, actionable, evidence-based recommendations** for improvement.\n" .
            "    *   Your advice should be tailored to the user's data and may include suggestions such as: adjusting target pace or distance, changing frequency of rest days, incorporating sprint or endurance training, using interval programs like C25K, or other relevant running strategies.\n" .
            "    *   **Be concrete and reference the user's actual stats, history, and profile in your suggestions.**\n" .
            "    *   Each recommendation should be presented as a distinct bullet point, clearly stating the suggestion and briefly explaining its rationale based on the provided data.\n\n" .
            "**Format:**\n" .
            "*   Format your response with a clear '**Encouragement**' heading, followed by a '**Recommendations for Improvement**' heading.\n" .
            "*   Each recommendation within the 'Recommendations for Improvement' section should be a distinct bullet point.\n" .
            "*   **Length:** The total response should be **between 300-500 words** to ensure conciseness and impact.\n\n" .
            "**User Data:**\n" . $summary;

        // Call OpenAI API
        $apiKey = env('OPENAI_API_KEY');
        $data = [
            "model" => "gpt-3.5-turbo",
            "messages" => [
                ["role" => "system", "content" => "You are a helpful and motivational running coach."],
                ["role" => "user", "content" => $prompt]
            ],
            "max_tokens" => 600,
            "temperature" => 0.7
        ];

        $ch = curl_init("https://api.openai.com/v1/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result, true);

        // Debug: Show raw API response if there's an error
        if (!isset($response['choices'][0]['message']['content'])) {
            $ai_feedback = "OpenAI API error: " . htmlspecialchars($result);
        } else {
            $ai_feedback = $response['choices'][0]['message']['content'];
        }

        session(['ai_feedback' => $ai_feedback]);
        return redirect('/');
    }

    // Save profile to DB
    public function saveProfile(Request $request)
    {
        $request->validate([
            'fitness_level' => 'required|in:sedentary,lightly active,active,very active',
            'age' => 'required|integer|min:1|max:120',
            'sex' => 'required|in:male,female,other',
            'height' => 'required|numeric|min:20|max:100', // inches
            'weight' => 'required|numeric|min:40|max:700', // pounds
        ]);

        $profile = UserProfile::first();
        if ($profile) {
            $profile->update($request->only(['fitness_level', 'age', 'sex', 'height', 'weight']));
        } else {
            UserProfile::create($request->only(['fitness_level', 'age', 'sex', 'height', 'weight']));
        }
        return redirect('/');
    }

    public function deleteFeedback(Request $request)
    {
        // Remove feedback from session
        session()->forget('ai_feedback');
        return redirect('/');
    }
}